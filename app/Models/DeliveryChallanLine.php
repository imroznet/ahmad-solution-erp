<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use App\Quotations\QuotationCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'delivery_challan_id',
    'quotation_line_id',
    'position',
    'description',
    'unit',
    'quantity',
    'accepted_quantity',
])]
class DeliveryChallanLine extends Model
{
    use ReadsStoredValues;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (DeliveryChallanLine $line): void {
            $status = $line->challan()->value('status');

            if ($status !== DocumentStatus::DRAFT) {
                throw new LogicException('An issued delivery challan line cannot be changed.');
            }
        });

        static::deleting(function (DeliveryChallanLine $line): bool {
            return $line->challan()->value('status') === DocumentStatus::DRAFT;
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
            'accepted_quantity' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<DeliveryChallan, $this>
     */
    public function challan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
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
}
