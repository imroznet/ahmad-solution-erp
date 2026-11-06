<?php

namespace App\Tax;

use Illuminate\Support\Facades\DB;

/**
 * Allocates M63-{year}-{sequence} when a Mushak 6.3 is issued.
 * The year is the calendar year in Asia/Dhaka. Drafts have no number.
 * The caller must already be inside the issue transaction.
 */
final class MushakNumberAllocator
{
    public function allocate(): string
    {
        $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
        DB::table('mushak_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = DB::table('mushak_number_sequences')->where('year', $year)->lockForUpdate()->first();

        if ($sequence === null) {
            throw new TaxRuleException('number', 'The Mushak number sequence could not be locked.');
        }

        $last = (int) $sequence->last_number + 1;
        DB::table('mushak_number_sequences')->where('year', $year)->update([
            'last_number' => $last,
            'updated_at' => now(),
        ]);

        $prefix = config('asl.mushak.number_prefix', 'M63');
        $prefix = is_string($prefix) && preg_match('/^[A-Z0-9]{2,8}$/', $prefix) === 1 ? $prefix : 'M63';

        return sprintf('%s-%d-%04d', $prefix, $year, $last);
    }
}
