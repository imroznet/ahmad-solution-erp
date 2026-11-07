<?php

namespace Tests\Unit;

use App\Tax\CalculationBasis;
use App\Tax\RateBasis;
use App\Tax\RoundingRule;
use App\Tax\TaxCalculator;
use App\Tax\TaxRuleCalendar;
use App\Tax\TaxType;
use App\Tax\ThresholdTreatment;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    public function test_dates_are_inclusive_and_adjacent_ranges_do_not_overlap(): void
    {
        $this->assertTrue(TaxRuleCalendar::contains('2026-07-01', '2026-07-31', '2026-07-01'));
        $this->assertTrue(TaxRuleCalendar::contains('2026-07-01', '2026-07-31', '2026-07-31'));
        $this->assertFalse(TaxRuleCalendar::contains('2026-07-01', '2026-07-31', '2026-08-01'));
        $this->assertTrue(TaxRuleCalendar::contains('2026-07-01', null, '2030-01-01'));
        $this->assertFalse(TaxRuleCalendar::overlaps('2026-07-01', null, '2026-01-01', '2026-06-30'));
        $this->assertTrue(TaxRuleCalendar::overlaps('2026-07-01', null, '2026-01-01', '2026-07-01'));
        $this->assertSame('2026-06-30', TaxRuleCalendar::dayBefore('2026-07-01'));
    }

    public function test_percentage_exclusive_and_inclusive_round_half_away_from_zero(): void
    {
        $calculator = new TaxCalculator;

        $exclusive = $calculator->fromSnapshot(TaxType::OUTPUT_VAT, '1.00', $this->snapshot(RateBasis::PERCENTAGE, '0.5000', CalculationBasis::EXCLUSIVE));
        $this->assertSame('matched', $exclusive->outcome);
        $this->assertSame('0.01', $exclusive->amount);

        $inclusive = $calculator->fromSnapshot(TaxType::OUTPUT_VAT, '115.00', $this->snapshot(RateBasis::PERCENTAGE, '15.0000', CalculationBasis::INCLUSIVE));
        $this->assertSame('15.00', $inclusive->amount);

        $below = $calculator->fromSnapshot(TaxType::VDS, '999.99', $this->snapshot(
            RateBasis::PERCENTAGE,
            '10.0000',
            CalculationBasis::EXCLUSIVE,
            '1000.00',
            ThresholdTreatment::BELOW_IS_ZERO_FULL_BASE,
        ));
        $this->assertSame('0.00', $below->amount);

        $atThreshold = $calculator->fromSnapshot(TaxType::VDS, '1000.00', $this->snapshot(
            RateBasis::PERCENTAGE,
            '10.0000',
            CalculationBasis::EXCLUSIVE,
            '1000.00',
            ThresholdTreatment::BELOW_IS_ZERO_FULL_BASE,
        ));
        $this->assertSame('100.00', $atThreshold->amount);
    }

    public function test_a_missing_rule_is_not_a_zero_rate_and_types_stay_separate(): void
    {
        $calculator = new TaxCalculator;
        $missing = $calculator->fromSnapshot(TaxType::AIT, '100.00', []);

        $this->assertSame('review_required', $missing->outcome);
        $this->assertNull($missing->amount);
        $this->assertSame(TaxType::AIT, $missing->taxType);

        $exempt = $calculator->fromSnapshot(TaxType::OUTPUT_VAT, '100.00', [
            'version_id' => 4,
            'rate_basis' => RateBasis::EXEMPT,
            'calculation_basis' => CalculationBasis::NOT_APPLICABLE,
            'rounding_rule' => RoundingRule::NOT_APPLICABLE,
            'threshold_treatment' => ThresholdTreatment::NOT_APPLICABLE,
        ]);
        $this->assertSame('0.00', $exempt->amount);
        $this->assertStringContainsString('approved rule', $exempt->reasons[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(string $basis, string $rate, string $calculation, ?string $threshold = null, string $treatment = ThresholdTreatment::NOT_APPLICABLE): array
    {
        return [
            'version_id' => 9,
            'rate_basis' => $basis,
            'rate_percent' => $rate,
            'calculation_basis' => $calculation,
            'rounding_rule' => RoundingRule::HALF_AWAY_FROM_ZERO,
            'threshold_amount' => $threshold,
            'threshold_treatment' => $treatment,
        ];
    }
}
