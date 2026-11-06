<?php

namespace App\Models;

use App\Models\Concerns\FreezesRecordedDeduction;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'direction',
    'status',
    'party_id',
    'counterparty_name',
    'counterparty_bin',
    'counterparty_tin',
    'commercial_invoice_id',
    'mushak_invoice_id',
    'source_reference',
    'taxable_basis',
    'deducted_amount',
    'record_date',
    'certificate_reference',
    'treasury_reference',
    'tax_rule_id',
    'rule_snapshot',
    'certificate_form',
    'review_status',
    'notes',
    'internal_notes',
    'recorded_at',
    'recorded_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class VdsRecord extends Model
{
    use FreezesRecordedDeduction;
    use ReadsStoredValues;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taxable_basis' => 'decimal:4',
            'deducted_amount' => 'decimal:4',
            'record_date' => 'date',
            'rule_snapshot' => 'array',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<TaxRule, $this>
     */
    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    /**
     * @return BelongsTo<MushakInvoice, $this>
     */
    public function mushakInvoice(): BelongsTo
    {
        return $this->belongsTo(MushakInvoice::class);
    }

    public function directionLabel(): string
    {
        return $this->stringAttribute('direction') === 'deducted_by_us'
            ? 'Deducted by Ahmad Solution Ltd.'
            : 'Withheld from Ahmad Solution Ltd.';
    }
}
