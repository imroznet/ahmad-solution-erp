<?php

namespace App\Accounting;

enum JournalSource: string
{
    case Manual = 'manual';
    case Opening = 'opening';
    case Reversal = 'reversal';
    case Invoice = 'invoice';
    case Mushak = 'mushak';
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Opening => 'Opening balances',
            self::Reversal => 'Reversal',
            self::Invoice => 'Commercial invoice',
            self::Mushak => 'Mushak 6.3',
            self::Receipt => 'Customer receipt',
        };
    }
}
