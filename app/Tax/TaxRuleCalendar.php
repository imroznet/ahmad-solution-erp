<?php

namespace App\Tax;

use Carbon\CarbonImmutable;

/**
 * A rule applies on D when effective_from <= D and (effective_to is null or D <= effective_to).
 * Both ends are inclusive. The next version starts on the following day.
 */
final class TaxRuleCalendar
{
    public function overlaps(string $start, ?string $end, string $otherStart, ?string $otherEnd): bool
    {
        $leftEnd = $end ?? '9999-12-31';
        $rightEnd = $otherEnd ?? '9999-12-31';

        return $start <= $rightEnd && $otherStart <= $leftEnd;
    }

    public function dayBefore(string $date): string
    {
        return CarbonImmutable::parse($date)->subDay()->toDateString();
    }

    public function contains(string $start, ?string $end, string $day): bool
    {
        return $start <= $day && ($end === null || $day <= $end);
    }
}
