<?php

namespace App\Tax;

use App\Models\Mushak63NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates M63-{Asia/Dhaka year}-{sequence} when a Mushak 6.3 is issued.
 * A draft has no number. A failed issue rolls the number back with the transaction.
 * The format is the company's series. It is not claimed as an NBR-prescribed format.
 */
final class MushakNumberAllocator
{
    public function allocate(): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): string => $this->next(), 5);
        }

        return $this->next();
    }

    private function next(): string
    {
        $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
        DB::table('mushak_63_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = Mushak63NumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->setAttribute('updated_at', now());
        $sequence->save();
        $prefix = config('asl.mushak.number_prefix', 'M63');
        $prefix = is_string($prefix) && $prefix !== '' ? $prefix : 'M63';

        return sprintf('%s-%d-%04d', $prefix, $year, $lastNumber);
    }
}
