<?php

namespace App\Tax;

/**
 * A withholding record has a business direction. Neither direction is assumed.
 */
final class WithholdingDirection
{
    public const WITHHELD_BY_CUSTOMER = 'withheld_by_customer';

    public const DEDUCTED_FROM_SUPPLIER = 'deducted_from_supplier';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::WITHHELD_BY_CUSTOMER, self::DEDUCTED_FROM_SUPPLIER];
    }

    public static function label(string $direction): string
    {
        return match ($direction) {
            self::WITHHELD_BY_CUSTOMER => 'Withheld from Ahmad Solution Ltd. by a customer',
            self::DEDUCTED_FROM_SUPPLIER => 'Deducted by Ahmad Solution Ltd. from a supplier',
            default => $direction,
        };
    }
}
