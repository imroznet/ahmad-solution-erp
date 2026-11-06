<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mushak63NumberSequence extends Model
{
    protected $table = 'mushak_63_number_sequences';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'year';

    protected $fillable = [
        'year',
        'last_number',
        'updated_at',
    ];
}
