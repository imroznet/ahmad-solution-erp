<?php

namespace App\Tax;

final class RoundingRule
{
    /**
     * Half away from zero at two decimal places, through App\Support\Money.
     * This is the application's money rule. It is not an NBR rounding rule.
     */
    public const HALF_AWAY_FROM_ZERO = 'half_away_from_zero';

    public const NOT_APPLICABLE = 'not_applicable';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::HALF_AWAY_FROM_ZERO, self::NOT_APPLICABLE];
    }
}
