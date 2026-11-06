<?php

namespace App\Tax;

use App\Support\Decimal;
use App\Support\Money;

/**
 * Checks figures a person typed. It does not fill a blank and it does not read a VAT percentage.
 */
final class MushakFigures
{
    /**
     * @param  list<array<string, ?string>>  $lines
     * @param  array<string, ?string>  $totals
     */
    public function assertDraft(array $lines, array $totals): void
    {
        foreach ($lines as $index => $line) {
            $this->assertLine($line, $index);
        }

        $this->assertTotals($lines, $totals);
    }

    /**
     * @param  list<array<string, ?string>>  $lines
     * @param  array<string, ?string>  $totals
     */
    public function assertIssue(array $lines, array $totals): void
    {
        if ($lines === []) {
            throw new TaxRuleException('lines', 'Add at least one line.');
        }

        foreach ($lines as $index => $line) {
            foreach ([
                'description' => 'Enter the goods or service description.',
                'unit' => 'Enter the unit of supply.',
                'quantity' => 'Enter the quantity. It is not calculated.',
                'unit_price' => 'Enter the unit price. It is not calculated.',
                'line_value' => 'Enter the value excluding taxes. It is not calculated.',
                'supplementary_duty' => 'Enter the supplementary duty, or 0.00. It is not calculated.',
                'vat_amount' => 'Enter the VAT amount, or 0.00. It is not calculated from the percentage.',
                'inclusive_value' => 'Enter the value including duties and taxes. It is not calculated.',
            ] as $key => $message) {
                if (($line[$key] ?? null) === null || $line[$key] === '') {
                    throw new TaxRuleException('lines.'.$index.'.'.$key, $message);
                }
            }

            $this->assertLine($line, $index);
        }

        foreach ([
            'total_value' => 'Enter the total value. It is not calculated.',
            'total_supplementary_duty' => 'Enter the total supplementary duty. It is not calculated.',
            'total_vat' => 'Enter the total VAT amount. It is not calculated.',
            'total_inclusive' => 'Enter the total including duties and taxes. It is not calculated.',
        ] as $key => $message) {
            if (($totals[$key] ?? null) === null) {
                throw new TaxRuleException($key, $message);
            }
        }

        $this->assertTotals($lines, $totals);
    }

    /**
     * @param  array<string, ?string>  $line
     */
    private function assertLine(array $line, int $index): void
    {
        $quantity = $line['quantity'] ?? null;
        $unitPrice = $line['unit_price'] ?? null;
        $lineValue = $line['line_value'] ?? null;

        if ($quantity !== null && $unitPrice !== null && $lineValue !== null) {
            $expected = Decimal::of($quantity)->multipliedBy($unitPrice)->toScale(Money::SCALE);

            if ($expected->compare(Decimal::of($lineValue)->toScale(Money::SCALE)) !== 0) {
                throw new TaxRuleException(
                    'lines.'.$index.'.line_value',
                    'This value does not equal quantity times unit price ('.$expected.'). The figure is not changed for you.',
                );
            }
        }

        $duty = $line['supplementary_duty'] ?? null;
        $vat = $line['vat_amount'] ?? null;
        $inclusive = $line['inclusive_value'] ?? null;

        if ($lineValue !== null && $duty !== null && $vat !== null && $inclusive !== null) {
            $sum = Money::of($lineValue)->plus(Money::of($duty))->plus(Money::of($vat));

            if (! $sum->equals(Money::of($inclusive))) {
                throw new TaxRuleException(
                    'lines.'.$index.'.inclusive_value',
                    'This amount does not equal the value plus supplementary duty plus VAT ('.$sum->amount().'). It is not filled in for you.',
                );
            }
        }
    }

    /**
     * @param  list<array<string, ?string>>  $lines
     * @param  array<string, ?string>  $totals
     */
    private function assertTotals(array $lines, array $totals): void
    {
        $pairs = [
            'total_value' => 'line_value',
            'total_supplementary_duty' => 'supplementary_duty',
            'total_vat' => 'vat_amount',
            'total_inclusive' => 'inclusive_value',
        ];

        foreach ($pairs as $totalKey => $lineKey) {
            $total = $totals[$totalKey] ?? null;

            if ($total === null) {
                continue;
            }

            $sum = Money::zero();

            foreach ($lines as $line) {
                $part = $line[$lineKey] ?? null;

                if ($part === null) {
                    throw new TaxRuleException($totalKey, 'Enter every line amount before this total, or leave the total blank. A blank line is not treated as zero.');
                }

                $sum = $sum->plus(Money::of($part));
            }

            if (! $sum->equals(Money::of($total))) {
                throw new TaxRuleException($totalKey, 'This total does not equal the sum of the lines ('.$sum->amount().'). Type the total. It is not filled in for you.');
            }
        }
    }
}
