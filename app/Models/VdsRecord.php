<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Support\Money;
use App\Tax\WithholdingDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'direction',
    'status',
    'record_date',
    'party_id',
    'counterparty_name',
    'counterparty_bin',
    'counterparty_tin',
    'commercial_invoice_id',
    'mushak_63_document_id',
    'taxable_basis',
    'tax_amount',
    'certificate_reference',
    'payment_reference',
    'tax_rule_version_id',
    'tax_rule_snapshot',
    'notes',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class VdsRecord extends Model
{
    use ReadsStoredValues;

    /**
     * @var list<string>
     */
    public const MUTABLE_AFTER_RECORD = [
        'status',
        'updated_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (VdsRecord $record): void {
            $forbidden = array_values(array_diff(array_keys($record->getDirty()), self::MUTABLE_AFTER_RECORD));

            if ($forbidden !== []) {
                throw new LogicException('A VDS record cannot be edited. Void it and record a replacement.');
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
            'record_date' => 'date',
            'taxable_basis' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_rule_snapshot' => 'array',
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
     * @return BelongsTo<Mushak63Document, $this>
     */
    public function mushak(): BelongsTo
    {
        return $this->belongsTo(Mushak63Document::class, 'mushak_63_document_id');
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function directionLabel(): string
    {
        return WithholdingDirection::label($this->stringAttribute('direction'));
    }

    public function basisMoney(): Money
    {
        return Money::of($this->decimalAttribute('taxable_basis'));
    }

    public function taxMoney(): Money
    {
        return Money::of($this->decimalAttribute('tax_amount'));
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'direction' => $this->stringAttribute('direction'),
            'status' => $this->stringAttribute('status'),
            'record_date' => $this->dateString('record_date'),
            'party_id' => $this->getAttribute('party_id'),
            'counterparty_name' => $this->stringAttribute('counterparty_name'),
            'counterparty_bin' => $this->nullableAttribute('counterparty_bin'),
            'counterparty_tin' => $this->nullableAttribute('counterparty_tin'),
            'commercial_invoice_id' => $this->getAttribute('commercial_invoice_id'),
            'mushak_63_document_id' => $this->getAttribute('mushak_63_document_id'),
            'taxable_basis' => $this->decimalAttribute('taxable_basis'),
            'tax_amount' => $this->decimalAttribute('tax_amount'),
            'certificate_reference' => $this->nullableAttribute('certificate_reference'),
            'payment_reference' => $this->nullableAttribute('payment_reference'),
            'tax_rule_version_id' => $this->getAttribute('tax_rule_version_id'),
        ];
    }

    public function nullableAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
