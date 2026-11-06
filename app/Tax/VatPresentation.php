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
}
