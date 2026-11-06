<?php

namespace App\Documents;

use App\Models\CommercialInvoiceNumberSequence;
use App\Models\DeliveryChallanNumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Allocates DC-{year}-{sequence} and CI-{year}-{sequence} numbers.
 *
 * The year is the calendar year in Asia/Dhaka. The year's row is created with
 * INSERT IGNORE before anyone locks it. The increment uses FOR UPDATE.
 * The caller must already be inside the issue transaction. A failed issue
 * rolls the number back. Drafts do not receive a number.
 *
 * ASL_DOCUMENT_ALLOCATION_DELAY may be 1, 2, or 3. The allocator then sleeps
 * after the insert and before the row lock. The MySQL concurrency script sets
 * it on the worker processes only.
 */
final class DocumentNumberAllocator
{
    public const CHALLAN = 'challan';

    public const INVOICE = 'invoice';

    public function allocate(string $series): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): string => $this->next($series), 5);
        }

        return $this->next($series);
    }

    private function next(string $series): string
    {
        $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
        DB::table($this->table($series))->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'updated_at' => now(),
        ]);
        $this->pauseForConcurrencyProof();

        $sequence = $this->query($series)->where('year', $year)->lockForUpdate()->firstOrFail();
        $lastNumber = (int) $sequence->getAttribute('last_number') + 1;
        $sequence->setAttribute('last_number', $lastNumber);
        $sequence->save();

        return sprintf('%s-%d-%04d', $this->prefix($series), $year, $lastNumber);
    }

    private function table(string $series): string
    {
        return match ($series) {
            self::CHALLAN => 'delivery_challan_number_sequences',
            self::INVOICE => 'commercial_invoice_number_sequences',
            default => throw new DocumentRuleException('number', 'Choose a document series.'),
        };
    }

    private function query(string $series): Builder
    {
        $model = match ($series) {
            self::CHALLAN => new DeliveryChallanNumberSequence,
            self::INVOICE => new CommercialInvoiceNumberSequence,
            default => throw new DocumentRuleException('number', 'Choose a document series.'),
        };

        return $model->newQuery();
    }

    private function prefix(string $series): string
    {
        $configured = match ($series) {
            self::CHALLAN => config('asl.delivery_challans.number_prefix', 'DC'),
            self::INVOICE => config('asl.commercial_invoices.number_prefix', 'CI'),
            default => 'DC',
        };

        return is_string($configured) && preg_match('/^[A-Z]{2,8}$/', $configured) === 1 ? $configured : ($series === self::INVOICE ? 'CI' : 'DC');
    }

    private function pauseForConcurrencyProof(): void
    {
        $delay = getenv('ASL_DOCUMENT_ALLOCATION_DELAY');

        if (! is_string($delay) || ! in_array($delay, ['1', '2', '3'], true)) {
            return;
        }

        sleep((int) $delay);
    }
}
