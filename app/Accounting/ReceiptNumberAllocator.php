<?php

namespace App\Accounting;

use App\Models\ReceiptNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates RCPT-{Asia/Dhaka year}-{sequence}.
 * The caller must already be inside the receipt transaction.
 */
final class ReceiptNumberAllocator
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
        DB::table('receipt_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = ReceiptNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->save();

        return sprintf('%s-%d-%04d', $this->prefix(), $year, $lastNumber);
    }

    private function prefix(): string
    {
        $configured = config('asl.receipts.number_prefix', 'RCPT');

        return is_string($configured) && preg_match('/^[A-Z]{2,8}$/', $configured) === 1
            ? $configured
            : 'RCPT';
    }
}
