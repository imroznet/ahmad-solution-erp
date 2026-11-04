<?php

namespace App\Company;

use Illuminate\Support\Carbon;

/**
 * A July–June suggestion for the empty profile form.
 * Bangladesh companies often use that period. It is not stored until someone saves it,
 * and the form accepts a different period.
 */
final class SuggestedFinancialYear
{
    /**
     * @return array{starts: string, ends: string}
     */
    public static function julyToJune(?Carbon $today = null): array
    {
        $today ??= now()->timezone((string) config('app.timezone'));
        $startYear = $today->month >= 7 ? $today->year : $today->year - 1;

        return [
            'starts' => sprintf('%d-07-01', $startYear),
            'ends' => sprintf('%d-06-30', $startYear + 1),
        ];
    }
}
