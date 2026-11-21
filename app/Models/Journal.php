<?php

namespace App\Models;

use App\Accounting\AccountingException;
use App\Accounting\JournalSource;
use App\Accounting\JournalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable([
    'number',
    'status',
    'journal_date',
    'financial_year_starts_on',
    'financial_year_ends_on',
    'memo',
    'source',
    'source_type',
    'source_id',
    'reverses_journal_id',
    'posted_at',
    'posted_by',
    'created_by',
])]
class Journal extends Model
{
    protected $table = 'journal_entries';

    protected $casts = [
        'status' => JournalStatus::class,
        'source' => JournalSource::class,
        'journal_date' => 'date',
        'financial_year_starts_on' => 'date',
        'financial_year_ends_on' => 'date',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (Journal $journal): void {
            if ($journal->getRawOriginal('status') === JournalStatus::Posted->value) {
                throw new AccountingException('A posted journal cannot be changed.');
            }
        });

        static::deleting(function (Journal $journal): void {
            if ($journal->status === JournalStatus::Posted) {
                throw new AccountingException('A posted journal cannot be deleted.');
            }
        });
    }

    public function journalDateString(): string
    {
        $date = $this->journal_date;

        return $date instanceof Carbon ? $date->toDateString() : '';
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasOne<Journal, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_journal_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_id');
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
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
