<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['year', 'last_number'])]
class ReceiptNumberSequence extends Model
{
    public const CREATED_AT = null;

    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];
}
