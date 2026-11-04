<?php

namespace App\Quotations;

use App\Support\Decimal;
use App\Support\Money;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Quotation arithmetic.
 *
 * Quantity is stored at 4 decimal places and must be greater than zero.
 * Unit price is stored at 2 decimal places and must not be negative.
 * Extra fractional digits are rejected, not rounded, so the stored rate is
 * the rate that was typed.
 *
 * A line total is quantity × unit price, then rounded to 2 decimal places.
 * A tie rounds away from zero. That is the application money rule. It is not
 * an NBR rule. The quotation total is the sum of those already rounded line
 * totals. Tax is not included.
 */
final class QuotationCalculator
{
    public const QUANTITY_SCALE = 4;

    public const MONEY_SCALE = 2;

    /**
     * Largest amount that fits quotation_revisions.subtotal and total, DECIMAL(18,2).
     */
    public const TOTAL_MAX = '9999999999999999.99';

    /**
     * @param  array<array-key, mixed>  $lines
     * @return list<array{position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>
     */
    public function normalizeLines(array $lines): array
    {
        $maxLines = (int) config('asl.quotations.max_lines', 50);

        if ($lines === []) {
            throw new QuotationRuleException('lines', 'Add at least one line.');
        }

        if (count($lines) > $maxLines) {
            throw new QuotationRuleException('lines', 'A quotation can contain at most '.$maxLines.' lines.');
        }

        $normalized = [];

        foreach (array_values($lines) as $index => $line) {
            if (! is_array($line)) {
                throw new QuotationRuleException('lines.'.$index.'.description', 'Enter a description for each line.');
            }

            $description = trim(strip_tags((string) ($line['description'] ?? '')));
            $unit = trim(strip_tags((string) ($line['unit'] ?? '')));

            if ($description === '') {
                throw new QuotationRuleException('lines.'.$index.'.description', 'Enter a description for each line.');
            }

            if (mb_strlen($description) > 2000) {
                throw new QuotationRuleException('lines.'.$index.'.description', 'A line description must be 2000 characters or fewer.');
            }

            if ($unit === '' || mb_strlen($unit) > 40) {
                throw new QuotationRuleException('lines.'.$index.'.unit', 'Enter a unit of 40 characters or fewer.');
            }

            $quantity = $this->quantity((string) ($line['quantity'] ?? ''), 'lines.'.$index.'.quantity');
            $unitPrice = $this->unitPrice((string) ($line['unit_price'] ?? ''), 'lines.'.$index.'.unit_price');

            $normalized[] = [
                'position' => $index + 1,
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $this->lineTotal($quantity, $unitPrice),
            ];
        }

        $this->sum(array_column($normalized, 'line_total'));

        return $normalized;
    }

    /**
     * @param  list<string>  $lineTotals
     */
    public function sum(array $lineTotals): string
    {
        $total = Money::zero();

        foreach ($lineTotals as $lineTotal) {
            $total = $total->plus(Money::of($lineTotal));
        }

        $amount = $total->amount();

        if (Decimal::of($amount)->compare(Decimal::of(self::TOTAL_MAX)) > 0) {
            throw new QuotationRuleException(
                'lines',
                'The quotation total is '.$amount.' BDT, which is above '.self::TOTAL_MAX.' BDT. Reduce the lines before saving.',
            );
        }

        return $amount;
    }

    public function lineTotal(string $quantity, string $unitPrice): string
    {
        $product = Decimal::of($quantity)->multipliedBy(Decimal::of($unitPrice));

        return Money::of((string) $product)->amount();
    }

    public static function formatQuantity(string $quantity): string
    {
        $normalized = (string) Decimal::of($quantity)->toScale(self::QUANTITY_SCALE);
        $trimmed = rtrim(rtrim($normalized, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }

    public static function taxNotice(): string
    {
        $notice = config('asl.quotations.tax_notice');

        return is_string($notice) && $notice !== ''
            ? $notice
            : 'Tax is excluded from the amounts on this quotation.';
    }

    private function quantity(string $value, string $field): string
    {
        $quantity = $this->exact($value, self::QUANTITY_SCALE, $field);
        $maximum = Decimal::of((string) config('asl.quotations.max_quantity', '9999999.9999'));

        if ($quantity->isNegative() || $quantity->isZero()) {
            throw new QuotationRuleException($field, 'Quantity must be greater than zero.');
        }

        if ($quantity->compare($maximum) > 0) {
            throw new QuotationRuleException($field, 'Quantity cannot exceed '.$maximum.'.');
        }

        return (string) $quantity->toScale(self::QUANTITY_SCALE);
    }

    private function unitPrice(string $value, string $field): string
    {
        $price = $this->exact($value, self::MONEY_SCALE, $field);
        $maximum = Decimal::of((string) config('asl.quotations.max_unit_price', '99999999.99'));

        if ($price->isNegative()) {
            throw new QuotationRuleException($field, 'Unit price cannot be negative.');
        }

        if ($price->compare($maximum) > 0) {
            throw new QuotationRuleException($field, 'Unit price cannot exceed '.$maximum.'.');
        }

        return (string) $price->toScale(self::MONEY_SCALE);
    }

    private function exact(string $value, int $scale, string $field): Decimal
    {
        try {
            $decimal = Decimal::of(trim($value));
        } catch (InvalidArgumentException) {
            throw new QuotationRuleException($field, 'Enter a plain decimal number.');
        }

        try {
            return $decimal->toScale($scale, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw new QuotationRuleException($field, 'Use at most '.$scale.' decimal places.');
        }
    }
}
