<?php

namespace App\Models\Concerns;

use App\Models\CommercialInvoice;
use App\Models\MushakDocument;
use App\Models\Party;
use App\Models\TaxRule;
use App\Models\User;
use App\Tax\TaxReviewStatus;
use App\Tax\WithholdingDirection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Shared columns for a VDS record and an AIT record.
 * The two tables stay separate so one cannot be added to the other.
 */
trait WithholdingRecord
{
    protected static function bootWithholdingRecord(): void
    {
        static::updating(function (self $record): void {
            $original = $record->getOriginal('status');

            if ($original !== 'recorded') {
                return;
            }

            $allowed = ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_at', 'updated_by'];
            $forbidden = array_values(array_diff(array_keys($record->getDirty()), $allowed));

            if ($forbidden !== []) {
                throw new LogicException('A recorded withholding entry cannot be edited. Void it and record another.');
            }
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function withholdingCasts(): array
    {
        return [
            'basis_amount' => 'decimal:2',
            'deducted_amount' => 'decimal:2',
            'record_date' => 'date',
            'rule_snapshot' => 'array',
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
     * @return BelongsTo<MushakDocument, $this>
     */
    public function mushakDocument(): BelongsTo
    {
        return $this->belongsTo(MushakDocument::class);
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }

    /**
     * @return BelongsTo<TaxRule, $this>
     */
    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function directionLabel(): string
    {
        return WithholdingDirection::label($this->stringAttribute('direction'));
    }

    public function reviewLabel(): string
    {
        return TaxReviewStatus::label($this->stringAttribute('tax_review_status'));
    }

    public function stringAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    public function nullableDecimal(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
