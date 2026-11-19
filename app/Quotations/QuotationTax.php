<?php

namespace App\Quotations;

use App\Support\Decimal;
use App\Support\Money;
use App\Tax\TaxRuleException;
use App\Tax\VatLineCalculator;
use App\Tax\VatRounding;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * VAT and a generic tax on one quotation revision.
 *
 * Excluding stores no tax. The total stays the sum of the line totals.
 * Including calculates from percentages the user typed. A blank percentage
 * is not zero and does not calculate. The browser is not the calculator.
 *
 * Quotation money columns are 2 decimal places, so this uses precision 2
 * and half up (ties away from zero), the same rule as a line total.
 * Tax % is a generic percentage. It is not VAT, supplementary duty, VDS, or AIT.
 */
final class QuotationTax
{
    public const EXCLUDED = 'excluded';

    public const INCLUDED = 'included';

    public const INCLUDED_NOTICE = 'The total includes VAT and the generic tax only where a percentage was typed on this quotation. A blank percentage is not calculated. Tax % is a generic tax, not VAT, supplementary duty, VDS, or AIT. This document is not a tax invoice or a Mushak form.';

    public function __construct(private readonly VatLineCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(array $input, QuotationCalculator $lines): array
    {
        $normalized = $lines->normalizeLines($this->lineRows($input));

        return $this->present($this->price(array_column($normalized, 'line_total'), $input, $lines));
    }

    /**
     * @param  list<string>  $lineTotals
     * @param  array<string, mixed>  $input
     * @return array{
     *     tax_treatment: string,
     *     subtotal: string,
     *     total: string,
     *     vat_rate_percent: ?string,
     *     tax_rate_percent: ?string,
     *     vat_amount: ?string,
     *     tax_amount: ?string,
     *     adjustment_amount: string,
     *     adjustment_reason: ?string,
     *     tax_notice: string
     * }
     */
    public function price(array $lineTotals, array $input, QuotationCalculator $lines): array
    {
        $base = $lines->sum($lineTotals);
        $treatment = $this->treatment($input['tax_treatment'] ?? null);

        if ($treatment === self::EXCLUDED) {
            return [
                'tax_treatment' => self::EXCLUDED,
                'subtotal' => $base,
                'total' => $base,
                'vat_rate_percent' => null,
                'tax_rate_percent' => null,
                'vat_amount' => null,
                'tax_amount' => null,
                'adjustment_amount' => '0.00',
                'adjustment_reason' => null,
                'tax_notice' => QuotationCalculator::taxNotice(),
            ];
        }

        $vatRate = $this->blankableRate($input['vat_rate_percent'] ?? null, 'vat_rate_percent');
        $taxRate = $this->blankableRate($input['tax_rate_percent'] ?? null, 'tax_rate_percent');
        $adjustment = $this->adjustment($input['adjustment_amount'] ?? null);
        $reason = $this->reason($input['adjustment_reason'] ?? null);

        try {
            $vat = $this->calculator->calculate($base, $vatRate, '0', null, 'forward', QuotationCalculator::MONEY_SCALE, VatRounding::HALF_UP, 'vat_rate_percent');
            $tax = $this->calculator->calculate($base, $taxRate, '0', null, 'forward', QuotationCalculator::MONEY_SCALE, VatRounding::HALF_UP, 'tax_rate_percent');
        } catch (TaxRuleException $exception) {
            throw new QuotationRuleException($exception->field, $exception->getMessage());
        }

        if (Decimal::of($adjustment)->isZero()) {
            $reason = null;
        } elseif ($reason === null) {
            throw new QuotationRuleException('adjustment_reason', 'Enter a reason for an adjustment that is not zero.');
        }

        $total = Decimal::of($base);

        if (is_string($vat->vat)) {
            $total = $total->plus(Decimal::of($vat->vat));
        }

        if (is_string($tax->vat)) {
            $total = $total->plus(Decimal::of($tax->vat));
        }

        $total = $total->plus(Decimal::of($adjustment));

        if ($total->isNegative()) {
            throw new QuotationRuleException('adjustment_amount', 'The total cannot be negative. Reduce the adjustment or increase the lines.');
        }

        if ($total->compare(Decimal::of(QuotationCalculator::TOTAL_MAX)) > 0) {
            throw new QuotationRuleException('lines', 'The quotation total is '.(string) $total.' BDT, which is above '.QuotationCalculator::TOTAL_MAX.' BDT. Reduce the lines before saving.');
        }

        return [
            'tax_treatment' => self::INCLUDED,
            'subtotal' => $base,
            'total' => (string) $total->toScale(QuotationCalculator::MONEY_SCALE, RoundingMode::Unnecessary),
            'vat_rate_percent' => $this->storedRate($vat->rate),
            'tax_rate_percent' => $this->storedRate($tax->rate),
            'vat_amount' => $vat->vat,
            'tax_amount' => $tax->vat,
            'adjustment_amount' => $adjustment,
            'adjustment_reason' => $reason,
            'tax_notice' => self::INCLUDED_NOTICE,
        ];
    }

    /**
     * @param  array{
     *     tax_treatment: string,
     *     subtotal: string,
     *     total: string,
     *     vat_amount: ?string,
     *     tax_amount: ?string,
     *     adjustment_amount: string,
     *     tax_notice: string
     * }  $priced
     * @return array<string, mixed>
     */
    public function present(array $priced): array
    {
        $included = $priced['tax_treatment'] === self::INCLUDED;

        return [
            'tax_treatment' => $priced['tax_treatment'],
            'subtotal' => $priced['subtotal'],
            'vat_amount' => $priced['vat_amount'],
            'tax_amount' => $priced['tax_amount'],
            'adjustment_amount' => $priced['adjustment_amount'],
            'total' => $priced['total'],
            'base_display' => Money::of($priced['subtotal'])->format(),
            'vat_display' => $priced['vat_amount'] === null ? 'Not calculated' : Money::of($priced['vat_amount'])->format(),
            'tax_display' => $priced['tax_amount'] === null ? 'Not calculated' : Money::of($priced['tax_amount'])->format(),
            'adjustment_display' => $included ? Money::of($priced['adjustment_amount'])->format() : 'None',
            'total_display' => Money::of($priced['total'])->format(),
            'notice' => $priced['tax_notice'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function lineRows(array $input): array
    {
        $lines = $input['lines'] ?? null;

        if (! is_array($lines)) {
            throw new QuotationRuleException('lines', 'Add at least one line.');
        }

        $rows = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $description = trim((string) ($line['description'] ?? ''));
            $unit = trim((string) ($line['unit'] ?? ''));
            $quantity = trim((string) ($line['quantity'] ?? ''));
            $price = trim((string) ($line['unit_price'] ?? ''));

            if ($description === '' && $unit === '' && $quantity === '' && $price === '') {
                continue;
            }

            $rows[] = [
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $price,
            ];
        }

        return $rows;
    }

    private function treatment(mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::EXCLUDED;
        }

        if ($value === self::EXCLUDED || $value === self::INCLUDED) {
            return $value;
        }

        throw new QuotationRuleException('tax_treatment', 'Choose VAT excluding or VAT including.');
    }

    private function blankableRate(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new QuotationRuleException($field, 'Enter a percentage or leave it blank.');
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function adjustment(mixed $value): string
    {
        if ($value === null) {
            return '0.00';
        }

        if (! is_string($value)) {
            throw new QuotationRuleException('adjustment_amount', 'Enter a plain decimal adjustment, or leave it blank.');
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '0.00';
        }

        try {
            $decimal = Decimal::of($trimmed);
        } catch (InvalidArgumentException) {
            throw new QuotationRuleException('adjustment_amount', 'Enter a plain decimal adjustment, or leave it blank.');
        }

        try {
            $scaled = $decimal->toScale(QuotationCalculator::MONEY_SCALE, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw new QuotationRuleException('adjustment_amount', 'Use at most 2 decimal places for the adjustment.');
        }

        $limit = Decimal::of(QuotationCalculator::TOTAL_MAX);

        if ($scaled->compare($limit) > 0 || $limit->multipliedBy(-1)->compare($scaled) > 0) {
            throw new QuotationRuleException('adjustment_amount', 'The adjustment is outside the stored range.');
        }

        return (string) $scaled;
    }

    private function reason(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > 2000) {
            throw new QuotationRuleException('adjustment_reason', 'The adjustment reason is too long.');
        }

        return $trimmed;
    }

    private function storedRate(?string $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        return (string) Decimal::of($rate)->toScale(4, RoundingMode::Unnecessary);
    }
}
