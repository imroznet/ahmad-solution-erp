<?php

namespace Tests\Unit;

use App\Tax\TaxRuleWindow;
use PHPUnit\Framework\TestCase;

class TaxRuleWindowTest extends TestCase
{
    public function test_inclusive_boundaries_and_the_following_day(): void
    {
        $windows = new TaxRuleWindow;

        $this->assertFalse($windows->overlaps('2026-01-01', '2026-06-30', '2026-07-01', null));
        $this->assertTrue($windows->overlaps('2026-01-01', '2026-06-30', '2026-06-30', '2026-06-30'));
        $this->assertTrue($windows->overlaps('2026-01-01', null, '2026-07-01', null));
        $this->assertTrue($windows->contains('2026-01-01', '2026-06-30', '2026-06-30'));
        $this->assertFalse($windows->contains('2026-01-01', '2026-06-30', '2026-07-01'));
        $this->assertFalse($windows->contains('2026-01-01', '2026-06-30', '2025-12-31'));
        $this->assertTrue($windows->contains('2026-07-01', null, '2026-12-31'));
    }
}
