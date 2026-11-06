<?php

namespace App\Tax;

/**
 * A threshold is applied only when the rule records a treatment.
 * The application does not choose a statutory threshold by itself.
 */
final class ThresholdTreatment
{
    public const NOT_APPLICABLE = 'not_applicable';

    /**
     * A base strictly below the threshold gives zero. A base equal to or
     * above the threshold is taxed on the full base, not only the excess.
     */
    public const BELOW_IS_ZERO_FULL_BASE = 'below_is_zero_full_base';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::NOT_APPLICABLE, self::BELOW_IS_ZERO_FULL_BASE];
    }
}
