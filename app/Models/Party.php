<?php

namespace App\Models;

use App\Parties\PartyKind;
use App\Parties\PartyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'legal_name',
    'trading_name',
    'legal_name_bn',
    'trading_name_bn',
    'party_kind',
    'registration_number',
    'tin',
    'bin',
    'vat_registration_number',
    'email',
    'phone',
    'website',
    'notes',
    'status',
    'currency_code',
    'created_by',
    'updated_by',
])]
class Party extends Model
{
    /**
     * @return HasMany<PartyRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class);
    }

    /**
     * @return HasMany<PartyContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class);
    }

    /**
     * @return HasOne<PartyContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(PartyContact::class)
            ->where('is_primary', true)
            ->where('is_active', true);
    }

    /**
     * @return HasMany<PartyAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(PartyAddress::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<Party>  $query
     */
    public function scopeWithListDistrict(Builder $query): void
    {
        $query->addSelect([
            'list_district' => PartyAddress::query()
                ->select('district')
                ->whereColumn('party_addresses.party_id', 'parties.id')
                ->where('is_active', true)
                ->orderByDesc('is_default_billing')
                ->orderByDesc('is_default_shipping')
                ->orderBy('id')
                ->limit(1),
        ]);
    }

    public function hasRole(string $role): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('role', $role);
    }

    public function isArchived(): bool
    {
        return $this->stringAttribute('status') === 'archived';
    }

    public function kindLabel(): string
    {
        return PartyKind::label($this->stringAttribute('party_kind'));
    }

    public function statusLabel(): string
    {
        return PartyStatus::label($this->stringAttribute('status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        $this->loadMissing('roles');

        return [
            'legal_name' => $this->stringAttribute('legal_name'),
            'trading_name' => $this->nullableAttribute('trading_name'),
            'legal_name_bn' => $this->nullableAttribute('legal_name_bn'),
            'trading_name_bn' => $this->nullableAttribute('trading_name_bn'),
            'party_kind' => $this->stringAttribute('party_kind'),
            'registration_number' => $this->nullableAttribute('registration_number'),
            'tin' => $this->nullableAttribute('tin'),
            'bin' => $this->nullableAttribute('bin'),
            'vat_registration_number' => $this->nullableAttribute('vat_registration_number'),
            'email' => $this->nullableAttribute('email'),
            'phone' => $this->nullableAttribute('phone'),
            'website' => $this->nullableAttribute('website'),
            'notes' => $this->nullableAttribute('notes'),
            'status' => $this->stringAttribute('status'),
            'currency_code' => $this->stringAttribute('currency_code'),
            'roles' => $this->roles->pluck('role')->sort()->values()->all(),
        ];
    }

    public function stringAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    public function nullableAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
