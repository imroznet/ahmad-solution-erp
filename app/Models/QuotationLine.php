<?php

namespace App\Models;

use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_revision_id',
    'position',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'line_total',
])]
class QuotationLine extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (QuotationLine $line): bool {
            $status = $line->revision()->value('status');

            return $status === QuotationStatus::DRAFT;
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
     * @return BelongsTo<QuotationRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
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

    public function decimalAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return '0.00';
    }
}
