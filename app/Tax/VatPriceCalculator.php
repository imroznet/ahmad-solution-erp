<?php

namespace App\Tax;

use App\Support\Decimal;
use Brick\Math\RoundingMode;

/**
 * Two-way VAT amounts for one line. The caller supplies the percentage.
 *
 * This class does not choose, infer, prefill, or default a rate. A blank
 * percentage produces no VAT amount and no inclusive amount. An explicit
 * zero percentage is calculated: VAT is 0.00 and the inclusive amount
 * equals the base.
 *
 * Forward path. Round half away from zero to two decimal places (poisha):
 * VAT = round(base × rate / 100, 2), then inclusive = base + VAT.
 *
 * Reverse path, when the VAT-inclusive amount is the value the user edited.
 * The inclusive amount is preserved exactly at two decimal places:
 * base = round(inclusive × 100 / (100 + rate), 2), then VAT = inclusive − base.
 * That residual subtraction is what makes base + VAT equal the entered
 * inclusive amount to the poisha. A zero percentage keeps the base equal
 * to the inclusive amount and sets VAT to 0.00.
 *
 * When quantity and unit price are the edited values, the base is
 * round(quantity × unit price, 2) and the forward path then runs from that base.
 *
 * Changing the percentage uses the forward path from the base. It does not
 * keep a previously entered inclusive amount.
 *
 * The two-decimal half-away-from-zero step is an application rule for BDT.
 * It is not a claim that the National Board of Revenue prescribes it.
 * Supplementary duty, VDS, and income-tax withholding are not inputs.
 */
final class VatPriceCalculator
{
    public const MONEY_SCALE = 2;

    public const RATE_SCALE = 4;

    public const MAX_MONEY = '9999999999999999.99';

    public const MAX_RATE = '999.9999';

    public const MAX_QUANTITY = '9999999.9999';

    public function forward(string $base, ?string $rate): VatLineCalculation
    {
        $base = $this->money($base, 'base_amount', 'Base amount');
        $normalized = $this->rate($rate, 'vat_rate');

        if ($normalized === null) {
            return new VatLineCalculation($base, null, null, null, false, 'none');
        }

        $vat = $this->vatOn($base, $normalized);
        $inclusive = $this->sum([$base, $vat], 'inclusive_amount', 'VAT-inclusive amount');

        return new VatLineCalculation($base, $normalized, $vat, $inclusive, true, 'base');
    }

    public function reverse(string $inclusive, string $rate): VatLineCalculation
    {
        $inclusive = $this->money($inclusive, 'inclusive_amount', 'VAT-inclusive amount');
        $normalized = $this->rate($rate, 'vat_rate');

        if ($normalized === null) {
            throw new TaxRuleException('vat_rate', 'Enter a VAT percentage before editing the VAT-inclusive amount. A blank percentage is not calculated.');
        }

        if (Decimal::of($normalized)->isZero()) {
            return new VatLineCalculation($inclusive, $normalized, $this->zero(), $inclusive, true, 'inclusive');
        }

        $numerator = Decimal::of($inclusive)->multipliedBy('100');
        $denominator = Decimal::of('100')->plus(Decimal::of($normalized));
        $base = $numerator->dividedBy($denominator, self::MONEY_SCALE, RoundingMode::HalfUp);
        $baseText = $this->bounded($base, 'base_amount', 'Base amount');
        $vat = Decimal::of($inclusive)->minus(Decimal::of($baseText));
        $vatText = $this->bounded($vat, 'vat_amount', 'VAT amount');

        return new VatLineCalculation($baseText, $normalized, $vatText, $inclusive, true, 'inclusive');
    }

