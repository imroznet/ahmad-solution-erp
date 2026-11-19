<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Quotations\QuotationPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_excluding_quotation_stores_the_line_sum_and_the_form_does_not_prefill_a_rate(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $customer = $this->party();

        $this->actingAs($sales)->get(route('quotations.create', ['party' => $customer->id]))
            ->assertOk()
            ->assertSee('VAT excluding')
            ->assertSee('VAT including')
            ->assertSee('Tax %')
            ->assertSee('name="vat_rate_percent"', false)
            ->assertDontSee('value="15"', false);

        $this->actingAs($sales)->post('/quotations', $this->payload($customer))->assertRedirect();
        $revision = Quotation::query()->firstOrFail()->currentRevision;
        $this->assertInstanceOf(QuotationRevision::class, $revision);
        $this->assertSame('excluded', $revision->tax_treatment);
        $this->assertSame('3001.00', $revision->subtotal);
        $this->assertSame('3001.00', $revision->total);
        $this->assertNull($revision->vat_amount);
        $this->assertNull($revision->tax_amount);
        $this->assertSame('0.00', $revision->adjustment_amount);
        $this->assertStringContainsString('Tax is excluded', (string) $revision->tax_notice);

        $html = view('quotations.pdf', [
            'quotation' => $revision->quotation,
            'revision' => $revision->load('lines'),
            'company' => app(QuotationPdfRenderer::class)->letterhead(),
            'logo' => null,
        ])->render();
        $this->assertStringContainsString('VAT exclusive', $html);
        $this->assertStringContainsString('Not calculated', $html);
        $this->assertStringNotContainsString('450.15', $html);
    }

    public function test_an_including_quotation_calculates_on_the_server_and_prints_the_breakdown(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $viewer = $this->userWithRole('viewer');
        $customer = $this->party();
        $included = $this->payload($customer, [
            'tax_treatment' => 'included',
            'vat_rate_percent' => '15',
            'tax_rate_percent' => '5',
            'adjustment_amount' => '-1.00',
            'adjustment_reason' => 'Round figure',
            'lines' => [[
                'description' => 'Panel',
                'unit' => 'set',
                'quantity' => '2',
                'unit_price' => '500.00',
            ]],
        ]);

        $this->post(route('quotations.calculate'), $included)->assertRedirect(route('login'));
        $this->actingAs($viewer)->postJson(route('quotations.calculate'), $included)->assertForbidden();

        $preview = $this->actingAs($sales)->postJson(route('quotations.calculate'), $included);
        $preview->assertOk();
        $preview->assertJsonPath('subtotal', '1000.00');
        $preview->assertJsonPath('vat_amount', '150.00');
        $preview->assertJsonPath('tax_amount', '50.00');
        $preview->assertJsonPath('total', '1199.00');
        $preview->assertJsonMissingPath('vat_rate_percent');

        $blank = $this->actingAs($sales)->postJson(route('quotations.calculate'), $this->payload($customer, [
            'tax_treatment' => 'included',
            'vat_rate_percent' => '',
            'tax_rate_percent' => '',
        ]));
        $blank->assertOk();
        $blank->assertJsonPath('vat_amount', null);
        $blank->assertJsonPath('tax_display', 'Not calculated');
        $blank->assertJsonPath('total', '3001.00');

        $this->actingAs($sales)->post('/quotations', $included)->assertRedirect();
        $revision = Quotation::query()->firstOrFail()->currentRevision;
        $this->assertInstanceOf(QuotationRevision::class, $revision);
        $this->assertSame('included', $revision->tax_treatment);
        $this->assertSame('1000.00', $revision->subtotal);
        $this->assertSame('150.00', $revision->vat_amount);
        $this->assertSame('50.00', $revision->tax_amount);
        $this->assertSame('-1.00', $revision->adjustment_amount);
        $this->assertSame('1199.00', $revision->total);
        $this->assertSame('15.0000', $revision->vat_rate_percent);
        $this->assertSame('Round figure', $revision->adjustment_reason);

        $this->actingAs($sales)->put(route('quotations.update', $revision->quotation), $this->payload($customer, [
            'tax_treatment' => 'included',
            'adjustment_amount' => '2.00',
        ]))->assertSessionHasErrors('adjustment_reason');
        $this->assertSame('1199.00', $revision->fresh()?->total);

        $edit = $this->actingAs($sales)->get(route('quotations.edit', $revision->quotation));
        $edit->assertOk();
        $edit->assertSee('value="15"', false);
        $edit->assertDontSee('value="15.0000"', false);

        $quotation = $revision->quotation;
        $this->assertInstanceOf(Quotation::class, $quotation);
        $html = view('quotations.pdf', [
            'quotation' => $quotation,
            'revision' => $revision->fresh()?->load('lines'),
            'company' => app(QuotationPdfRenderer::class)->letterhead(),
            'logo' => null,
        ])->render();
        $this->assertStringContainsString('VAT inclusive', $html);
        $this->assertStringContainsString('15%', $html);
        $this->assertStringContainsString('5%', $html);
        $this->assertStringContainsString('Round figure', $html);
        $this->assertStringContainsString('letterhead-name', $html);
    }

    private function party(): Party
    {
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'email' => 'accounts@padma.example',
            'phone' => '01711000001',
        ]);
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Party $party, array $overrides = []): array
    {
        return array_merge([
            'party_id' => $party->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
            ]],
        ], $overrides);
    }
}
