<?php

namespace App\Quotations;

use App\Models\QuotationNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates QT-{year}-{sequence} numbers.
 *
 * The year is the calendar year in Asia/Dhaka. The year's row is created with
 * INSERT IGNORE before anyone locks it, including the first quotation of a
 * new year. Two connections therefore do not take gap locks on a missing row
 * and then deadlock on the insert. The increment uses FOR UPDATE. This is not
 * an unlocked MAX()+1 query.
 *
 * The caller must already be inside the quotation-create transaction. A failed
 * create rolls the insert and the increment back, so the number is not
 * consumed. The unique index on quotations.number is the second guard. A
 * deadlock or lock-wait timeout is retried by that outer transaction.
 *
 * ASL_QUOTATION_ALLOCATION_DELAY may be 1, 2, or 3. The allocator then sleeps
 * that many seconds after the insert and before the row lock, while the
 * create transaction is still open. The MySQL concurrency script sets it.
 * Leave it unset in every other environment.
 */
final class QuotationNumberAllocator
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
        DB::table('quotation_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);
        $this->pauseForConcurrencyProof();

        $sequence = QuotationNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
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

    private function pauseForConcurrencyProof(): void
    {
        $delay = getenv('ASL_QUOTATION_ALLOCATION_DELAY');

        if (! is_string($delay) || ! in_array($delay, ['1', '2', '3'], true)) {
            return;
        }

        sleep((int) $delay);
    }
}
