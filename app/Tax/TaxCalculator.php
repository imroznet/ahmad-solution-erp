<?php

namespace App\Tax;

use App\Support\Decimal;
use App\Support\Money;
use Brick\Math\RoundingMode;

/**
 * Tax arithmetic uses decimal strings. The only rounding to poisha is
 * Money's half-away-from-zero rule, and only when the rule asks for it.
 */
final class TaxCalculator
{
    private const MONEY_CAP = '9999999999999999.99';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function fromSnapshot(string $taxType, string $base, array $snapshot): TaxAssessment
    {
        if (! in_array($taxType, TaxType::all(), true)) {
            return $this->review($taxType, $base, ['The tax type is not output VAT, VDS, or AIT.'], null);
        }

        $basis = $this->string($snapshot, 'rate_basis');
        $calculation = $this->string($snapshot, 'calculation_basis');
        $rounding = $this->string($snapshot, 'rounding_rule');
        $threshold = $this->nullableString($snapshot, 'threshold_amount');
        $treatment = $this->string($snapshot, 'threshold_treatment');
        $versionId = $snapshot['version_id'] ?? null;
        $versionId = is_int($versionId) ? $versionId : (is_numeric($versionId) ? (int) $versionId : null);

        if ($basis === '' || ! in_array($basis, RateBasis::all(), true)) {
            return $this->review($taxType, $base, ['The pinned rule has no recognised calculation basis.'], $versionId);
        }

        if (! $this->validBase($base)) {
            return $this->review($taxType, '0.00', ['The base amount must be a non-negative amount in taka and poisha.'], $versionId);
        }

        if ($threshold !== null && $treatment === ThresholdTreatment::NOT_APPLICABLE) {
            return $this->review($taxType, $base, ['This rule records a threshold without a treatment, so the amount is not calculated.'], $versionId);
        }

        if ($threshold !== null && $treatment === ThresholdTreatment::BELOW_IS_ZERO_FULL_BASE) {
            if (Decimal::of($base)->compare(Decimal::of($threshold)) < 0) {
                return new TaxAssessment(
                    'matched',
                    $taxType,
                    '0.00',
                    $base,
                    $versionId,
                    ['The base is below the threshold recorded on this rule, so the recorded treatment is zero. The threshold is an entry on the rule, not a statutory finding by this screen.'],
                    $snapshot,
                );
            }
        }

        if (RateBasis::isZeroTreatment($basis)) {
            return new TaxAssessment(
                'matched',
                $taxType,
                '0.00',
                $base,
                $versionId,
                ['This approved rule records the treatment "'.RateBasis::label($basis).'". The zero amount is that record. It is not an automatic exemption.'],
                $snapshot,
            );
        }

        if ($basis === RateBasis::FIXED_AMOUNT) {
            $fixed = $this->nullableString($snapshot, 'fixed_amount');

            if ($fixed === null || $rounding !== RoundingRule::HALF_AWAY_FROM_ZERO) {
                return $this->review($taxType, $base, ['The fixed amount or the rounding rule is missing.'], $versionId);
            }

            return $this->matched($taxType, $base, Money::of($fixed), $versionId, [
                'The approved rule uses a fixed amount of BDT '.$fixed.'.',
            ], $snapshot);
        }

        $rate = $this->nullableString($snapshot, 'rate_percent');

        if ($basis !== RateBasis::PERCENTAGE || $rate === null) {
            return $this->review($taxType, $base, ['The approved rule has no percentage rate.'], $versionId);
        }

        if ($rounding !== RoundingRule::HALF_AWAY_FROM_ZERO) {
            return $this->review($taxType, $base, ['Only the application rounding rule, half away from zero, can be calculated. That rule is not an NBR rounding rule.'], $versionId);
        }

        if ($calculation === CalculationBasis::EXCLUSIVE) {
            $raw = Decimal::of($base)->multipliedBy($rate)->dividedBy('100', 10, RoundingMode::HalfUp);

            return $this->matched($taxType, $base, Money::of((string) $raw), $versionId, [
                'Output is the tax-exclusive base times the recorded rate, rounded half away from zero to poisha.',
            ], $snapshot);
        }

        if ($calculation === CalculationBasis::INCLUSIVE) {
            $fraction = Decimal::of($rate)->dividedBy('100', 10, RoundingMode::HalfUp);
            $divisor = Decimal::of('1')->plus($fraction);
            $net = Decimal::of($base)->dividedBy($divisor, 10, RoundingMode::HalfUp);
            $raw = Decimal::of($base)->minus($net);

            return $this->matched($taxType, $base, Money::of((string) $raw), $versionId, [
                'The base is treated as tax-inclusive at the recorded rate. The tax is the base minus the net, rounded half away from zero to poisha.',
            ], $snapshot);
        }

        return $this->review($taxType, $base, ['A percentage rule needs an exclusive or inclusive basis.'], $versionId);
    }

    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $snapshot
     */
    private function matched(string $taxType, string $base, Money $amount, ?int $versionId, array $reasons, array $snapshot): TaxAssessment
    {
        if (Decimal::of($amount->amount())->compare(Decimal::of(self::MONEY_CAP)) > 0) {
            return $this->review($taxType, $base, ['The calculated amount is above 9999999999999999.99.'], $versionId);
        }

        $snapshot['amount'] = $amount->amount();
        $snapshot['base'] = $base;
        $snapshot['tax_type'] = $taxType;

        return new TaxAssessment('matched', $taxType, $amount->amount(), $base, $versionId, $reasons, $snapshot);
    }

    /**
     * @param  list<string>  $reasons
     */
    private function review(string $taxType, string $base, array $reasons, ?int $versionId): TaxAssessment
    {
        return new TaxAssessment('review_required', $taxType, null, $base, $versionId, $reasons, null);
    }

    private function validBase(string $base): bool
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $base)) {
            return false;
        }

        return Decimal::of($base)->compare(Decimal::of(self::MONEY_CAP)) <= 0;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function string(array $snapshot, string $key): string
    {
        $value = $snapshot[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function nullableString(array $snapshot, string $key): ?string
    {
        $value = $snapshot[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
