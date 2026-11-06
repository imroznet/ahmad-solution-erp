<?php

namespace Tests\Unit;

use App\Support\Decimal;
use App\Tax\TaxRuleException;
use App\Tax\TaxRuleWindow;
use App\Tax\VatLineCalculator;
use App\Tax\VatPresentation;
use App\Tax\VatRounding;
use PHPUnit\Framework\TestCase;

class VatLineCalculatorTest extends TestCase
{
    public function test_a_blank_rate_does_not_calculate_vat_and_zero_does(): void
    {
        $calculator = new VatLineCalculator;

        $blank = $calculator->calculate('1000.00', null, '0', null, 'forward', 2, VatRounding::HALF_UP);
        $this->assertNull($blank->vat);
        $this->assertNull($blank->final);
        $this->assertSame('1000.00', $blank->base);

        $zero = $calculator->calculate('1000.00', '0', '1.50', null, 'forward', 2, VatRounding::HALF_UP);
        $this->assertSame('0.00', $zero->vat);
        $this->assertSame('1001.50', $zero->final);
        $this->assertSame('1000.00', $zero->base);
    }

    public function test_forward_calculation_uses_the_typed_rate_and_keeps_the_adjustment(): void
    {
        $result = (new VatLineCalculator)->calculate('1000.00', '15', '-2.00', null, 'forward', 2, VatRounding::HALF_UP);

        $this->assertSame('150.00', $result->vat);
        $this->assertSame('1000.00', $result->base);
        $this->assertSame('15', $result->rate);
        $this->assertSame('-2.00', $result->adjustment);
        $this->assertSame('1148.00', $result->final);
        $this->assertTrue(Decimal::of($result->base)->plus(Decimal::of((string) $result->vat))->plus(Decimal::of($result->adjustment))->compare(Decimal::of((string) $result->final)) === 0);
    }

    public function test_editing_the_final_amount_keeps_the_rate_and_adjustment_and_preserves_the_final(): void
    {
        $calculator = new VatLineCalculator;
        $result = $calculator->calculate('1.00', '15', '0', '10.00', 'reverse', 2, VatRounding::TOWARD_ZERO);

        $this->assertSame('15', $result->rate);
        $this->assertSame('0.00', $result->adjustment);
        $this->assertSame('10.00', $result->final);
        $this->assertSame('8.69', $result->base);
        $this->assertSame('1.31', $result->vat);
        $this->assertSame('1.30', $result->forwardVat);
        $this->assertTrue($result->residualApplied);
        $this->assertSame(0, Decimal::of($result->base)->plus(Decimal::of((string) $result->vat))->plus(Decimal::of($result->adjustment))->compare(Decimal::of($result->final)));
    }

    public function test_changing_the_adjustment_does_not_change_the_base_or_the_rate(): void
    {
        $calculator = new VatLineCalculator;
        $first = $calculator->calculate('200.00', '10', '0', null, 'forward', 2, VatRounding::HALF_UP);
        $second = $calculator->calculate('200.00', '10', '0.50', null, 'forward', 2, VatRounding::HALF_UP);

        $this->assertSame($first->base, $second->base);
        $this->assertSame($first->rate, $second->rate);
        $this->assertSame($first->vat, $second->vat);
        $this->assertSame('220.50', $second->final);
    }

    public function test_half_even_and_half_up_diverge_on_a_tie_and_precision_is_kept(): void
    {
        $calculator = new VatLineCalculator;
        $even = $calculator->calculate('10.00', '12.25', '0', null, 'forward', 2, VatRounding::HALF_EVEN);
        $up = $calculator->calculate('10.00', '12.25', '0', null, 'forward', 2, VatRounding::HALF_UP);

        $this->assertSame('1.22', $even->vat);
        $this->assertSame('1.23', $up->vat);

        $finer = $calculator->calculate('10.00', '12.25', '0', null, 'forward', 4, VatRounding::HALF_UP);
        $this->assertSame('1.2250', $finer->vat);
        $this->assertSame('1.23', VatPresentation::poisha($finer->vat));
    }

    public function test_a_negative_final_and_an_unparsed_rate_are_rejected(): void
    {
        $calculator = new VatLineCalculator;

        try {
            $calculator->calculate('10.00', '15', '-20.00', null, 'forward', 2, VatRounding::HALF_UP);
            $this->fail('A negative final was accepted.');
        } catch (TaxRuleException $exception) {
            $this->assertSame('final_amount', $exception->field);
        }

        try {
            $calculator->calculate('10.00', '15.12345', '0', null, 'forward', 2, VatRounding::HALF_UP);
            $this->fail('An over-precise rate was accepted.');
        } catch (TaxRuleException $exception) {
            $this->assertSame('vat_rate', $exception->field);
        }
    }

    public function test_date_windows_are_inclusive_and_a_blank_classification_is_not_a_wildcard(): void
    {
        $this->assertFalse(TaxRuleWindow::overlaps('2026-01-01', '2026-06-30', '2026-07-01', null));
        $this->assertTrue(TaxRuleWindow::overlaps('2026-01-01', '2026-06-30', '2026-06-30', '2026-07-01'));
        $this->assertTrue(TaxRuleWindow::overlaps('2026-01-01', null, '2026-12-31', '2026-12-31'));
        $this->assertSame('', TaxRuleWindow::normalizeClassification('  '));
        $this->assertSame('service', TaxRuleWindow::normalizeClassification(' Service '));
    }
}
