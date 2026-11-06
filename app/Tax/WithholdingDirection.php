<?php

namespace App\Tax;

/**
 * withheld_from_us: a customer deducted tax from Ahmad Solution Ltd.
 * deducted_by_us: Ahmad Solution Ltd. deducted tax from a supplier.
 */
final class WithholdingDirection
{
    public const WITHHELD_FROM_US = 'withheld_from_us';

    public const DEDUCTED_BY_US = 'deducted_by_us';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::WITHHELD_FROM_US => 'Withheld from us by a customer',
            self::DEDUCTED_BY_US => 'Deducted by us from a supplier',
        ];
    }

    public static function label(string $direction): string
    {
        return self::options()[$direction] ?? $direction;
    }

    public static function isKnown(string $direction): bool
    {
        return isset(self::options()[$direction]);
    }
}
