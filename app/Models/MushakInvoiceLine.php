<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'mushak_invoice_id',
    'commercial_invoice_line_id',
    'quotation_line_id',
    'delivery_challan_line_id',
    'position',
    'description',
    'source_description',
    'unit',
    'quantity',
    'source_unit_price',
    'source_line_total',
    'base_amount',
    'vat_rate_percent',
    'vat_amount',
    'forward_vat_amount',
    'residual_applied',
    'adjustment_amount',
    'adjustment_reason',
    'adjustment_by',
    'adjustment_at',
    'supplementary_duty_amount',
    'final_amount',
    'calculation_snapshot',
    'rate_entered_by',
    'rate_entered_at',
])]
class MushakInvoiceLine extends Model
{
    use ReadsStoredValues;

    protected static function booted(): void
    {
        static::updating(function (MushakInvoiceLine $line): void {
            $status = $line->mushakInvoice()->value('status');

            if ($status !== null && $status !== 'draft') {
                throw new LogicException('A line on an issued Mushak 6.3 cannot be changed.');
            }
        });

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
            'position' => 'integer',
            'quantity' => 'decimal:4',
            'source_unit_price' => 'decimal:2',
            'source_line_total' => 'decimal:2',
            'base_amount' => 'decimal:4',
            'vat_rate_percent' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'forward_vat_amount' => 'decimal:4',
            'residual_applied' => 'boolean',
            'adjustment_amount' => 'decimal:4',
            'adjustment_at' => 'datetime',
            'supplementary_duty_amount' => 'decimal:4',
            'final_amount' => 'decimal:4',
            'calculation_snapshot' => 'array',
            'rate_entered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MushakInvoice, $this>
     */
    public function mushakInvoice(): BelongsTo
    {
        return $this->belongsTo(MushakInvoice::class);
    }
}
