<?php

namespace App\Tax;

/**
 * Who withheld the amount. Neither direction is assumed for a customer receipt
 * or a supplier payment.
 */
final class WithholdingDirection
{
    public const WITHHELD_FROM_US = 'withheld_from_us';

    public const DEDUCTED_BY_US = 'deducted_by_us';

    public static function label(string $direction): string
    {
        return match ($direction) {
            self::WITHHELD_FROM_US => 'Withheld from Ahmad Solution Ltd.',
            self::DEDUCTED_BY_US => 'Deducted by Ahmad Solution Ltd.',
            default => $direction,
        };
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::WITHHELD_FROM_US, self::DEDUCTED_BY_US];
    }
}
