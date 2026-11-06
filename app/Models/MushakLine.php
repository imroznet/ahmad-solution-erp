<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\HidesSupersededLines;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'mushak_invoice_id',
    'commercial_invoice_line_id',
    'position',
    'description',
    'unit',
    'quantity',
    'base_amount',
    'vat_rate',
    'vat_amount',
    'adjustment_amount',
    'adjustment_reason',
    'final_amount',
    'residual_amount',
    'calculation_origin',
    'rate_entered_by',
    'rate_entered_at',
    'adjustment_updated_by',
    'adjustment_updated_at',
    'superseded_at',
    'superseded_by',
])]
class MushakLine extends Model
{
    use HidesSupersededLines;
    use ReadsStoredValues;

    protected static function booted(): void
    {
        static::updating(function (MushakLine $line): void {
            if ($line->getOriginal('superseded_at') !== null) {
                throw new LogicException('A superseded Mushak line cannot be changed.');
            }

            $status = $line->invoice()->value('status');

            if ($status !== DocumentStatus::DRAFT) {
                throw new LogicException('An issued Mushak line cannot be changed.');
            }

            $allowed = ['superseded_at', 'superseded_by', 'updated_at'];
            $forbidden = array_values(array_diff(array_keys($line->getDirty()), $allowed));

            if ($forbidden !== []) {
                throw new LogicException('A Mushak line is replaced rather than edited in place.');
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
            'base_amount' => 'decimal:4',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'adjustment_amount' => 'decimal:4',
            'final_amount' => 'decimal:4',
            'residual_amount' => 'decimal:4',
            'rate_entered_at' => 'datetime',
            'adjustment_updated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MushakInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MushakInvoice::class, 'mushak_invoice_id');
    }

    /**
     * @return BelongsTo<CommercialInvoiceLine, $this>
     */
    public function commercialLine(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoiceLine::class, 'commercial_invoice_line_id');
    }

    public function rateValue(): ?string
    {
        $value = $this->getAttribute('vat_rate');

        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimalAttribute('vat_rate');
    }
}
