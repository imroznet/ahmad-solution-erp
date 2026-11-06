<?php

namespace Tests\Unit;

use App\Tax\TaxRuleCalendar;
use PHPUnit\Framework\TestCase;

class TaxRuleCalendarTest extends TestCase
{
    public function test_both_ends_are_inclusive_and_the_next_day_does_not_overlap(): void
    {
        $calendar = new TaxRuleCalendar;

        $this->assertTrue($calendar->contains('2026-01-01', '2026-01-31', '2026-01-01'));
        $this->assertTrue($calendar->contains('2026-01-01', '2026-01-31', '2026-01-31'));
        $this->assertFalse($calendar->contains('2026-01-01', '2026-01-31', '2026-02-01'));
        $this->assertTrue($calendar->contains('2026-02-01', null, '2026-12-31'));
        $this->assertTrue($calendar->overlaps('2026-01-01', '2026-01-31', '2026-01-31', '2026-02-28'));
        $this->assertFalse($calendar->overlaps('2026-01-01', '2026-01-31', '2026-02-01', null));
        $this->assertSame('2026-01-31', $calendar->dayBefore('2026-02-01'));
    }
}
