<?php

namespace Tests\Unit;

use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationTax;
use App\Tax\VatLineCalculator;
use Tests\TestCase;

class QuotationTaxTest extends TestCase
{
    public function test_excluding_keeps_the_line_sum_and_stores_no_tax(): void
    {
        $priced = $this->tax()->price(['10.00', '5.50'], [], new QuotationCalculator);

        $this->assertSame('excluded', $priced['tax_treatment']);
        $this->assertSame('15.50', $priced['subtotal']);
        $this->assertSame('15.50', $priced['total']);
        $this->assertNull($priced['vat_rate_percent']);
        $this->assertNull($priced['tax_rate_percent']);
        $this->assertNull($priced['vat_amount']);
        $this->assertNull($priced['tax_amount']);
        $this->assertSame('0.00', $priced['adjustment_amount']);
        $this->assertNull($priced['adjustment_reason']);
        $this->assertStringContainsString('Tax is excluded', $priced['tax_notice']);
    }

    public function test_including_adds_typed_vat_and_generic_tax_and_a_reasoned_adjustment(): void
    {
        $priced = $this->tax()->price(['1000.00'], [
            'tax_treatment' => 'included',
            'vat_rate_percent' => '15',
            'tax_rate_percent' => '5',
            'adjustment_amount' => '1.25',
            'adjustment_reason' => 'Poisha',
        ], new QuotationCalculator);

        $this->assertSame('included', $priced['tax_treatment']);
        $this->assertSame('1000.00', $priced['subtotal']);
        $this->assertSame('15.0000', $priced['vat_rate_percent']);
        $this->assertSame('5.0000', $priced['tax_rate_percent']);
        $this->assertSame('150.00', $priced['vat_amount']);
        $this->assertSame('50.00', $priced['tax_amount']);
        $this->assertSame('1.25', $priced['adjustment_amount']);
        $this->assertSame('1201.25', $priced['total']);
    }

    public function test_a_blank_rate_is_not_zero_and_an_explicit_zero_is_stored(): void
    {
        $blank = $this->tax()->price(['80.00'], [
            'tax_treatment' => 'included',
            'vat_rate_percent' => '   ',
            'tax_rate_percent' => '',
        ], new QuotationCalculator);
        $this->assertNull($blank['vat_amount']);
        $this->assertNull($blank['tax_amount']);
        $this->assertSame('80.00', $blank['total']);

        $zero = $this->tax()->price(['80.00'], [
            'tax_treatment' => 'included',
            'vat_rate_percent' => '0',
            'tax_rate_percent' => '0.0000',
        ], new QuotationCalculator);
        $this->assertSame('0.00', $zero['vat_amount']);
        $this->assertSame('0.00', $zero['tax_amount']);
        $this->assertSame('0.0000', $zero['vat_rate_percent']);
        $this->assertSame('80.00', $zero['total']);
    }

    public function test_half_up_rounds_a_tie_away_from_zero(): void
    {
        $priced = $this->tax()->price(['10.05'], [
            'tax_treatment' => 'included',
            'vat_rate_percent' => '10',
        ], new QuotationCalculator);

        $this->assertSame('1.01', $priced['vat_amount']);
        $this->assertSame('11.06', $priced['total']);
    }

    public function test_a_non_zero_adjustment_requires_a_reason_and_cannot_make_the_total_negative(): void
    {
        try {
            $this->tax()->price(['10.00'], [
                'tax_treatment' => 'included',
                'adjustment_amount' => '1.00',
            ], new QuotationCalculator);
            $this->fail('A non-zero adjustment was saved without a reason.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('adjustment_reason', $exception->field);
        }

        try {
            $this->tax()->price(['10.00'], [
                'tax_treatment' => 'included',
                'adjustment_amount' => '-10.01',
                'adjustment_reason' => 'Too large',
            ], new QuotationCalculator);
            $this->fail('A negative total was accepted.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('adjustment_amount', $exception->field);
        }
    }

    private function tax(): QuotationTax
    {
        return new QuotationTax(new VatLineCalculator);
    }
}
