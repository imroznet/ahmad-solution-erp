<?php

namespace App\Models;

use App\Accounting\AccountingException;
use App\Accounting\JournalStatus;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'journal_id',
    'account_id',
    'party_id',
    'debit',
    'credit',
    'memo',
    'position',
])]
class JournalLine extends Model
{
    use ReadsStoredValues;

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (JournalLine $line): void {
            $line->guardPosted();
        });

        static::deleting(function (JournalLine $line): void {
            $line->guardPosted();
        });
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    private function guardPosted(): void
    {
        $journal = $this->journal;

        if ($journal instanceof Journal && $journal->status === JournalStatus::Posted) {
            throw new AccountingException('A posted journal cannot be changed.');
        }
    }
}
