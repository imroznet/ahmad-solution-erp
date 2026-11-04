<?php

namespace App\Parties;

use App\Models\Party;
use App\Models\PartyRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PartyWriter
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PartyAuthorizer $authorizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): Party
    {
        $roles = $this->roles($attributes);

        if (! $this->authorizer->canCreate($actor, $roles)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($attributes, $actor, $roles): Party {
            $party = Party::query()->create([
                ...$this->payload($attributes, $actor, true),
                'created_by' => $actor->id,
            ]);

            foreach ($roles as $role) {
                PartyRole::query()->create([
                    'party_id' => $party->id,
                    'role' => $role,
                ]);
                $this->audit->record('party_role_added', $party, [], ['role' => $role], $actor);
            }

            $party->load('roles');
            $this->audit->record('party_created', $party, [], $party->auditSnapshot(), $actor);

            return $party;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Party $party, array $attributes, User $actor): Party
    {
        if (! $this->authorizer->canUpdate($actor, $party)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($party, $attributes, $actor): Party {
            Party::query()->whereKey($party->id)->lockForUpdate()->first();
            $party->refresh();
            $party->load('roles');

            if ($party->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'Archived parties cannot be edited. Reactivate the party first.',
                ]);
            }

            $old = $party->auditSnapshot();
            $party->fill($this->payload($attributes, $actor, false));
            $party->save();
            $this->syncRoles($party, $this->roles($attributes), $actor);
            $party->load('roles');
            $new = $party->auditSnapshot();

            if ($old !== $new) {
                $this->audit->record('party_updated', $party, $old, $new, $actor);
            }

            return $party;
        });
    }

    public function archive(Party $party, User $actor): Party
    {
        return $this->changeStatus($party, $actor, 'archived', 'party_archived');
    }

    public function reactivate(Party $party, User $actor): Party
    {
        return $this->changeStatus($party, $actor, 'active', 'party_reactivated');
    }

    private function changeStatus(Party $party, User $actor, string $status, string $event): Party
    {
        if (! $this->authorizer->canArchive($actor, $party)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($party, $actor, $status, $event): Party {
            Party::query()->whereKey($party->id)->lockForUpdate()->first();
            $party->refresh();
            $old = $party->stringAttribute('status');

            if ($old === $status) {
                throw ValidationException::withMessages([
                    'status' => $status === 'archived'
                        ? 'This party is already archived.'
                        : 'This party is already active.',
                ]);
            }

            if ($status === 'active' && $old !== 'archived') {
                throw ValidationException::withMessages([
                    'status' => 'Only an archived party can be reactivated.',
                ]);
            }

            $party->fill([
                'status' => $status,
                'updated_by' => $actor->id,
            ]);
            $party->save();
            $this->audit->record($event, $party, ['status' => $old], ['status' => $status], $actor);

            return $party;
        });
    }

    /**
     * @param  list<string>  $roles
     */
    private function syncRoles(Party $party, array $roles, User $actor): void
    {
        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => 'A party needs at least one role.',
            ]);
        }

        $current = $party->roles->pluck('role')->all();
        $add = array_values(array_diff($roles, $current));
        $remove = array_values(array_diff($current, $roles));

        foreach ([...$add, ...$remove] as $role) {
            if (! $this->authorizer->canChangeRole($actor, $role)) {
                throw new AuthorizationException;
            }
        }

        foreach ($remove as $role) {
            $party->roles()->where('role', $role)->delete();
            $this->audit->record('party_role_removed', $party, ['role' => $role], [], $actor);
        }

        foreach ($add as $role) {
            PartyRole::query()->create([
                'party_id' => $party->id,
                'role' => $role,
            ]);
            $this->audit->record('party_role_added', $party, [], ['role' => $role], $actor);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function roles(array $attributes): array
    {
        $roles = $attributes['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $clean = [];

        foreach ($roles as $role) {
            if (is_string($role) && isset(PartyRoleName::options()[$role])) {
                $clean[] = $role;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes, User $actor, bool $creating): array
    {
        $status = $creating ? 'active' : (string) ($attributes['status'] ?? 'active');

        if (! isset(PartyStatus::options()[$status]) || $status === 'archived') {
            $status = 'active';
        }

        return [
            'legal_name' => trim((string) ($attributes['legal_name'] ?? '')),
            'trading_name' => $this->nullableString($attributes['trading_name'] ?? null),
            'legal_name_bn' => $this->nullableString($attributes['legal_name_bn'] ?? null),
            'trading_name_bn' => $this->nullableString($attributes['trading_name_bn'] ?? null),
            'party_kind' => (string) ($attributes['party_kind'] ?? 'company'),
            'registration_number' => $this->nullableString($attributes['registration_number'] ?? null),
            'tin' => $this->nullableString($attributes['tin'] ?? null),
            'bin' => $this->nullableString($attributes['bin'] ?? null),
            'vat_registration_number' => $this->nullableString($attributes['vat_registration_number'] ?? null),
            'email' => $this->email($attributes['email'] ?? null),
            'phone' => $this->nullableString($attributes['phone'] ?? null),
            'website' => $this->nullableString($attributes['website'] ?? null),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
            'status' => $status,
            'currency_code' => 'BDT',
            'updated_by' => (int) $actor->id,
        ];
    }

    private function email(mixed $value): ?string
    {
        $email = $this->nullableString($value);

        return $email === null ? null : mb_strtolower($email);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
