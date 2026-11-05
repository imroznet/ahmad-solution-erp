<?php

namespace App\Documents;

use App\Support\Decimal;
use App\Support\Money;
use Brick\Math\RoundingMode;

/**
 * Prices one invoiced slice of an accepted quotation line.
 *
 * Each slice is quantity × unit price, rounded half away from zero to poisha.
 * That raw amount is then capped so the issued slices of the line never exceed
 * the accepted line total. The slice that consumes the remaining accepted
 * quantity is priced as whatever accepted amount is still left, so a fully
 * invoiced line adds up to the accepted line total. An earlier slice may be
 * 0.00 when rounding already used the accepted amount.
 */
final class InvoicePricing
{
    public function stageAmount(
        string $quantity,
        string $unitPrice,
        string $acceptedQuantity,
        string $acceptedLineTotal,
        string $priorQuantity,
        string $priorAmount,
        string $field,
    ): string {
        $acceptedQuantityDecimal = Decimal::of($acceptedQuantity);
        $acceptedTotal = Decimal::of($acceptedLineTotal);
        $priorQuantityDecimal = Decimal::of($priorQuantity);
        $priorAmountDecimal = Decimal::of($priorAmount);
        $remainingAmount = $acceptedTotal->minus($priorAmountDecimal);

        if ($remainingAmount->isNegative()) {
            throw new DocumentRuleException($field, 'The invoiced amount would exceed the accepted line total.');
        }

        $combinedQuantity = $priorQuantityDecimal->plus(Decimal::of($quantity));

        if ($combinedQuantity->compare($acceptedQuantityDecimal) > 0) {
            throw new DocumentRuleException($field, 'The invoiced quantity would exceed the accepted quantity.');
        }

        $raw = Money::of((string) Decimal::of($quantity)->multipliedBy(Decimal::of($unitPrice)))->amount();
        $rawDecimal = Decimal::of($raw);
        $chosen = $combinedQuantity->compare($acceptedQuantityDecimal) === 0 || $rawDecimal->compare($remainingAmount) > 0
            ? $remainingAmount
            : $rawDecimal;

        return (string) $chosen->toScale(Money::SCALE, RoundingMode::Unnecessary);
    }
}
