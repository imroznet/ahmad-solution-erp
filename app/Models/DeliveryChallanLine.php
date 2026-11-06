<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\HidesSupersededLines;
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
    use HidesSupersededLines;
    use ReadsStoredValues;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (DeliveryChallanLine $line): void {
            if ($line->getOriginal('superseded_at') !== null) {
                throw new LogicException('A superseded delivery challan line cannot be changed.');
            }

            $status = $line->challan()->value('status');

            if ($status !== DocumentStatus::DRAFT) {
                throw new LogicException('An issued delivery challan line cannot be changed.');
            }

            $forbidden = array_values(array_diff(array_keys($line->getDirty()), ['superseded_at', 'superseded_by']));

            if ($forbidden !== []) {
                throw new LogicException('A delivery challan line cannot be edited in place.');
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
            'accepted_quantity' => 'decimal:4',
            'superseded_at' => 'datetime',
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
