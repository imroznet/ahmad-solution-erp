<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use App\Quotations\QuotationCalculator;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'commercial_invoice_id',
    'delivery_challan_line_id',
    'quotation_line_id',
    'position',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'line_total',
])]
class CommercialInvoiceLine extends Model
{
    use ReadsStoredValues;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (CommercialInvoiceLine $line): void {
            $status = $line->invoice()->value('status');

            if ($status !== DocumentStatus::DRAFT) {
                throw new LogicException('An issued commercial invoice line cannot be changed.');
            }
        });

        static::deleting(function (CommercialInvoiceLine $line): bool {
            return $line->invoice()->value('status') === DocumentStatus::DRAFT;
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
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }

    /**
     * @return BelongsTo<DeliveryChallanLine, $this>
     */
    public function challanLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallanLine::class, 'delivery_challan_line_id');
    }

    /**
     * @return BelongsTo<QuotationLine, $this>
     */
    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class);
    }

    public function quantityLabel(): string
    {
        return QuotationCalculator::formatQuantity($this->decimalAttribute('quantity'));
    }

    public function unitPriceMoney(): Money
    {
        return Money::of($this->decimalAttribute('unit_price'));
    }

    public function lineTotalMoney(): Money
    {
        return Money::of($this->decimalAttribute('line_total'));
    }
}
