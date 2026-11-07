<?php

namespace App\Tax;

final class RateBasis
{
    public const PERCENTAGE = 'percentage';

    public const FIXED_AMOUNT = 'fixed_amount';

    public const EXEMPT = 'exempt';

    public const ZERO_RATED = 'zero_rated';

    public const NON_TAXABLE = 'non_taxable';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PERCENTAGE,
            self::FIXED_AMOUNT,
            self::EXEMPT,
            self::ZERO_RATED,
            self::NON_TAXABLE,
        ];
    }

    public static function label(string $basis): string
    {
        return match ($basis) {
            self::PERCENTAGE => 'Percentage',
            self::FIXED_AMOUNT => 'Fixed amount',
            self::EXEMPT => 'Exempt',
            self::ZERO_RATED => 'Zero-rated',
            self::NON_TAXABLE => 'Non-taxable',
            default => $basis,
        };
    }

    public static function isZeroTreatment(string $basis): bool
    {
        return in_array($basis, [self::EXEMPT, self::ZERO_RATED, self::NON_TAXABLE], true);
    }
}
