<?php

namespace App\Accounting;

enum ReceiptStatus: string
{
    case Recorded = 'recorded';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Void => 'Void',
        };
    }
}
