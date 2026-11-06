<?php

namespace App\Tax;

use Illuminate\Support\Carbon;

/**
 * Dates are calendar dates in Asia/Dhaka. effective_from and effective_to
 * are both inclusive. A null effective_to means the range has no end.
 * The next range may start on the following day. It may not start on the
 * same day the previous range still covers.
 */
final class TaxRuleCalendar
{
    public static function overlaps(string $fromA, ?string $toA, string $fromB, ?string $toB): bool
    {
        if ($toB !== null && $fromA > $toB) {
            return false;
        }

        if ($toA !== null && $fromB > $toA) {
            return false;
        }

        return true;
    }

    public static function contains(string $from, ?string $to, string $date): bool
    {
        return $date >= $from && ($to === null || $date <= $to);
    }

    public static function dayBefore(string $date): string
    {
        $parsed = Carbon::createFromFormat('!Y-m-d', $date, 'Asia/Dhaka');

        if (! $parsed instanceof Carbon) {
            throw new TaxRuleException('effective_from', 'Enter the date as YYYY-MM-DD.');
        }

        return $parsed->subDay()->toDateString();
    }

    public static function today(): string
    {
        return Carbon::now('Asia/Dhaka')->toDateString();
    }
}
