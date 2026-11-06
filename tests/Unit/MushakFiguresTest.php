<?php

namespace Tests\Unit;

use App\Tax\MushakFigures;
use App\Tax\TaxRuleException;
use PHPUnit\Framework\TestCase;

class MushakFiguresTest extends TestCase
{
    public function test_a_typed_percentage_does_not_change_the_typed_vat_amount(): void
    {
        $figures = new MushakFigures;
        $lines = [[
            'description' => 'Network survey',
            'unit' => 'job',
            'quantity' => '2.0000',
            'unit_price' => '1000.00',
            'line_value' => '2000.00',
            'supplementary_duty' => '0.00',
            'vat_rate' => '15.0000',
            'vat_amount' => '1.00',
            'inclusive_value' => '2001.00',
        ]];

        $figures->assertIssue($lines, [
            'total_value' => '2000.00',
            'total_supplementary_duty' => '0.00',
            'total_vat' => '1.00',
            'total_inclusive' => '2001.00',
        ]);

        $this->assertSame('1.00', $lines[0]['vat_amount']);
    }

    public function test_a_blank_amount_is_not_treated_as_zero(): void
    {
        $figures = new MushakFigures;

        $this->expectException(TaxRuleException::class);
        $this->expectExceptionMessage('blank line is not treated as zero');

        $figures->assertDraft([
            [
                'quantity' => '1.0000',
                'unit_price' => '10.00',
                'line_value' => '10.00',
                'supplementary_duty' => null,
                'vat_amount' => null,
                'inclusive_value' => null,
            ],
        ], [
            'total_value' => '10.00',
            'total_supplementary_duty' => null,
            'total_vat' => '0.00',
            'total_inclusive' => null,
        ]);
    }

    public function test_quantity_times_price_must_match_only_when_all_three_are_typed(): void
    {
        $figures = new MushakFigures;
        $figures->assertDraft([
            [
                'quantity' => '2.0000',
                'unit_price' => '10.00',
                'line_value' => null,
                'supplementary_duty' => null,
                'vat_amount' => null,
                'inclusive_value' => null,
            ],
        ], [
            'total_value' => null,
            'total_supplementary_duty' => null,
            'total_vat' => null,
            'total_inclusive' => null,
        ]);

        $this->expectException(TaxRuleException::class);
        $this->expectExceptionMessage('not changed for you');
        $figures->assertDraft([
            [
                'quantity' => '2.0000',
                'unit_price' => '10.00',
                'line_value' => '15.00',
                'supplementary_duty' => null,
                'vat_amount' => null,
                'inclusive_value' => null,
            ],
        ], [
            'total_value' => null,
            'total_supplementary_duty' => null,
            'total_vat' => null,
            'total_inclusive' => null,
        ]);
    }
}
