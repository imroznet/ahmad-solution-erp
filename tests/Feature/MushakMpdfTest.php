<?php

namespace Tests\Feature;

use App\Documents\MpdfFactory;
use App\Tax\MushakSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MushakMpdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpdf_renders_a_mushak_pdf(): void
    {
        $binary = app(MpdfFactory::class)->render('tax.mushak.pdf', app(MushakSample::class)->viewData());

        $this->assertStringStartsWith('%PDF-', $binary);
        $this->assertGreaterThan(1000, strlen($binary));
    }
}
