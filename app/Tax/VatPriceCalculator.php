<?php

namespace App\Tax;

use App\Support\Decimal;
use App\Support\Money;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use LogicException;

/**
 * Two-way VAT arithmetic from a percentage the user typed.
 *
 * This class does not choose, infer, prefill, or default a percentage. A blank
 * percentage produces no VAT amount and no final amount. An explicit 0% is
 * calculated. VDS and AIT are not inputs.
 *
 * Stored scale is the selected calculation precision (2, 3, or 4). Columns
 * are DECIMAL(20,4) so those results fit without an earlier round. Extra
 * input digits are rounded once with the selected method. They are not
 * truncated.
 *
 * Invariant, whenever VAT is calculated, at that precision:
 * final = base + VAT + adjustment.
 *
 * From the base amount, VAT = round(base × rate / 100, precision, method)
 * and the final amount is the sum. The adjustment is kept as entered
 * (after its own rounding).
 *
 * From the final amount, the rate and the adjustment stay fixed.
 * net = final − adjustment.
 * base = round(net × 100 / (100 + rate), precision, method).
 * VAT = net − base.
 * That VAT is exact, so the invariant holds at the stored precision.
 * The residual is VAT minus round(base × rate / 100). It is recorded and
 * is not added a second time. It explains a poisha that the percentage
 * rounding could not absorb while keeping the typed final amount.
 *
 * Editing the adjustment rounds only the adjustment. Base, rate, and VAT
 * stay as they were. The final amount becomes base + VAT + adjustment.
 *
 * The Mushak face shows BDT at 2 decimal places, rounded with the same
 * method. That display value is not written back over the stored amount.
 * A higher calculation precision therefore cannot appear as a fractional
 * taka on the form face. The supplementary note keeps the stored figures.
 */
final class VatPriceCalculator
{
    public const DISPLAY_SCALE = 2;

    public const RATE_SCALE = 4;

    public const STORAGE_SCALE = 4;

    /** @var list<int> */
    public const PRECISIONS = [2, 3, 4];

    public const ORIGIN_BASE = 'from_base';

    public const ORIGIN_FINAL = 'from_final';

    public const ORIGIN_ADJUSTMENT = 'from_adjustment';

    public const MAX_AMOUNT = '9999999999999999.9999';

    public function fromBase(
        string $base,
        ?string $rate,
        string $adjustment,
        int $precision,
        string $method,
    ): VatPrice {
        $mode = $this->prepare($precision, $method);
        $baseAmount = $this->money($base, $precision, $mode, 'base_amount', false);
        $adjustmentAmount = $this->money($adjustment, $precision, $mode, 'adjustment_amount', true);
        $rateAmount = $this->rate($rate);

        if ($rateAmount === null) {
            return $this->blank($baseAmount, $adjustmentAmount, $precision, $method, self::ORIGIN_BASE);
        }

        $vat = $baseAmount->multipliedBy($rateAmount)->dividedBy(100, $precision, $mode);
        $final = $baseAmount->plus($vat)->plus($adjustmentAmount);
        $this->assertPayable($final, 'final_amount');

        return $this->result(
            $baseAmount,
            $rateAmount,
            $vat,
            $adjustmentAmount,
            $final,
            Decimal::zero()->toScale($precision),
            self::ORIGIN_BASE,
            $precision,
            $method,
        );
    }

