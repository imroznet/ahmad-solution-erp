<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Models\Concerns\RejectsHistoryChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mushak63_document_id',
    'commercial_invoice_line_id',
    'position',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'line_total',
])]
class Mushak63Line extends Model
{
    use ReadsStoredValues;
    use RejectsHistoryChanges;

    public $timestamps = false;

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
     * @return BelongsTo<Mushak63Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Mushak63Document::class, 'mushak63_document_id');
    }

    /**
     * @return BelongsTo<CommercialInvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoiceLine::class, 'commercial_invoice_line_id');
    }
}
