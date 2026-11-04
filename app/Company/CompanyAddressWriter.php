<?php

namespace App\Company;

use App\Models\CompanyAddress;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Address changes keep the previous row. Closing a row does not delete it.
 * Registered office and corporate office are never copied from each other.
 */
final class CompanyAddressWriter
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(CompanyProfile $profile, array $attributes, User $actor): CompanyAddress
    {
        return DB::transaction(function () use ($profile, $attributes, $actor): CompanyAddress {
            $this->lock($profile->id);

            return $this->createUnlocked($profile, $attributes, $actor, null);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompanyAddress $address, array $attributes, User $actor): CompanyAddress
    {
        return DB::transaction(function () use ($address, $attributes, $actor): CompanyAddress {
            $this->lock($address->company_profile_id);
            $address->refresh();
            $payload = $this->payload($attributes, $actor);
            $this->assertExclusive($address->company_profile_id, $payload['address_type'], $payload['is_active'], $address->id);
            $this->clearDefaults($address->company_profile_id, $payload, $address->id);
            $address->fill($payload);
            $address->save();

            return $address;
        });
    }

    public function close(CompanyAddress $address, User $actor, ?string $effectiveUntil = null): CompanyAddress
    {
        return DB::transaction(function () use ($address, $actor, $effectiveUntil): CompanyAddress {
            $this->lock($address->company_profile_id);
            $address->refresh();

            return $this->closeUnlocked($address, $actor, $effectiveUntil);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function supersede(CompanyAddress $address, array $attributes, User $actor): CompanyAddress
    {
        return DB::transaction(function () use ($address, $attributes, $actor): CompanyAddress {
            $this->lock($address->company_profile_id);
            $address->refresh();
            $profile = CompanyProfile::query()->findOrFail($address->company_profile_id);
            $until = isset($attributes['effective_from']) && is_string($attributes['effective_from'])
                ? $attributes['effective_from']
                : null;
            $this->closeUnlocked($address, $actor, $until);

            return $this->createUnlocked($profile, $attributes, $actor, $address->id);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUnlocked(CompanyProfile $profile, array $attributes, User $actor, ?int $supersedesId): CompanyAddress
    {
        $payload = $this->payload($attributes, $actor);
        $this->assertExclusive($profile->id, $payload['address_type'], $payload['is_active'], null);
        $this->clearDefaults($profile->id, $payload, null);

        return CompanyAddress::query()->create([
            ...$payload,
            'company_profile_id' => $profile->id,
            'supersedes_address_id' => $supersedesId,
            'created_by' => $actor->id,
        ]);
    }

    private function closeUnlocked(CompanyAddress $address, User $actor, ?string $effectiveUntil): CompanyAddress
    {
        if (! $address->is_active) {
            throw ValidationException::withMessages([
                'address' => 'This address is already closed.',
            ]);
        }

        $until = $effectiveUntil ?? now()->timezone((string) config('app.timezone'))->toDateString();

        $currentUntil = $address->getAttribute('effective_from');
        $currentUntilDate = $currentUntil instanceof \DateTimeInterface
            ? $currentUntil->format('Y-m-d')
            : (is_string($currentUntil) && $currentUntil !== '' ? substr($currentUntil, 0, 10) : null);

        if ($currentUntilDate !== null && $currentUntilDate > $until) {
            $until = $currentUntilDate;
        }

        $address->fill([
            'is_active' => false,
            'is_default_correspondence' => false,
            'is_default_billing' => false,
            'effective_until' => $until,
            'updated_by' => $actor->id,
        ]);
        $address->save();

        return $address;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     address_type: string,
     *     line_1: string,
     *     line_2: ?string,
     *     area: ?string,
     *     upazila: ?string,
     *     district: string,
     *     division: ?string,
     *     postcode: ?string,
     *     country: string,
     *     is_active: bool,
     *     effective_from: ?string,
     *     effective_until: ?string,
     *     is_default_correspondence: bool,
     *     is_default_billing: bool,
     *     remarks: ?string,
     *     updated_by: int
     * }
     */
    private function payload(array $attributes, User $actor): array
    {
        $active = (bool) ($attributes['is_active'] ?? false);

        return [
            'address_type' => (string) $attributes['address_type'],
            'line_1' => (string) $attributes['line_1'],
            'line_2' => $this->nullableString($attributes['line_2'] ?? null),
            'area' => $this->nullableString($attributes['area'] ?? null),
            'upazila' => $this->nullableString($attributes['upazila'] ?? null),
            'district' => (string) $attributes['district'],
            'division' => $this->nullableString($attributes['division'] ?? null),
            'postcode' => $this->nullableString($attributes['postcode'] ?? null),
            'country' => (string) $attributes['country'],
            'is_active' => $active,
            'effective_from' => $this->nullableString($attributes['effective_from'] ?? null),
            'effective_until' => $this->nullableString($attributes['effective_until'] ?? null),
            'is_default_correspondence' => $active && (bool) ($attributes['is_default_correspondence'] ?? false),
            'is_default_billing' => $active && (bool) ($attributes['is_default_billing'] ?? false),
            'remarks' => $this->nullableString($attributes['remarks'] ?? null),
            'updated_by' => (int) $actor->id,
        ];
    }

    private function assertExclusive(int $profileId, string $type, bool $active, ?int $ignoreId): void
    {
        if (! $active || ! in_array($type, AddressType::exclusiveActive(), true)) {
            return;
        }

        $exists = CompanyAddress::query()
            ->where('company_profile_id', $profileId)
            ->where('address_type', $type)
            ->where('is_active', true)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'address_type' => 'An active '.AddressType::label($type).' already exists. Close it, or replace it, before saving another active address of that type.',
            ]);
        }
    }

    /**
     * @param  array{is_default_correspondence: bool, is_default_billing: bool}  $payload
     */
    private function clearDefaults(int $profileId, array $payload, ?int $ignoreId): void
    {
        if ($payload['is_default_correspondence']) {
            CompanyAddress::query()
                ->where('company_profile_id', $profileId)
                ->where('is_default_correspondence', true)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->update(['is_default_correspondence' => false]);
        }

        if ($payload['is_default_billing']) {
            CompanyAddress::query()
                ->where('company_profile_id', $profileId)
                ->where('is_default_billing', true)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->update(['is_default_billing' => false]);
        }
    }

    private function lock(int $profileId): void
    {
        CompanyProfile::query()->whereKey($profileId)->lockForUpdate()->first();
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
