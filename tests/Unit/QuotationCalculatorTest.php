<?php

namespace Tests\Unit;

use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationStatus;
use Tests\TestCase;

class QuotationCalculatorTest extends TestCase
{
    public function test_line_totals_round_half_away_from_zero_and_the_quotation_total_sums_them(): void
    {
        $calculator = new QuotationCalculator;

        $this->assertSame('10.01', $calculator->lineTotal('1.0005', '10.00'));
        $this->assertSame('0.01', $calculator->lineTotal('0.5000', '0.01'));
        $this->assertSame('2.78', $calculator->lineTotal('2.5000', '1.11'));
        $this->assertSame('1.38', $calculator->lineTotal('1.2500', '1.10'));
        $this->assertSame('1.01', $calculator->lineTotal('1.0000', '1.005'));
        $this->assertSame('3001.00', $calculator->lineTotal('2.0000', '1500.50'));
        $this->assertSame('14.18', $calculator->sum(['10.01', '2.78', '1.38', '0.01']));
    }

    public function test_quantity_and_price_keep_exact_typed_scale(): void
    {
        $calculator = new QuotationCalculator;
        $lines = $calculator->normalizeLines([[
            'description' => 'Cable laying',
            'unit' => 'meter',
            'quantity' => '1.5',
            'unit_price' => '10',
        ]]);

        $this->assertSame('1.5000', $lines[0]['quantity']);
        $this->assertSame('10.00', $lines[0]['unit_price']);
        $this->assertSame('15.00', $lines[0]['line_total']);
        $this->assertSame('1.5', QuotationCalculator::formatQuantity('1.5000'));
        $this->assertSame('1', QuotationCalculator::formatQuantity('1.0000'));
    }

    public function test_extra_precision_zero_quantity_and_negative_price_are_rejected(): void
    {
        $calculator = new QuotationCalculator;

        $this->expectException(QuotationRuleException::class);
        $calculator->normalizeLines([[
            'description' => 'Too precise',
            'unit' => 'job',
            'quantity' => '1.00001',
            'unit_price' => '10.00',
        ]]);
    }

    public function test_a_price_with_more_than_two_decimal_places_is_rejected(): void
    {
        $calculator = new QuotationCalculator;

        try {
            $calculator->normalizeLines([[
                'description' => 'Rate',
                'unit' => 'job',
                'quantity' => '1',
                'unit_price' => '10.005',
            ]]);
            $this->fail('The extra poisha digit should be rejected.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('lines.0.unit_price', $exception->field);
        }
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $calculator = new QuotationCalculator;

        try {
            $calculator->normalizeLines([[
                'description' => 'Nothing',
                'unit' => 'job',
                'quantity' => '0',
                'unit_price' => '10.00',
            ]]);
            $this->fail('A zero quantity should be rejected.');
        } catch (QuotationRuleException $exception) {
            $this->assertSame('lines.0.quantity', $exception->field);
        }
    }

    public function test_status_transitions_follow_the_documented_map(): void
    {
        $this->assertTrue(QuotationStatus::canMove(QuotationStatus::DRAFT, QuotationStatus::SUBMITTED));
        $this->assertTrue(QuotationStatus::canMove(QuotationStatus::SUBMITTED, QuotationStatus::DRAFT));
        $this->assertTrue(QuotationStatus::canMove(QuotationStatus::APPROVED, QuotationStatus::SENT));
        $this->assertTrue(QuotationStatus::canMove(QuotationStatus::SENT, QuotationStatus::ACCEPTED));
        $this->assertFalse(QuotationStatus::canMove(QuotationStatus::DRAFT, QuotationStatus::APPROVED));
        $this->assertFalse(QuotationStatus::canMove(QuotationStatus::ACCEPTED, QuotationStatus::DRAFT));
        $this->assertFalse(QuotationStatus::canMove(QuotationStatus::CANCELLED, QuotationStatus::SENT));
    }
}
