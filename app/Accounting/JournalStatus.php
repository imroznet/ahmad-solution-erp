<?php

namespace App\Accounting;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
        };
    }
}
