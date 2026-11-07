<?php

namespace App\Tax;

final class CalculationBasis
{
    public const EXCLUSIVE = 'exclusive';

    public const INCLUSIVE = 'inclusive';

    public const NOT_APPLICABLE = 'not_applicable';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::EXCLUSIVE, self::INCLUSIVE, self::NOT_APPLICABLE];
    }

    public static function label(string $basis): string
    {
        return match ($basis) {
            self::EXCLUSIVE => 'Tax exclusive',
            self::INCLUSIVE => 'Tax inclusive',
            self::NOT_APPLICABLE => 'Not applicable',
            default => $basis,
        };
    }
}
