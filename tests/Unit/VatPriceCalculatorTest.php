<?php

namespace Tests\Unit;

use App\Support\Decimal;
use App\Tax\RoundingMethod;
use App\Tax\TaxRuleException;
use App\Tax\VatPrice;
use App\Tax\VatPriceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VatPriceCalculatorTest extends TestCase
{
    private VatPriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new VatPriceCalculator;
    }

    public function test_a_blank_percentage_does_not_calculate_vat_or_default_to_fifteen(): void
    {
        $price = $this->calculator->fromBase('1000.00', null, '0', 2, RoundingMethod::HALF_UP);

        $this->assertNull($price->rate);
        $this->assertNull($price->vatAmount);
        $this->assertNull($price->finalAmount);
        $this->assertSame('1000.00', $price->baseAmount);
        $this->assertSame('0.00', $price->adjustmentAmount);
        $this->assertStringNotContainsString('15', $this->calculator->explain($price));
    }

    public function test_an_explicit_zero_percentage_calculates_zero_vat(): void
    {
        $price = $this->calculator->fromBase('80.00', '0', '-0.50', 2, RoundingMethod::HALF_UP);

        $this->assertSame('0.0000', $price->rate);
        $this->assertSame('0.00', $price->vatAmount);
        $this->assertSame('-0.50', $price->adjustmentAmount);
        $this->assertSame('79.50', $price->finalAmount);
        $this->assertTrue($this->holds($price));
    }

    public function test_base_and_rate_keep_the_adjustment_and_recalculate_vat(): void
    {
        $first = $this->calculator->fromBase('100.00', '10', '0.50', 2, RoundingMethod::HALF_UP);
        $changed = $this->calculator->fromBase($first->baseAmount, '5', $first->adjustmentAmount, 2, RoundingMethod::HALF_UP);

        $this->assertSame('10.00', $first->vatAmount);
        $this->assertSame('110.50', $first->finalAmount);
        $this->assertSame('100.00', $changed->baseAmount);
        $this->assertSame('5.0000', $changed->rate);
        $this->assertSame('5.00', $changed->vatAmount);
        $this->assertSame('0.50', $changed->adjustmentAmount);
        $this->assertSame('105.50', $changed->finalAmount);
        $this->assertSame('0.00', $changed->residualAmount);
    }

    public function test_editing_the_final_amount_keeps_the_rate_and_adjustment(): void
    {
        $price = $this->calculator->fromFinal('115.00', '15', '-0.05', 2, RoundingMethod::HALF_UP);

        $this->assertSame('15.0000', $price->rate);
        $this->assertSame('-0.05', $price->adjustmentAmount);
        $this->assertSame(VatPriceCalculator::ORIGIN_FINAL, $price->origin);
        $this->assertTrue($this->holds($price));
        $this->assertSame('115.00', $price->finalAmount);
    }

    public function test_editing_the_adjustment_does_not_change_base_rate_or_vat(): void
    {
        $original = $this->calculator->fromBase('250.00', '7.5', '0', 2, RoundingMethod::HALF_UP);
        $adjusted = $this->calculator->fromAdjustment(
            $original->baseAmount,
            $original->rate,
            $original->vatAmount,
            '-1.25',
            2,
            RoundingMethod::HALF_UP,
        );

        $this->assertSame($original->baseAmount, $adjusted->baseAmount);
        $this->assertSame($original->rate, $adjusted->rate);
        $this->assertSame($original->vatAmount, $adjusted->vatAmount);
        $this->assertSame('-1.25', $adjusted->adjustmentAmount);
        $this->assertSame('267.50', $adjusted->finalAmount);
        $this->assertSame(VatPriceCalculator::ORIGIN_ADJUSTMENT, $adjusted->origin);
        $this->assertTrue($this->holds($adjusted));
    }

    public function test_half_even_and_half_up_diverge_on_a_tie(): void
    {
        $even = $this->calculator->fromBase('10.00', '10.025', '0', 3, RoundingMethod::HALF_EVEN);
        $up = $this->calculator->fromBase('10.00', '10.025', '0', 3, RoundingMethod::HALF_UP);

        $this->assertSame('1.002', $even->vatAmount);
        $this->assertSame('1.003', $up->vatAmount);
        $this->assertSame('11.002', $even->finalAmount);
        $this->assertSame('11.003', $up->finalAmount);
    }

    public function test_four_decimal_precision_is_stored_and_bdt_display_stays_at_two(): void
    {
        $price = $this->calculator->fromBase('10.1234', '15', '0.0001', 4, RoundingMethod::HALF_UP);

        $this->assertSame('1.5185', $price->vatAmount);
        $this->assertSame('11.6420', $price->finalAmount);
        $this->assertSame('10.12', $this->calculator->display($price->baseAmount, RoundingMethod::HALF_UP));
        $this->assertSame('1.52', $this->calculator->display($price->vatAmount ?? '0', RoundingMethod::HALF_UP));
        $this->assertSame('11.64', $this->calculator->display($price->finalAmount ?? '0', RoundingMethod::HALF_UP));
        $this->assertTrue($this->holds($price));
    }

    #[DataProvider('directedRounding')]
    public function test_directed_rounding_matches_its_definition(string $method, string $expectedVat): void
    {
        $price = $this->calculator->fromBase('10.00', '10.025', '0', 3, $method);

        $this->assertSame($expectedVat, $price->vatAmount);
        $this->assertTrue($this->holds($price));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function directedRounding(): array
    {
        return [
            'toward zero' => [RoundingMethod::TOWARD_ZERO, '1.002'],
            'away from zero' => [RoundingMethod::AWAY_FROM_ZERO, '1.003'],
            'toward positive' => [RoundingMethod::TOWARD_POSITIVE, '1.003'],
            'toward negative' => [RoundingMethod::TOWARD_NEGATIVE, '1.002'],
        ];
    }

    public function test_reverse_calculation_preserves_the_typed_final_amount(): void
    {
        foreach (['10.00', '10.01', '10.02', '33.33', '99.99', '100.00', '115.05'] as $final) {
            $price = $this->calculator->fromFinal($final, '7.5', '-0.01', 2, RoundingMethod::HALF_UP);

            $this->assertSame($this->atScale($final, 2), $price->finalAmount);
            $this->assertSame('7.5000', $price->rate);
            $this->assertSame('-0.01', $price->adjustmentAmount);
            $this->assertTrue($this->holds($price));
        }
    }

    public function test_a_residual_is_recorded_when_the_percentage_cannot_absorb_the_typed_final(): void
    {
        $found = false;

        for ($poisha = 1; $poisha <= 500; $poisha++) {
            $final = number_format($poisha / 100, 2, '.', '');
            $price = $this->calculator->fromFinal($final, '15', '0', 4, RoundingMethod::HALF_EVEN);

            $this->assertTrue($this->holds($price));

            if (! Decimal::of($price->residualAmount)->isZero()) {
                $found = true;
                $this->assertNotNull($price->vatAmount);
                $this->assertStringContainsString('residual', $this->calculator->explain($price));

                break;
            }
        }

        $this->assertTrue($found, 'Expected at least one final amount to leave a rounding residual.');
    }

    public function test_negative_final_and_oversized_amounts_are_rejected(): void
    {
        try {
            $this->calculator->fromBase('10.00', '0', '-10.01', 2, RoundingMethod::HALF_UP);
            $this->fail('A negative final amount was accepted.');
        } catch (TaxRuleException $exception) {
            $this->assertSame('final_amount', $exception->field);
        }

        try {
            $this->calculator->fromAdjustment('10.00', '0.0000', '0.00', '-10.01', 2, RoundingMethod::HALF_UP);
            $this->fail('An adjustment below zero final was accepted.');
        } catch (TaxRuleException $exception) {
            $this->assertSame('adjustment_amount', $exception->field);
        }
    }

    public function test_adjustment_edit_refuses_to_silently_round_the_base(): void
    {
        $this->expectException(TaxRuleException::class);
        $this->calculator->fromAdjustment('10.001', '5.0000', '0.50', '0', 2, RoundingMethod::HALF_UP);
    }

    public function test_display_uses_the_selected_method_rather_than_always_half_up(): void
    {
        $this->assertSame('10.12', $this->calculator->display('10.125', RoundingMethod::HALF_EVEN));
        $this->assertSame('10.13', $this->calculator->display('10.125', RoundingMethod::HALF_UP));
    }

    private function holds(VatPrice $price): bool
    {
        if ($price->vatAmount === null || $price->finalAmount === null) {
            return false;
        }

        $sum = Decimal::of($price->baseAmount)
            ->plus(Decimal::of($price->vatAmount))
            ->plus(Decimal::of($price->adjustmentAmount));

        return $sum->compare(Decimal::of($price->finalAmount)) === 0;
    }

    private function atScale(string $amount, int $scale): string
    {
        return (string) Decimal::of($amount)->toScale($scale);
    }
}
