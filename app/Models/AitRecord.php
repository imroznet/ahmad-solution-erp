<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'direction',
    'party_id',
    'party_name',
    'party_bin',
    'party_tin',
    'commercial_invoice_id',
    'mushak_invoice_id',
    'tax_rule_version_id',
    'supply_description',
    'taxable_basis',
    'amount',
    'record_date',
    'certificate_reference',
    'payment_reference',
    'source_note',
    'internal_notes',
    'certificate_status',
    'created_by',
    'updated_by',
])]
class AitRecord extends Model
{
    use ReadsStoredValues;

    protected static function booted(): void
    {
        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taxable_basis' => 'decimal:4',
            'amount' => 'decimal:4',
            'record_date' => 'date',
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
     * @return BelongsTo<MushakInvoice, $this>
     */
    public function mushakInvoice(): BelongsTo
    {
        return $this->belongsTo(MushakInvoice::class);
    }

    /**
     * @return BelongsTo<TaxRuleVersion, $this>
     */
    public function taxRuleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
