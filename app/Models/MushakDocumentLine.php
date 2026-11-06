<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\HidesSupersededLines;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'mushak_document_id',
    'commercial_invoice_line_id',
    'quotation_line_id',
    'delivery_challan_line_id',
    'position',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'line_value',
    'supplementary_duty',
    'vat_rate',
    'specific_tax_note',
    'vat_amount',
    'inclusive_value',
    'vat_rate_entered_by',
    'vat_rate_entered_at',
    'superseded_at',
    'superseded_by',
    'created_at',
])]
class MushakDocumentLine extends Model
{
    use HidesSupersededLines;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (MushakDocumentLine $line): void {
            if ($line->getOriginal('superseded_at') !== null) {
                throw new LogicException('A superseded Mushak line cannot be changed.');
            }

            $status = $line->document()->value('status');

            if ($status !== DocumentStatus::DRAFT) {
                throw new LogicException('An issued Mushak line cannot be changed.');
            }

            $allowed = ['superseded_at', 'superseded_by'];
            $forbidden = array_values(array_diff(array_keys($line->getDirty()), $allowed));

            if ($forbidden !== []) {
                throw new LogicException('A Mushak line is replaced rather than edited.');
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
            'unit_price' => 'decimal:2',
            'line_value' => 'decimal:2',
            'supplementary_duty' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'inclusive_value' => 'decimal:2',
            'vat_rate_entered_at' => 'datetime',
            'superseded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MushakDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(MushakDocument::class, 'mushak_document_id');
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function text(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }
}
