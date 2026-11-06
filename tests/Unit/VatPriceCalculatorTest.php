<?php

namespace Tests\Unit;

use App\Support\Decimal;
use App\Tax\TaxRuleException;
use App\Tax\VatPriceCalculator;
use PHPUnit\Framework\TestCase;

class VatPriceCalculatorTest extends TestCase
{
    public function test_a_blank_percentage_does_not_calculate_and_zero_does(): void
    {
        $calculator = new VatPriceCalculator;

        $blank = $calculator->forward('100.00', '');
        $this->assertFalse($blank->calculated);
        $this->assertNull($blank->rate);
        $this->assertNull($blank->vat);
        $this->assertNull($blank->inclusive);
        $this->assertSame('100.00', $blank->base);

        $spaces = $calculator->forward('100.00', '   ');
        $this->assertFalse($spaces->calculated);

        $zero = $calculator->forward('100.00', '0');
        $this->assertTrue($zero->calculated);
        $this->assertSame('0.0000', $zero->rate);
        $this->assertSame('0.00', $zero->vat);
        $this->assertSame('100.00', $zero->inclusive);
        $this->assertSame('0', $calculator->displayRate('0.0000'));
        $this->assertSame('', $calculator->displayRate(null));
    }

    public function test_forward_calculation_rounds_half_away_from_zero_to_poisha(): void
    {
        $calculator = new VatPriceCalculator;

        $standard = $calculator->forward('100.00', '15');
        $this->assertSame('15.0000', $standard->rate);
        $this->assertSame('15.00', $standard->vat);
        $this->assertSame('115.00', $standard->inclusive);

        $fraction = $calculator->forward('10.00', '7.5');
        $this->assertSame('0.75', $fraction->vat);
        $this->assertSame('10.75', $fraction->inclusive);

        $tie = $calculator->forward('1.00', '2.5');
        $this->assertSame('0.03', $tie->vat);
        $this->assertSame('1.03', $tie->inclusive);

        $belowTie = $calculator->forward('10.01', '15');
        $this->assertSame('1.50', $belowTie->vat);
        $this->assertSame('11.51', $belowTie->inclusive);
    }

    public function test_editing_the_inclusive_amount_preserves_it_and_keeps_the_rate(): void
    {
        $calculator = new VatPriceCalculator;
        $result = $calculator->reverse('100.00', '15');

        $this->assertSame('15.0000', $result->rate);
        $this->assertSame('86.96', $result->base);
        $this->assertSame('13.04', $result->vat);
        $this->assertSame('100.00', $result->inclusive);
        $this->assertSame('100.00', (string) Decimal::of($result->base)->plus(Decimal::of((string) $result->vat))->toScale(2));

        $zero = $calculator->reverse('80.00', '0');
        $this->assertSame('80.00', $zero->base);
        $this->assertSame('0.00', $zero->vat);
        $this->assertSame('80.00', $zero->inclusive);
    }

    public function test_reverse_results_add_back_to_the_entered_inclusive_amount(): void
    {
        $calculator = new VatPriceCalculator;

        foreach (['1.00', '10.00', '10.01', '33.33', '99.99', '100.00', '1234.56'] as $inclusive) {
            foreach (['0', '1', '7.5', '15', '15.5'] as $rate) {
                $result = $calculator->reverse($inclusive, $rate);

                $this->assertSame(
                    $inclusive,
                    (string) Decimal::of($result->base)->plus(Decimal::of((string) $result->vat))->toScale(2),
                    $inclusive.' at '.$rate,
                );
            }
        }
    }

    public function test_changing_the_rate_recalculates_from_the_base(): void
    {
        $calculator = new VatPriceCalculator;
        $reversed = $calculator->reverse('100.00', '15');
        $changed = $calculator->forward($reversed->base, '10');

        $this->assertSame('86.96', $changed->base);
        $this->assertSame('8.70', $changed->vat);
        $this->assertSame('95.66', $changed->inclusive);
        $this->assertNotSame('100.00', $changed->inclusive);
    }

    public function test_quantity_times_unit_price_becomes_the_base(): void
    {
        $calculator = new VatPriceCalculator;
        $result = $calculator->fromQuantity('2', '1500.50', '15');

        $this->assertSame('3001.00', $result->base);
        $this->assertSame('450.15', $result->vat);
        $this->assertSame('3451.15', $result->inclusive);
        $this->assertSame('components', $result->driver);
    }

    public function test_invalid_percentages_and_money_are_rejected_without_a_default(): void
    {
        $calculator = new VatPriceCalculator;

        foreach (['15.12345', '-1', '1000', '15%', 'fifteen'] as $rate) {
            try {
                $calculator->rate($rate);
                $this->fail('Expected '.$rate.' to be rejected.');
            } catch (TaxRuleException $exception) {
                $this->assertSame('vat_rate', $exception->field);
            }
        }

        $this->expectException(TaxRuleException::class);
        $calculator->money('10.001', 'base_amount', 'Base amount');
    }

    public function test_reverse_without_a_percentage_does_not_invent_one(): void
    {
        $this->expectException(TaxRuleException::class);
        (new VatPriceCalculator)->reverse('100.00', '');
    }
}
