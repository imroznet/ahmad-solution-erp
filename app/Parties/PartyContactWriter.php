<?php

namespace App\Parties;

use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PartyContactWriter
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PartyAuthorizer $authorizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Party $party, array $attributes, User $actor): PartyContact
    {
        $this->guard($party, $actor);

        return DB::transaction(function () use ($party, $attributes, $actor): PartyContact {
            $this->lock($party->id);
            $payload = $this->payload($attributes, $actor);
            $this->clearPrimary($party, $payload['is_primary'], null, $actor);

            $contact = PartyContact::query()->create([
                ...$payload,
                'party_id' => $party->id,
                'created_by' => $actor->id,
            ]);
            $this->audit->record('party_contact_created', $contact, [], $contact->auditSnapshot(), $actor);

            return $contact;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PartyContact $contact, array $attributes, User $actor): PartyContact
    {
        $party = $this->party($contact);
        $this->guard($party, $actor);

        return DB::transaction(function () use ($contact, $attributes, $actor, $party): PartyContact {
            $this->lock($party->id);
            $contact->refresh();
            $old = $contact->auditSnapshot();
            $payload = $this->payload($attributes, $actor);
            $this->clearPrimary($party, $payload['is_primary'], $contact->id, $actor);
            $contact->fill($payload);
            $contact->save();
            $this->audit->record('party_contact_updated', $contact, $old, $contact->auditSnapshot(), $actor);

            return $contact;
        });
    }

    public function archive(PartyContact $contact, User $actor): PartyContact
    {
        $party = $this->party($contact);
        $this->guard($party, $actor);

        return DB::transaction(function () use ($contact, $actor, $party): PartyContact {
            $this->lock($party->id);
            $contact->refresh();

            if (! $contact->is_active) {
                throw ValidationException::withMessages([
                    'contact' => 'This contact is already archived.',
                ]);
            }

            $old = $contact->auditSnapshot();
            $contact->fill([
                'is_active' => false,
                'is_primary' => false,
                'updated_by' => $actor->id,
            ]);
            $contact->save();
            $this->audit->record('party_contact_archived', $contact, $old, $contact->auditSnapshot(), $actor);

            return $contact;
        });
    }

    private function guard(Party $party, User $actor): void
    {
        if (! $this->authorizer->canManageContacts($actor, $party)) {
            throw new AuthorizationException;
        }
    }

    private function party(PartyContact $contact): Party
    {
        $party = $contact->party;

        if (! $party instanceof Party) {
            throw new AuthorizationException;
        }

        return $party;
    }

    private function clearPrimary(Party $party, bool $primary, ?int $ignoreId, User $actor): void
    {
        if (! $primary) {
            return;
        }

        $previous = PartyContact::query()
            ->where('party_id', $party->id)
            ->where('is_primary', true)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        foreach ($previous as $contact) {
            $old = $contact->auditSnapshot();
            $contact->fill([
                'is_primary' => false,
                'updated_by' => $actor->id,
            ]);
            $contact->save();
            $this->audit->record('party_contact_updated', $contact, $old, $contact->auditSnapshot(), $actor);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes, User $actor): array
    {
        $active = (bool) ($attributes['is_active'] ?? false);

        return [
            'name' => trim((string) ($attributes['name'] ?? '')),
            'designation' => $this->nullableString($attributes['designation'] ?? null),
            'department' => $this->nullableString($attributes['department'] ?? null),
            'email' => $this->email($attributes['email'] ?? null),
            'phone' => $this->nullableString($attributes['phone'] ?? null),
            'alternative_phone' => $this->nullableString($attributes['alternative_phone'] ?? null),
            'whatsapp_number' => $this->nullableString($attributes['whatsapp_number'] ?? null),
            'is_active' => $active,
            'is_primary' => $active && (bool) ($attributes['is_primary'] ?? false),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
            'updated_by' => (int) $actor->id,
        ];
    }

    private function lock(int $partyId): void
    {
        Party::query()->whereKey($partyId)->lockForUpdate()->first();
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
