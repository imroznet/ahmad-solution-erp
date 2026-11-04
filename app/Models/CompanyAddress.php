<?php

namespace App\Models;

use App\Company\AddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_profile_id',
    'address_type',
    'line_1',
    'line_2',
    'area',
    'upazila',
    'district',
    'division',
    'postcode',
    'country',
    'is_active',
    'effective_from',
    'effective_until',
    'is_default_correspondence',
    'is_default_billing',
    'remarks',
    'supersedes_address_id',
    'created_by',
    'updated_by',
])]
class CompanyAddress extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default_correspondence' => 'boolean',
            'is_default_billing' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CompanyProfile, $this>
     */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /**
     * @return BelongsTo<CompanyAddress, $this>
     */
    public function supersededAddress(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_address_id');
    }

    public function typeLabel(): string
    {
        return AddressType::label($this->address_type);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->line_1,
            $this->line_2,
            $this->area,
            $this->upazila,
            $this->district,
            $this->division,
            $this->postcode,
            $this->country,
        ], fn (mixed $line): bool => is_string($line) && $line !== ''));
    }
}
