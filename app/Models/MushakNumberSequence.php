<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MushakNumberSequence extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'year';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
