<?php

namespace App\Tax;

use Brick\Math\RoundingMode;

/**
 * Rounding methods for the user-selected VAT calculation stage.
 *
 * Half up ties away from zero. Half even ties to the even digit.
 * Toward zero drops the extra fraction. Ceiling moves toward positive
 * infinity. Floor moves toward negative infinity. These names are
 * calculation choices. They are not an NBR rounding certificate.
 */
final class VatRounding
{
    public const HALF_UP = 'half_up';

    public const HALF_EVEN = 'half_even';

    public const TOWARD_ZERO = 'toward_zero';

    public const CEILING = 'ceiling';

    public const FLOOR = 'floor';

    /**
     * @var list<string>
     */
    public const ALL = [
        self::HALF_UP,
        self::HALF_EVEN,
        self::TOWARD_ZERO,
        self::CEILING,
        self::FLOOR,
    ];

    public static function mode(string $method): RoundingMode
    {
        return match ($method) {
            self::HALF_UP => RoundingMode::HalfUp,
            self::HALF_EVEN => RoundingMode::HalfEven,
            self::TOWARD_ZERO => RoundingMode::Down,
            self::CEILING => RoundingMode::Ceiling,
            self::FLOOR => RoundingMode::Floor,
            default => throw new TaxRuleException('rounding_method', 'Choose a rounding method.'),
        };
    }

    public static function label(string $method): string
    {
        return match ($method) {
            self::HALF_UP => 'Half up (ties away from zero)',
            self::HALF_EVEN => 'Half even (ties to even)',
            self::TOWARD_ZERO => 'Toward zero',
            self::CEILING => 'Ceiling (toward positive infinity)',
            self::FLOOR => 'Floor (toward negative infinity)',
            default => $method,
        };
    }
}
