<?php

namespace App\Tax;

/**
 * Inclusive effective dates for one tax-rule version.
 *
 * effective_to null means the version is open-ended. The end date is inclusive.
 * The next version may start on the following day. The same calendar day is an overlap.
 * Dates are compared as Y-m-d strings.
 */
final class TaxRuleWindow
{
    public function overlaps(string $fromA, ?string $toA, string $fromB, ?string $toB): bool
    {
        if ($toB !== null && $fromA > $toB) {
            return false;
        }

        if ($toA !== null && $fromB > $toA) {
            return false;
        }

        return true;
    }

    public function contains(string $from, ?string $to, string $on): bool
    {
        if ($on < $from) {
            return false;
        }

        return $to === null || $on <= $to;
    }
}
