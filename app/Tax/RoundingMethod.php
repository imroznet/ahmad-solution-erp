<?php

namespace App\Tax;

use Brick\Math\RoundingMode;

/**
 * Rounding methods the VAT calculator can apply.
 *
 * Each name is the behaviour, not a statutory NBR rule. The method is stored
 * on the document so a later change of the default cannot rewrite an issued
 * calculation.
 */
final class RoundingMethod
{
    public const HALF_UP = 'half_up';

    public const HALF_EVEN = 'half_even';

    public const TOWARD_ZERO = 'toward_zero';

    public const AWAY_FROM_ZERO = 'away_from_zero';

    public const TOWARD_POSITIVE = 'toward_positive';

    public const TOWARD_NEGATIVE = 'toward_negative';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::HALF_UP,
            self::HALF_EVEN,
            self::TOWARD_ZERO,
            self::AWAY_FROM_ZERO,
            self::TOWARD_POSITIVE,
            self::TOWARD_NEGATIVE,
        ];
    }

    public static function mode(string $method): RoundingMode
    {
        return match ($method) {
            self::HALF_UP => RoundingMode::HalfUp,
            self::HALF_EVEN => RoundingMode::HalfEven,
            self::TOWARD_ZERO => RoundingMode::Down,
            self::AWAY_FROM_ZERO => RoundingMode::Up,
            self::TOWARD_POSITIVE => RoundingMode::Ceiling,
            self::TOWARD_NEGATIVE => RoundingMode::Floor,
            default => throw new TaxRuleException('rounding_method', 'Choose a rounding method.'),
        };
    }

    public static function label(string $method): string
    {
        return match ($method) {
            self::HALF_UP => 'Half up (ties away from zero)',
            self::HALF_EVEN => 'Half even (ties to the even digit)',
            self::TOWARD_ZERO => 'Toward zero',
            self::AWAY_FROM_ZERO => 'Away from zero',
            self::TOWARD_POSITIVE => 'Toward positive infinity',
            self::TOWARD_NEGATIVE => 'Toward negative infinity',
            default => $method,
        };
    }

    public static function definition(string $method): string
    {
        return match ($method) {
            self::HALF_UP => 'Round to the nearest digit. A tie rounds away from zero.',
            self::HALF_EVEN => 'Round to the nearest digit. A tie rounds to the even digit.',
            self::TOWARD_ZERO => 'Drop the extra digits, toward zero.',
            self::AWAY_FROM_ZERO => 'Round away from zero whenever there is a non-zero extra digit.',
            self::TOWARD_POSITIVE => 'Round toward positive infinity.',
            self::TOWARD_NEGATIVE => 'Round toward negative infinity.',
            default => '',
        };
    }
}
