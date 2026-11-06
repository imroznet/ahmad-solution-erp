<?php

namespace App\Tax;

use App\Models\MushakInvoiceNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates M63-{year}-{sequence} when a Mushak 6.3 is issued.
 * The caller must already be inside the issue transaction.
 */
final class MushakNumberAllocator
{
    public function allocate(): string
    {
        $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
        DB::table('mushak_invoice_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);

        $sequence = MushakInvoiceNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->save();

        $configured = config('asl.mushak.number_prefix', 'M63');
        $prefix = is_string($configured) && preg_match('/^[A-Z][A-Z0-9]{1,7}$/', $configured) === 1 ? $configured : 'M63';

        return sprintf('%s-%d-%04d', $prefix, $year, $lastNumber);
    }
}