    public function fromFinal(
        string $final,
        ?string $rate,
        string $adjustment,
        int $precision,
        string $method,
    ): VatPrice {
        $mode = $this->prepare($precision, $method);
        $finalAmount = $this->money($final, $precision, $mode, 'final_amount', false);
        $adjustmentAmount = $this->money($adjustment, $precision, $mode, 'adjustment_amount', true);
        $rateAmount = $this->rate($rate);

        if ($rateAmount === null) {
            throw new TaxRuleException('vat_rate', 'Enter a VAT percentage before editing the final amount. A blank percentage does not calculate VAT.');
        }

        $net = $finalAmount->minus($adjustmentAmount);

        if ($net->isNegative()) {
            throw new TaxRuleException('final_amount', 'The final amount cannot be less than the rounding adjustment.');
        }

        $base = $net->multipliedBy(100)->dividedBy(Decimal::of(100)->plus($rateAmount), $precision, $mode);
        $theoretical = $base->multipliedBy($rateAmount)->dividedBy(100, $precision, $mode);
        $vat = $net->minus($base);

        if ($base->isNegative() || $vat->isNegative()) {
            throw new TaxRuleException('final_amount', 'That final amount does not produce a base and VAT that stay at or above zero.');
        }

        return $this->result(
            $base,
            $rateAmount,
            $vat,
            $adjustmentAmount,
            $finalAmount,
            $vat->minus($theoretical),
            self::ORIGIN_FINAL,
            $precision,
            $method,
        );
    }

    /**
     * Change only the adjustment. Base, rate, and VAT are accepted only when
     * they are already at the selected precision, so this path cannot round
     * them again.
     */
    public function fromAdjustment(
        string $base,
        ?string $rate,
        ?string $vat,
        string $adjustment,
        int $precision,
        string $method,
    ): VatPrice {
        $mode = $this->prepare($precision, $method);
        $baseAmount = $this->exactMoney($base, $precision, 'base_amount', false);
        $adjustmentAmount = $this->money($adjustment, $precision, $mode, 'adjustment_amount', true);
        $rateAmount = $this->rate($rate);

        if ($rateAmount === null) {
            return $this->blank($baseAmount, $adjustmentAmount, $precision, $method, self::ORIGIN_ADJUSTMENT);
        }

        if ($vat === null || trim($vat) === '') {
            throw new TaxRuleException('vat_amount', 'VAT has not been calculated for this line yet.');
        }

        $vatAmount = $this->exactMoney($vat, $precision, 'vat_amount', false);
        $final = $baseAmount->plus($vatAmount)->plus($adjustmentAmount);
        $this->assertPayable($final, 'adjustment_amount');
        $theoretical = $baseAmount->multipliedBy($rateAmount)->dividedBy(100, $precision, $mode);

        return $this->result(
            $baseAmount,
            $rateAmount,
            $vatAmount,
            $adjustmentAmount,
            $final,
            $vatAmount->minus($theoretical),
            self::ORIGIN_ADJUSTMENT,
            $precision,
            $method,
        );
    }

    public function display(string $amount, string $method): string
    {
        return (string) Decimal::of($amount)->toScale(self::DISPLAY_SCALE, RoundingMethod::mode($method));
    }

    public function formatDisplay(string $amount, string $method): string
    {
        return Money::of($this->display($amount, $method))->format();
    }

    public function explain(VatPrice $price): string
    {
        $method = RoundingMethod::label($price->roundingMethod);
        $precision = $price->precision;
        $lead = 'The VAT percentage was entered by a user. This ERP calculated the VAT amount from that percentage. It did not decide whether the percentage applies. ';
        $lead .= "Calculation precision is {$precision} decimal places. Rounding method: {$method}. ";
        $lead .= 'BDT on the Mushak face is shown at 2 decimal places. ';
        $lead .= 'Rounding / Decimal Adjustment is not VAT, not supplementary duty, and not VDS or AIT. ';

        if ($price->rateIsBlank()) {
            return $lead.'The percentage is blank, so VAT and the final amount were not calculated.';
        }

        $lead .= 'Stored invariant: final = base + VAT + adjustment.';

        if (Decimal::of($price->residualAmount)->isZero()) {
            return $lead;
        }

        return $lead." The typed final amount was kept. VAT is the exact remainder after the base was rounded, and the residual against the rounded percentage is {$price->residualAmount}.";
    }

    private function prepare(int $precision, string $method): RoundingMode
    {
        if (! in_array($precision, self::PRECISIONS, true)) {
            throw new TaxRuleException('calculation_precision', 'Choose a calculation precision of 2, 3, or 4 decimal places.');
        }

        return RoundingMethod::mode($method);
    }

