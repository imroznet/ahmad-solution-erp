<?php

namespace App\Models;

use App\Company\AddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'singleton_key',
    'legal_name',
    'trading_name',
    'logo_path',
    'rjsc_registration_number',
    'incorporation_date',
    'business_type',
    'tin',
    'bin',
    'trade_licence_number',
    'trade_licence_issuing_authority',
    'trade_licence_issued_on',
    'trade_licence_expires_on',
    'financial_year_starts_on',
    'financial_year_ends_on',
    'currency_code',
    'website',
    'email',
    'phone',
    'description',
    'updated_by',
])]
class CompanyProfile extends Model
{
    protected static function booted(): void
    {
        static::saving(function (CompanyProfile $profile): void {
            $email = $profile->getAttribute('email');

            if (is_string($email) && $email !== '') {
                $profile->email = Str::lower(trim($email));
            }

            $currency = $profile->getAttribute('currency_code');

            if (is_string($currency)) {
                $profile->currency_code = Str::upper(trim($currency));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'incorporation_date' => 'date',
            'trade_licence_issued_on' => 'date',
            'trade_licence_expires_on' => 'date',
            'financial_year_starts_on' => 'date',
            'financial_year_ends_on' => 'date',
        ];
    }

    /**
     * @return HasMany<CompanyAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    /**
     * @return HasMany<CompanyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function primary(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function tradeLicenceState(): ?string
    {
        $expires = $this->asDateValue('trade_licence_expires_on');

        if ($expires === null) {
            return null;
        }

        $today = now()->timezone((string) config('app.timezone'))->startOfDay();

        if ($expires->lt($today)) {
            return 'expired';
        }

        if ($expires->lte($today->copy()->addDays(60))) {
            return 'expiring';
        }

        return 'current';
    }

    private function asDateValue(string $attribute): ?Carbon
    {
        $value = $this->getAttribute($attribute);

        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->startOfDay();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->startOfDay();
        }

        return null;
    }

    public function registeredOffice(): ?CompanyAddress
    {
        return $this->activeAddress(AddressType::REGISTERED_OFFICE);
    }

    public function corporateOffice(): ?CompanyAddress
    {
        return $this->activeAddress(AddressType::CORPORATE_OFFICE);
    }

    private function activeAddress(string $type): ?CompanyAddress
    {
        $address = $this->addresses
            ->where('address_type', $type)
            ->where('is_active', true)
            ->sortByDesc('id')
            ->first();

        return $address instanceof CompanyAddress ? $address : null;
    }
}
