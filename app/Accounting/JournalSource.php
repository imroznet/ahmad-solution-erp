<?php

namespace App\Accounting;

enum JournalSource: string
{
    case Manual = 'manual';
    case Opening = 'opening';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Opening => 'Opening balances',
            self::Reversal => 'Reversal',
        };
    }
}
