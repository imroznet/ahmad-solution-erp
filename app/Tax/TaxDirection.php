<?php

namespace App\Tax;

final class TaxDirection
{
    public const WITHHELD_FROM_US = 'withheld_from_us';

    public const DEDUCTED_BY_US = 'deducted_by_us';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::WITHHELD_FROM_US, self::DEDUCTED_BY_US];
    }

    public static function label(string $direction): string
    {
        return match ($direction) {
            self::WITHHELD_FROM_US => 'Withheld from Ahmad Solution Ltd.',
            self::DEDUCTED_BY_US => 'Deducted by Ahmad Solution Ltd.',
            default => $direction,
        };
    }
}
