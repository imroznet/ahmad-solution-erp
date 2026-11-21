<?php

namespace App\Models;

use App\Accounting\BankAccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'account_id',
    'bank_name',
    'branch',
    'account_title',
    'account_number',
    'account_type',
    'opened_on',
])]
class BankAccount extends Model
{
    protected $casts = [
        'account_type' => BankAccountType::class,
        'opened_on' => 'date',
    ];

    public function openedOn(): string
    {
        $date = $this->opened_on;

        return $date instanceof Carbon ? $date->toDateString() : '';
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
