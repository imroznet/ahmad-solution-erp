<?php

namespace App\Models\Concerns;

use LogicException;

trait RejectsHistoryChanges
{
    public static function bootRejectsHistoryChanges(): void
    {
        static::updating(function (): void {
            throw new LogicException('This history row cannot be changed.');
        });

        static::deleting(function (): bool {
            return false;
        });
    }
}
