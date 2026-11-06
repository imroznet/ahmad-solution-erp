<?php

namespace App\Tax;

/**
 * Effective dates are inclusive on both ends. An empty effective_to means
 * the rule is open-ended. The next day after an end date does not overlap.
 * A blank supply classification is its own scope. It is not a wildcard.
 */
final class TaxRuleWindow
{
    public static function normalizeClassification(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? '' : mb_strtolower($trimmed);
    }

    public static function overlaps(string $fromA, ?string $toA, string $fromB, ?string $toB): bool
    {
        $endA = $toA ?? '9999-12-31';
        $endB = $toB ?? '9999-12-31';

        return $fromA <= $endB && $fromB <= $endA;
    }
}
