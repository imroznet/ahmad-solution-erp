<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait FreezesRecordedDeduction
{
    protected static function bootFreezesRecordedDeduction(): void
    {
        static::updating(function (Model $record): void {
            $original = $record->getOriginal('status');

            if (! is_string($original) || $original === 'draft') {
                return;
            }

            $allowed = ['status', 'updated_at', 'voided_at', 'voided_by', 'void_reason'];
            $forbidden = array_values(array_diff(array_keys($record->getDirty()), $allowed));

            if ($forbidden !== []) {
                throw new LogicException('A recorded deduction cannot change its content.');
            }
        });

        static::deleting(function (): bool {
            return false;
        });
    }
}
