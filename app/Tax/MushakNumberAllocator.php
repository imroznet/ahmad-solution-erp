<?php

namespace App\Tax;

use App\Models\MushakNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates M63-{Asia/Dhaka year}-{sequence} when a Mushak 6.3 is issued.
 * A draft has no number. A failed issue rolls the number back with the transaction.
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
        DB::table('mushak_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = MushakNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->save();

        $configured = config('asl.mushak.number_prefix', 'M63');
        $prefix = is_string($configured) && preg_match('/^[A-Z0-9]{2,8}$/', $configured) === 1 ? $configured : 'M63';

        return sprintf('%s-%d-%04d', $prefix, $year, $lastNumber);
    }
}
