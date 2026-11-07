<?php

namespace App\Tax;

use App\Support\Decimal;
use Brick\Math\RoundingMode;

/**
 * Poisha presentation for Mushak 6.3 money columns.
 * This is display rounding only. It does not replace the stored calculation.
 */
final class VatPresentation
{
    public static function poisha(string $amount): string
    {
        return (string) Decimal::of($amount)->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * A typed percentage for display, without the stored scale's trailing
     * zeros: 15.0000 becomes 15, 7.5000 becomes 7.5. The stored value is
     * unchanged; this only affects what the PDF prints.
     */
    public static function rate(string $rate): string
    {
        $trimmed = rtrim(rtrim($rate, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }
}