    public function fromQuantity(string $quantity, string $unitPrice, ?string $rate): VatLineCalculation
    {
        $quantity = $this->quantity($quantity);
        $unitPrice = $this->money($unitPrice, 'unit_price', 'Unit price');
        $product = Decimal::of($quantity)->multipliedBy($unitPrice)->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
        $base = $this->bounded($product, 'base_amount', 'Base amount');
        $result = $this->forward($base, $rate);

        return new VatLineCalculation(
            $result->base,
            $result->rate,
            $result->vat,
            $result->inclusive,
            $result->calculated,
            'components',
        );
    }

    /**
     * @param  list<string>  $amounts
     */
    public function sum(array $amounts, string $field = 'total', string $label = 'Total'): string
    {
        $total = Decimal::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus(Decimal::of($amount));
        }

        return $this->bounded($total, $field, $label);
    }

    public function rate(?string $value, string $field = 'vat_rate'): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new TaxRuleException($field, 'Enter the VAT percentage as a non-negative number, or leave it blank. The system does not choose a percentage.');
        }

        $fraction = explode('.', $value, 2)[1] ?? '';

        if (strlen($fraction) > self::RATE_SCALE) {
            throw new TaxRuleException($field, 'The VAT percentage cannot have more than 4 decimal places.');
        }

        $normalized = Decimal::of($value)->toScale(self::RATE_SCALE);

        if ($normalized->compare(Decimal::of(self::MAX_RATE)) > 0) {
            throw new TaxRuleException($field, 'The VAT percentage cannot be more than 999.9999. That limit is an input bound, not a statutory rate.');
        }

        return (string) $normalized;
    }

    public function displayRate(?string $rate): string
    {
        $normalized = $this->rate($rate);

        if ($normalized === null) {
            return '';
        }

        $trimmed = rtrim(rtrim($normalized, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public function money(string $value, string $field, string $label): string
    {
        $value = trim($value);

        if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new TaxRuleException($field, $label.' must be a non-negative number with at most 2 decimal places.');
        }

        $fraction = explode('.', $value, 2)[1] ?? '';

        if (strlen($fraction) > self::MONEY_SCALE) {
            throw new TaxRuleException($field, $label.' cannot have more than 2 decimal places.');
        }

        return $this->bounded(Decimal::of($value)->toScale(self::MONEY_SCALE), $field, $label);
    }

    public function optionalMoney(?string $value, string $field, string $label): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->money($value, $field, $label);
    }

    public function quantity(string $value, string $field = 'quantity'): string
    {
        $value = trim($value);

        if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new TaxRuleException($field, 'Quantity must be a positive number with at most 4 decimal places.');
        }

        $fraction = explode('.', $value, 2)[1] ?? '';

        if (strlen($fraction) > 4) {
            throw new TaxRuleException($field, 'Quantity cannot have more than 4 decimal places.');
        }

        $quantity = Decimal::of($value)->toScale(4);

        if ($quantity->isZero() || $quantity->isNegative()) {
            throw new TaxRuleException($field, 'Quantity must be greater than zero.');
        }

        if ($quantity->compare(Decimal::of(self::MAX_QUANTITY)) > 0) {
            throw new TaxRuleException($field, 'Quantity cannot be more than 9999999.9999.');
        }

        return (string) $quantity;
    }

    private function vatOn(string $base, string $rate): string
    {
        $product = Decimal::of($base)
            ->multipliedBy($rate)
            ->dividedBy('100', self::MONEY_SCALE, RoundingMode::HalfUp);

        return $this->bounded($product, 'vat_amount', 'VAT amount');
    }

    private function zero(): string
    {
        return $this->bounded(Decimal::zero(), 'vat_amount', 'VAT amount');
    }

    private function bounded(Decimal $amount, string $field, string $label): string
    {
        $scaled = $amount->toScale(self::MONEY_SCALE);

        if ($scaled->isNegative()) {
            throw new TaxRuleException($field, $label.' cannot be negative.');
        }

        if ($scaled->compare(Decimal::of(self::MAX_MONEY)) > 0) {
            throw new TaxRuleException($field, $label.' cannot be more than '.self::MAX_MONEY.'.');
        }

        return (string) $scaled;
    }
}