    private function blank(Decimal $base, Decimal $adjustment, int $precision, string $method, string $origin): VatPrice
    {
        return new VatPrice(
            (string) $base,
            null,
            null,
            (string) $adjustment,
            null,
            (string) Decimal::zero()->toScale($precision),
            $origin,
            $precision,
            $method,
        );
    }

    private function result(
        Decimal $base,
        Decimal $rate,
        Decimal $vat,
        Decimal $adjustment,
        Decimal $final,
        Decimal $residual,
        string $origin,
        int $precision,
        string $method,
    ): VatPrice {
        $sum = $base->plus($vat)->plus($adjustment);

        if ($sum->compare($final) !== 0) {
            throw new LogicException('The VAT calculation did not satisfy final = base + VAT + adjustment.');
        }

        return new VatPrice(
            (string) $base,
            (string) $rate->toScale(self::RATE_SCALE),
            (string) $vat,
            (string) $adjustment,
            (string) $final,
            (string) $residual,
            $origin,
            $precision,
            $method,
        );
    }

    private function rate(?string $rate): ?Decimal
    {
        if ($rate === null) {
            return null;
        }

        $trimmed = trim($rate);

        if ($trimmed === '') {
            return null;
        }

        if (! preg_match('/^\d{1,3}(\.\d{1,4})?$/', $trimmed)) {
            throw new TaxRuleException('vat_rate', 'Enter the VAT percentage with at most 4 decimal places, or leave it blank.');
        }

        $value = Decimal::of($trimmed)->toScale(self::RATE_SCALE, RoundingMode::Unnecessary);

        if ($value->compare(Decimal::of(100)) > 0) {
            throw new TaxRuleException('vat_rate', 'Enter a VAT percentage from 0 to 100.');
        }

        return $value;
    }

    private function money(string $amount, int $precision, RoundingMode $mode, string $field, bool $signed): Decimal
    {
        $trimmed = trim($amount);

        if ($trimmed === '') {
            if ($field !== 'adjustment_amount') {
                throw new TaxRuleException($field, 'Enter an amount.');
            }

            $trimmed = '0';
        }

        if (! preg_match('/^[+-]?\d{1,16}(\.\d{1,8})?$/', $trimmed)) {
            throw new TaxRuleException($field, 'Enter a plain decimal amount with no thousands separator.');
        }

        if (! $signed && str_starts_with($trimmed, '-')) {
            throw new TaxRuleException($field, 'This amount cannot be negative.');
        }

        $rounded = Decimal::of($trimmed)->toScale($precision, $mode);
        $this->assertWithinLimit($rounded, $field);

        if (! $signed && $rounded->isNegative()) {
            throw new TaxRuleException($field, 'This amount cannot be negative.');
        }

        return $rounded;
    }

    private function exactMoney(string $amount, int $precision, string $field, bool $signed): Decimal
    {
        $trimmed = trim($amount);

        if (! preg_match('/^[+-]?\d{1,16}(\.\d{1,4})?$/', $trimmed)) {
            throw new TaxRuleException($field, 'Enter a plain decimal amount.');
        }

        if (! $signed && str_starts_with($trimmed, '-')) {
            throw new TaxRuleException($field, 'This amount cannot be negative.');
        }

        try {
            $value = Decimal::of($trimmed)->toScale($precision, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw new TaxRuleException($field, 'That amount has more decimal places than the selected calculation precision.');
        }

        $this->assertWithinLimit($value, $field);

        return $value;
    }

    private function assertPayable(Decimal $final, string $field): void
    {
        if ($final->isNegative()) {
            throw new TaxRuleException($field, 'The final amount cannot be negative.');
        }

        $this->assertWithinLimit($final, 'final_amount');
    }

    private function assertWithinLimit(Decimal $amount, string $field): void
    {
        $limit = Decimal::of(self::MAX_AMOUNT);
        $absolute = $amount->isNegative() ? $amount->multipliedBy(-1) : $amount;

        if ($absolute->compare($limit) > 0) {
            throw new TaxRuleException($field, 'That amount is larger than this document can store.');
        }
    }
}
