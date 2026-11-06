<?php

namespace App\Tax;

use App\Support\Decimal;
use Brick\Math\RoundingMode;

/**
 * Two-way VAT arithmetic from a percentage the user typed.
 *
 * The percentage is never invented, prefilled, or read from a tax rule.
 * A blank percentage does not calculate VAT. An entered zero does.
 * Decimal strings only. Floating-point values are rejected by Decimal::of.
 */
final class VatLineCalculator
{
    public function calculate(
        string $base,
        ?string $rate,
        string $adjustment,
        ?string $final,
        string $driver,
        int $precision,
        string $roundingMethod,
    ): VatLineResult {
        $mode = VatRounding::mode($roundingMethod);
        $this->assertPrecision($precision);

        $adjustmentAmount = $this->money($adjustment, $precision, $mode, 'adjustment_amount');
        $parsedRate = $this->rate($rate);

        if ($driver === 'reverse') {
            if ($parsedRate === null) {
                throw new TaxRuleException('vat_rate', 'Enter a VAT percentage before reversing the final amount. A blank percentage is not zero.');
            }

            if ($final === null || trim($final) === '') {
                throw new TaxRuleException('final_amount', 'Enter the final amount to reverse-calculate the base.');
            }

            return $this->fromFinal(
                $this->money($final, $precision, $mode, 'final_amount'),
                $parsedRate,
                $adjustmentAmount,
                $precision,
                $mode,
                $roundingMethod,
            );
        }

        if ($driver !== 'forward') {
            throw new TaxRuleException('driver', 'Choose whether to calculate from the base amount or from the final amount.');
        }

        return $this->fromBase(
            $this->money($base, $precision, $mode, 'base_amount'),
            $parsedRate,
            $adjustmentAmount,
            $precision,
            $mode,
            $roundingMethod,
        );
    }

    private function fromBase(
        Decimal $base,
        ?Decimal $rate,
        Decimal $adjustment,
        int $precision,
        RoundingMode $mode,
        string $roundingMethod,
    ): VatLineResult {
        $this->assertNonNegative($base, 'base_amount', 'The base amount cannot be negative.');

        if ($rate === null) {
            return new VatLineResult(
                base: $this->text($base, $precision),
                rate: null,
                vat: null,
                forwardVat: null,
                residualApplied: false,
                adjustment: $this->text($adjustment, $precision),
                final: null,
                precision: $precision,
                roundingMethod: $roundingMethod,
                driver: 'forward',
            );
        }

        $vat = $base->multipliedBy($rate)->dividedBy(100, $precision, $mode);
        $this->assertNonNegative($vat, 'vat_amount', 'The VAT amount cannot be negative.');
        $final = $base->plus($vat)->plus($adjustment);
        $this->assertNonNegative($final, 'final_amount', 'The final amount cannot be negative. Reduce the adjustment or increase the base.');

        return new VatLineResult(
            base: $this->text($base, $precision),
            rate: $this->rateText($rate),
            vat: $this->text($vat, $precision),
            forwardVat: $this->text($vat, $precision),
            residualApplied: false,
            adjustment: $this->text($adjustment, $precision),
            final: $this->text($final, $precision),
            precision: $precision,
            roundingMethod: $roundingMethod,
            driver: 'forward',
        );
    }

    private function fromFinal(
        Decimal $final,
        Decimal $rate,
        Decimal $adjustment,
        int $precision,
        RoundingMode $mode,
        string $roundingMethod,
    ): VatLineResult {
        $this->assertNonNegative($final, 'final_amount', 'The final amount cannot be negative.');
        $net = $final->minus($adjustment);
        $this->assertNonNegative($net, 'final_amount', 'The final amount cannot be smaller than the adjustment.');

        $divisor = Decimal::of(100)->plus($rate);
        $base = $net->multipliedBy(100)->dividedBy($divisor, $precision, $mode);
        $this->assertNonNegative($base, 'base_amount', 'The reverse-calculated base amount cannot be negative.');

        $vat = $net->minus($base);
        $this->assertNonNegative($vat, 'vat_amount', 'The residual VAT amount cannot be negative.');

        $forward = $base->multipliedBy($rate)->dividedBy(100, $precision, $mode);
        $reconstructed = $base->plus($vat)->plus($adjustment);

        if ($reconstructed->compare($final) !== 0) {
            throw new TaxRuleException('final_amount', 'The final amount could not be preserved exactly.');
        }

        return new VatLineResult(
            base: $this->text($base, $precision),
            rate: $this->rateText($rate),
            vat: $this->text($vat, $precision),
            forwardVat: $this->text($forward, $precision),
            residualApplied: $vat->compare($forward) !== 0,
            adjustment: $this->text($adjustment, $precision),
            final: $this->text($final, $precision),
            precision: $precision,
            roundingMethod: $roundingMethod,
            driver: 'reverse',
        );
    }

    private function money(string $value, int $precision, RoundingMode $mode, string $field): Decimal
    {
        try {
            $parsed = Decimal::of(trim($value));
        } catch (\InvalidArgumentException) {
            throw new TaxRuleException($field, 'Enter a plain decimal amount.');
        }

        $scaled = $parsed->toScale($precision, $mode);
        $limit = Decimal::of('999999999999.9999');

        if ($scaled->compare($limit) > 0 || $limit->multipliedBy(-1)->compare($scaled) > 0) {
            throw new TaxRuleException($field, 'The amount is outside the stored range.');
        }

        return $scaled;
    }

    private function rate(?string $value): ?Decimal
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(?:100(?:\.0{1,4})?|\d{1,2}(?:\.\d{1,4})?)$/', $trimmed) !== 1) {
            throw new TaxRuleException('vat_rate', 'Enter a VAT percentage from 0 to 100, with at most 4 decimal places. Leave it blank if you are not entering one. A blank percentage is not zero.');
        }

        return Decimal::of($trimmed);
    }

    private function rateText(Decimal $rate): string
    {
        return (string) $rate;
    }

    private function text(Decimal $amount, int $precision): string
    {
        return (string) $amount->toScale($precision, RoundingMode::Unnecessary);
    }

    private function assertPrecision(int $precision): void
    {
        if (! in_array($precision, [2, 3, 4], true)) {
            throw new TaxRuleException('calculation_precision', 'Choose a calculation precision of 2, 3, or 4 decimal places.');
        }
    }

    private function assertNonNegative(Decimal $amount, string $field, string $message): void
    {
        if ($amount->isNegative()) {
            throw new TaxRuleException($field, $message);
        }
    }
}
