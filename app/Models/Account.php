<?php

namespace App\Models;

use App\Accounting\AccountingException;
use App\Accounting\AccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code',
    'name',
    'type',
    'parent_id',
    'system_key',
    'is_active',
    'created_by',
])]
class Account extends Model
{
    protected $table = 'chart_of_accounts';

    protected $casts = [
        'type' => AccountType::class,
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (Account $account): void {
            $systemKey = $account->getRawOriginal('system_key');

            if (! is_string($systemKey) || $systemKey === '') {
                return;
            }

            foreach (['code', 'name', 'type', 'parent_id', 'system_key', 'is_active'] as $key) {
                if ($account->isDirty($key)) {
                    throw new AccountingException('A system account cannot be renamed, retyped, or deactivated.');
                }
            }
        });

        static::deleting(function (Account $account): void {
            if ($account->isSystem()) {
                throw new AccountingException('A system account cannot be deleted.');
            }

            if ($account->lines()->exists()) {
                throw new AccountingException('An account with a posting cannot be deleted. Deactivate it instead.');
            }
        });
    }

    public function isSystem(): bool
    {
        return is_string($this->system_key) && $this->system_key !== '';
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasOne<BankAccount, $this>
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class);
    }

    /**
     * @return HasOne<MfsAccount, $this>
     */
    public function mfsAccount(): HasOne
    {
        return $this->hasOne(MfsAccount::class);
    }

    /**
     * Cash in Hand plus bank and MFS accounts that can still take a new posting.
     * A later receipts and payments screen chooses from this list.
     *
     * @param  Builder<Account>  $query
     * @return Builder<Account>
     */
    public function scopeForSettlement(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $inner): void {
            $inner->where('system_key', 'cash')
                ->orWhereHas('bankAccount')
                ->orWhereHas('mfsAccount');
        })->orderBy('code');
    }
}
