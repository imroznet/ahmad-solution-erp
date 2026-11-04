<?php

namespace App\Parties;

use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PartyAddressWriter
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PartyAuthorizer $authorizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Party $party, array $attributes, User $actor): PartyAddress
    {
        $this->guard($party, $actor);

        return DB::transaction(function () use ($party, $attributes, $actor): PartyAddress {
            $this->lock($party->id);

            return $this->createUnlocked($party, $attributes, $actor, null);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PartyAddress $address, array $attributes, User $actor): PartyAddress
    {
        $party = $this->party($address);
        $this->guard($party, $actor);

        return DB::transaction(function () use ($address, $attributes, $actor, $party): PartyAddress {
            $this->lock($party->id);
            $address->refresh();
            $old = $address->auditSnapshot();
            $payload = $this->payload($attributes, $actor);
            $this->clearDefaults($party, $payload, $address->id, $actor);
            $address->fill($payload);
            $address->save();
            $this->audit->record('party_address_updated', $address, $old, $address->auditSnapshot(), $actor);

            return $address;
        });
    }

    public function close(PartyAddress $address, User $actor, ?string $effectiveUntil = null): PartyAddress
    {
        $party = $this->party($address);
        $this->guard($party, $actor);

        return DB::transaction(function () use ($address, $actor, $effectiveUntil, $party): PartyAddress {
            $this->lock($party->id);
            $address->refresh();

            return $this->closeUnlocked($address, $actor, $effectiveUntil);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function supersede(PartyAddress $address, array $attributes, User $actor): PartyAddress
    {
        $party = $this->party($address);
        $this->guard($party, $actor);

        return DB::transaction(function () use ($address, $attributes, $actor, $party): PartyAddress {
            $this->lock($party->id);
            $address->refresh();
            $until = isset($attributes['effective_from']) && is_string($attributes['effective_from'])
                ? $attributes['effective_from']
                : null;
            $this->closeUnlocked($address, $actor, $until);

            return $this->createUnlocked($party, $attributes, $actor, $address->id);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUnlocked(Party $party, array $attributes, User $actor, ?int $supersedesId): PartyAddress
    {
        $payload = $this->payload($attributes, $actor);
        $this->clearDefaults($party, $payload, null, $actor);

        $address = PartyAddress::query()->create([
            ...$payload,
            'party_id' => $party->id,
            'supersedes_address_id' => $supersedesId,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('party_address_created', $address, [], $address->auditSnapshot(), $actor);

        return $address;
    }

    private function closeUnlocked(PartyAddress $address, User $actor, ?string $effectiveUntil): PartyAddress
    {
        if (! $address->is_active) {
            throw ValidationException::withMessages([
                'address' => 'This address is already closed.',
            ]);
        }

        $old = $address->auditSnapshot();
        $until = $effectiveUntil ?? now()->timezone((string) config('app.timezone'))->toDateString();
        $from = $address->dateLabel('effective_from');

        if ($from !== null && $from > $until) {
            $until = $from;
        }

        $address->fill([
            'is_active' => false,
            'is_default_billing' => false,
            'is_default_shipping' => false,
            'effective_until' => $until,
            'updated_by' => $actor->id,
        ]);
        $address->save();
        $this->audit->record('party_address_closed', $address, $old, $address->auditSnapshot(), $actor);

        return $address;
    }

    /**
     * @param  array{is_default_billing: bool, is_default_shipping: bool}  $payload
     */
    private function clearDefaults(Party $party, array $payload, ?int $ignoreId, User $actor): void
    {
        if ($payload['is_default_billing']) {
            $this->clearFlag($party, 'is_default_billing', $ignoreId, $actor);
        }

        if ($payload['is_default_shipping']) {
            $this->clearFlag($party, 'is_default_shipping', $ignoreId, $actor);
        }
    }

    private function clearFlag(Party $party, string $flag, ?int $ignoreId, User $actor): void
    {
        $previous = PartyAddress::query()
            ->where('party_id', $party->id)
            ->where($flag, true)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        foreach ($previous as $address) {
            $old = $address->auditSnapshot();
            $address->fill([
                $flag => false,
                'updated_by' => $actor->id,
            ]);
            $address->save();
            $this->audit->record('party_address_updated', $address, $old, $address->auditSnapshot(), $actor);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     address_type: string,
     *     address_line_1: string,
     *     address_line_2: ?string,
     *     area: ?string,
     *     upazila: ?string,
     *     district: string,
     *     division: ?string,
     *     postcode: ?string,
     *     country: string,
     *     is_active: bool,
     *     effective_from: ?string,
     *     effective_until: ?string,
     *     is_default_billing: bool,
     *     is_default_shipping: bool,
     *     updated_by: int
     * }
     */
    private function payload(array $attributes, User $actor): array
    {
        $active = (bool) ($attributes['is_active'] ?? false);

        return [
            'address_type' => (string) ($attributes['address_type'] ?? ''),
            'address_line_1' => trim((string) ($attributes['address_line_1'] ?? '')),
            'address_line_2' => $this->nullableString($attributes['address_line_2'] ?? null),
            'area' => $this->nullableString($attributes['area'] ?? null),
            'upazila' => $this->nullableString($attributes['upazila'] ?? null),
            'district' => trim((string) ($attributes['district'] ?? '')),
            'division' => $this->nullableString($attributes['division'] ?? null),
            'postcode' => $this->nullableString($attributes['postcode'] ?? null),
            'country' => trim((string) ($attributes['country'] ?? 'Bangladesh')),
            'is_active' => $active,
            'effective_from' => $this->nullableString($attributes['effective_from'] ?? null),
            'effective_until' => $this->nullableString($attributes['effective_until'] ?? null),
            'is_default_billing' => $active && (bool) ($attributes['is_default_billing'] ?? false),
            'is_default_shipping' => $active && (bool) ($attributes['is_default_shipping'] ?? false),
            'updated_by' => (int) $actor->id,
        ];
    }

    private function guard(Party $party, User $actor): void
    {
        if (! $this->authorizer->canManageAddresses($actor, $party)) {
            throw new AuthorizationException;
        }
    }

    private function party(PartyAddress $address): Party
    {
        $party = $address->party;

        if (! $party instanceof Party) {
            throw new AuthorizationException;
        }

        return $party;
    }

    private function lock(int $partyId): void
    {
        Party::query()->whereKey($partyId)->lockForUpdate()->first();
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
