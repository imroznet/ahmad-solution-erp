<?php

namespace Tests\Unit;

use App\Support\DebugMode;
use PHPUnit\Framework\TestCase;

class DebugModeTest extends TestCase
{
    public function test_debug_output_is_limited_to_local_and_testing(): void
    {
        $this->assertTrue(DebugMode::enabled('local', true));
        $this->assertTrue(DebugMode::enabled('testing', true));
        $this->assertFalse(DebugMode::enabled('local', false));
        $this->assertFalse(DebugMode::enabled('staging', true));
        $this->assertFalse(DebugMode::enabled('production', true));
        $this->assertFalse(DebugMode::enabled(null, true));
    }
}
