<?php

namespace App\Accounting;

use App\Models\PaymentNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates PAYM-{Asia/Dhaka year}-{sequence}.
 * The caller must already be inside the payment transaction.
 */
final class PaymentNumberAllocator
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
        DB::table('payment_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = PaymentNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->save();

        return sprintf('%s-%d-%04d', $this->prefix(), $year, $lastNumber);
    }

    private function prefix(): string
    {
        $configured = config('asl.payments.number_prefix', 'PAYM');

        return is_string($configured) && preg_match('/^[A-Z]{2,8}$/', $configured) === 1
            ? $configured
            : 'PAYM';
    }
}
