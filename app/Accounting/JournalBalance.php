<?php

namespace App\Accounting;

use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Checks the double-entry invariant without posting a journal.
 * Amounts are unsigned. A reversal is a new entry, not a negative line.
 */
final class JournalBalance
{
    /**
     * @param  list<string|int>  $debits
     * @param  list<string|int>  $credits
     */
    public static function isBalanced(array $debits, array $credits): bool
    {
        return self::total($debits)->compare(self::total($credits)) === 0;
    }

    /**
     * @param  list<string|int>  $amounts
     */
    private static function total(array $amounts): Decimal
    {
        $total = Decimal::zero();

        foreach ($amounts as $amount) {
            $decimal = Decimal::of($amount);

            if ($decimal->isNegative()) {
                throw new InvalidArgumentException('Journal amounts must be zero or positive.');
            }

            $total = $total->plus($decimal);
        }

        return $total;
    }
}
