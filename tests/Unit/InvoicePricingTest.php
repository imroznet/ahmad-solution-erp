<?php

namespace Tests\Unit;

use App\Documents\InvoicePricing;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class InvoicePricingTest extends TestCase
{
    public function test_split_rounding_stays_within_the_accepted_line_total(): void
    {
        $pricing = new InvoicePricing;
        $first = $pricing->stageAmount('0.5000', '0.01', '1.0000', '0.01', '0.0000', '0.00', 'quantity');
        $second = $pricing->stageAmount('0.5000', '0.01', '1.0000', '0.01', '0.5000', $first, 'quantity');

        $this->assertSame('0.01', $first);
        $this->assertSame('0.00', $second);
        $this->assertSame('0.01', Money::of($first)->plus(Money::of($second))->amount());
    }

    public function test_the_final_slice_absorbs_the_remainder_so_the_line_adds_up(): void
    {
        $pricing = new InvoicePricing;
        $first = $pricing->stageAmount('0.3333', '10.00', '1.0000', '10.00', '0.0000', '0.00', 'quantity');
        $second = $pricing->stageAmount('0.3333', '10.00', '1.0000', '10.00', '0.3333', $first, 'quantity');
        $prior = Money::of($first)->plus(Money::of($second))->amount();
        $third = $pricing->stageAmount('0.3334', '10.00', '1.0000', '10.00', '0.6666', $prior, 'quantity');

        $this->assertSame('3.33', $first);
        $this->assertSame('3.33', $second);
        $this->assertSame('3.34', $third);
        $this->assertSame('10.00', Money::of($prior)->plus(Money::of($third))->amount());
    }
}
