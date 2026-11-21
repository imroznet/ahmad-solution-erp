<?php

namespace App\Accounting;

use App\Models\JournalNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates JE-{Asia/Dhaka year}-{sequence}.
 *
 * The year's row is created with INSERT IGNORE before the lock. The caller
 * must already be inside the journal transaction. A failed create rolls the
 * number back. Deleting a saved draft does not rewind the sequence.
 */
final class JournalNumberAllocator
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
        DB::table('journal_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = JournalNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->save();

        return sprintf('%s-%d-%04d', $this->prefix(), $year, $lastNumber);
    }

    private function prefix(): string
    {
        $configured = config('asl.journals.number_prefix', 'JE');

        return is_string($configured) && preg_match('/^[A-Z]{2,8}$/', $configured) === 1
            ? $configured
            : 'JE';
    }
}
