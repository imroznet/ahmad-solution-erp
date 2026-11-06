<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Tax\TaxDocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

abstract class WithholdingRecord extends Model
{
    use ReadsStoredValues;

    abstract public function taxType(): string;

    protected static function booted(): void
    {
        static::updating(function (WithholdingRecord $record): void {
            if ($record->getOriginal('status') === TaxDocumentStatus::VOIDED) {
                throw new LogicException('A voided withholding record cannot be changed.');
            }

            if ($record->stringAttribute('status') === TaxDocumentStatus::ISSUED) {
                throw new LogicException('A withholding certificate cannot be issued while the legal gate is closed.');
            }
        });

        static::saving(function (WithholdingRecord $record): void {
            if ($record->stringAttribute('status') === TaxDocumentStatus::ISSUED) {
                throw new LogicException('A withholding certificate cannot be issued while the legal gate is closed.');
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
            'basis_amount' => 'decimal:2',
            'record_date' => 'date',
            'pinned_rule_snapshot' => 'array',
            'company_snapshot' => 'array',
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
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }

    /**
     * @return BelongsTo<TaxRuleVersion, $this>
     */
    public function pinnedRule(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'pinned_tax_rule_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dateString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
