<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\HidesSupersededLines;
use App\Models\Concerns\ReadsStoredValues;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'mushak_63_document_id',
    'commercial_invoice_line_id',
    'position',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'base_amount',
    'vat_rate_percent',
    'vat_amount',
    'inclusive_amount',
    'supplementary_duty_amount',
    'duties_amount',
    'calculation_driver',
    'rate_entered_by',
    'rate_entered_at',
])]
class Mushak63Line extends Model
{
    use HidesSupersededLines;
    use ReadsStoredValues;

    protected $table = 'mushak_63_lines';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (Mushak63Line $line): void {
            if ($line->getOriginal('superseded_at') !== null) {
                throw new LogicException('A superseded Mushak 6.3 line cannot be changed.');
            }

            $status = $line->document()->value('status');

            if (is_string($status) && $status !== DocumentStatus::DRAFT) {
                throw new LogicException('An issued Mushak 6.3 line cannot be changed.');
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
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'vat_rate_percent' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'inclusive_amount' => 'decimal:2',
            'supplementary_duty_amount' => 'decimal:2',
            'duties_amount' => 'decimal:2',
            'rate_entered_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Mushak63Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Mushak63Document::class, 'mushak_63_document_id');
    }

    /**
     * @return BelongsTo<CommercialInvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoiceLine::class, 'commercial_invoice_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rateEnteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rate_entered_by');
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (is_int($value) ? (string) $value : null);
    }

    public function quantityLabel(): string
    {
        return rtrim(rtrim($this->decimalAttribute('quantity'), '0'), '.');
    }

    public function money(string $key): ?Money
    {
        $amount = $this->nullableDecimal($key);

        return $amount === null ? null : Money::of($amount);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'position' => (int) $this->getAttribute('position'),
            'commercial_invoice_line_id' => $this->getAttribute('commercial_invoice_line_id'),
            'description' => $this->stringAttribute('description'),
            'unit' => $this->stringAttribute('unit'),
            'quantity' => $this->decimalAttribute('quantity'),
            'unit_price' => $this->decimalAttribute('unit_price'),
            'base_amount' => $this->decimalAttribute('base_amount'),
            'vat_rate_percent' => $this->nullableDecimal('vat_rate_percent'),
            'vat_amount' => $this->nullableDecimal('vat_amount'),
            'inclusive_amount' => $this->nullableDecimal('inclusive_amount'),
            'supplementary_duty_amount' => $this->nullableDecimal('supplementary_duty_amount'),
            'duties_amount' => $this->nullableDecimal('duties_amount'),
            'calculation_driver' => $this->stringAttribute('calculation_driver'),
            'rate_entered_by' => $this->getAttribute('rate_entered_by'),
            'rate_entered_at' => $this->dateTimeString('rate_entered_at'),
        ];
    }
}
