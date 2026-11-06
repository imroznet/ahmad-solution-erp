<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Current document lines are the rows that have not been superseded.
 * A draft edit keeps the previous row and marks it superseded.
 */
trait HidesSupersededLines
{
    public static function bootHidesSupersededLines(): void
    {
        static::addGlobalScope('current', function (Builder $query): void {
            $query->whereNull($query->getModel()->getTable().'.superseded_at');
        });
    }
}
