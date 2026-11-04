<?php

namespace App\Models;

use App\Parties\PartyAddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'party_id',
    'address_type',
    'address_line_1',
    'address_line_2',
    'area',
    'upazila',
    'district',
    'division',
    'postcode',
    'country',
    'is_default_billing',
    'is_default_shipping',
    'is_active',
    'effective_from',
    'effective_until',
    'supersedes_address_id',
    'created_by',
    'updated_by',
])]
class PartyAddress extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<PartyAddress, $this>
     */
    public function supersededAddress(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_address_id');
    }

    public function typeLabel(): string
    {
        $type = $this->getAttribute('address_type');

        return PartyAddressType::label(is_string($type) ? $type : '');
    }

    public function dateLabel(string $attribute): ?string
    {
        $value = $this->getAttribute($attribute);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->text('address_line_1'),
            $this->text('address_line_2'),
            $this->text('area'),
            $this->text('upazila'),
            $this->text('district'),
            $this->text('division'),
            $this->text('postcode'),
            $this->text('country'),
        ], fn (?string $line): bool => $line !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'address_type' => $this->text('address_type'),
            'address_line_1' => $this->text('address_line_1'),
            'address_line_2' => $this->text('address_line_2'),
            'area' => $this->text('area'),
            'upazila' => $this->text('upazila'),
            'district' => $this->text('district'),
            'division' => $this->text('division'),
            'postcode' => $this->text('postcode'),
            'country' => $this->text('country'),
            'is_default_billing' => (bool) $this->is_default_billing,
            'is_default_shipping' => (bool) $this->is_default_shipping,
            'is_active' => (bool) $this->is_active,
            'effective_from' => $this->dateLabel('effective_from'),
            'effective_until' => $this->dateLabel('effective_until'),
            'supersedes_address_id' => $this->supersedes_address_id,
        ];
    }

    private function text(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
