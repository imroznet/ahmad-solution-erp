<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'starts_on',
    'ends_on',
])]
class FinancialYear extends Model
{
    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'closed_at' => 'datetime',
    ];

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function startsOn(): string
    {
        $date = $this->starts_on;

        return $date instanceof Carbon ? $date->toDateString() : '';
    }

    public function endsOn(): string
    {
        $date = $this->ends_on;

        return $date instanceof Carbon ? $date->toDateString() : '';
    }
}
