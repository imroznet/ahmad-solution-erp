<?php

namespace App\Quotations;

use App\Models\QuotationNumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Allocates QT-{year}-{sequence} numbers.
 *
 * The year is the calendar year in Asia/Dhaka. The sequence is stored in
 * quotation_number_sequences and incremented under a row lock, so two requests
 * cannot read the same last number. This is not an unlocked MAX()+1 query.
 * The caller must already be inside a transaction when the quotation row is
 * inserted, so a failed save rolls the number back. The unique index on
 * quotations.number is the second guard.
 */
final class QuotationNumberAllocator
{
    public function allocate(): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): string => $this->next());
        }

        return $this->next();
    }

    private function next(): string
    {
        $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
        $sequence = QuotationNumberSequence::query()->where('year', $year)->lockForUpdate()->first();

        if (! $sequence instanceof QuotationNumberSequence) {
            try {
                $sequence = new QuotationNumberSequence([
                    'year' => $year,
                    'last_number' => 0,
                ]);
                $sequence->save();
            } catch (UniqueConstraintViolationException) {
                $sequence = QuotationNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
            }
        }

        $lastNumber = (int) $sequence->last_number + 1;
        $sequence->last_number = $lastNumber;
        $sequence->save();

        return sprintf('%s-%d-%04d', $this->prefix(), $year, $lastNumber);
    }

    private function prefix(): string
    {
        $prefix = config('asl.quotations.number_prefix', 'QT');

        return is_string($prefix) && preg_match('/^[A-Z]{2,8}$/', $prefix) === 1 ? $prefix : 'QT';
    }
}
