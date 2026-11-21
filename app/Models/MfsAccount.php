<?php

namespace App\Models;

use App\Accounting\MfsProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'account_id',
    'provider',
    'mobile_number',
    'account_title',
    'opened_on',
])]
class MfsAccount extends Model
{
    protected $casts = [
        'provider' => MfsProvider::class,
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
