<?php

namespace Tests\Feature;

use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanLine;
use App\Models\FinancialYear;
use App\Models\MushakInvoice;
use App\Models\MushakInvoiceLine;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Unit;
use App\Quotations\QuotationStatus;
use App\Units\UnitModuleSql;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnitListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
        FinancialYear::query()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
        ]);
        $this->seed(UnitSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_units_upgrade_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-units-module.sql'));

        $this->assertIsString($committed);
        $this->assertSame($committed, UnitModuleSql::upgrade());
    }

    public function test_units_are_managed_only_with_the_new_permissions(): void
    {
        $viewer = $this->userWithRole('viewer');
        $sales = $this->userWithRole('sales-executive');
        $admin = $this->userWithRole('administrator');

        $this->actingAs($viewer)->get(route('units.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('units.store'), [
            'name' => 'Dozen',
            'symbol' => 'dz',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertForbidden();

        $this->actingAs($sales)->get(route('units.index'))
            ->assertOk()
            ->assertSee('Pieces')
            ->assertSee('pcs')
            ->assertDontSee('Add unit');

        $this->actingAs($sales)->get(route('dashboard'))
            ->assertSee('Sales')
            ->assertSee(route('quotations.index'), false)
            ->assertSee(route('units.index'), false)
            ->assertSee('Collapse')
            ->assertSee('sidebar = false', false)
            ->assertDontSee(route('users.index'), false)
            ->assertDontSee(route('quotations.approvals'), false)
            ->assertDontSee(route('suppliers.index'), false);

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertDontSee(route('units.index'), false)
            ->assertDontSee(route('quotations.index'), false);

        $this->actingAs($admin)->post(route('units.store'), [
            'name' => 'Dozen',
            'symbol' => 'dz',
            'sort_order' => 15,
            'is_active' => '0',
        ])->assertRedirect(route('units.index'));

        $dozen = Unit::query()->where('symbol', 'dz')->firstOrFail();
        $this->assertFalse($dozen->is_active);

        $this->actingAs($admin)->put(route('units.update', $dozen), [
            'name' => 'Dozen',
            'symbol' => 'dz',
            'sort_order' => 15,
            'is_active' => '1',
        ])->assertRedirect(route('units.index'));
        $this->assertTrue($dozen->fresh()?->is_active);

        $this->actingAs($admin)->delete(route('units.destroy', $dozen))
            ->assertRedirect(route('units.index'));
        $this->assertNull(Unit::query()->where('symbol', 'dz')->first());
        $this->assertNotNull(Unit::query()->where('symbol', 'pcs')->first());

        Unit::query()->where('symbol', 'pcs')->delete();
        $this->seed(UnitSeeder::class);
        $this->assertNull(Unit::query()->where('symbol', 'pcs')->first());
    }

    public function test_line_forms_offer_active_units_and_keep_typed_text(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $accountant = $this->userWithRole('accountant');
        $customer = $this->party();

        Unit::query()->where('symbol', 'dz')->delete();
        Unit::query()->create([
            'name' => 'Dozen',
            'symbol' => 'dz',
            'is_active' => false,
            'sort_order' => 15,
        ]);

        $create = $this->actingAs($sales)->get(route('quotations.create', ['party' => $customer->id]));
        $create->assertOk()
            ->assertSee('Other…')
            ->assertSee('Pieces (pcs)')
            ->assertDontSee('Dozen (dz)');

        $this->actingAs($sales)->post('/quotations', $this->quotationPayload($customer, 'coil'))->assertRedirect();
        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('coil', $quotation->currentRevision?->lines()->firstOrFail()->unit);

        $this->actingAs($sales)->get(route('quotations.edit', $quotation))
            ->assertOk()
            ->assertSee('\u0022unitChoice\u0022:\u0022__other__\u0022', false)
            ->assertSee('\u0022unitCustom\u0022:\u0022coil\u0022', false)
            ->assertSee('Other…')
            ->assertDontSee('Dozen (dz)');

        $this->actingAs($sales)->get(route('quotations.show', $quotation))
            ->assertSee('coil');

        $this->actingAs($sales)->put(route('quotations.update', $quotation), $this->quotationPayload($customer, 'pcs'))
            ->assertRedirect();
        $this->assertSame('pcs', $quotation->fresh()?->currentRevision?->lines()->firstOrFail()->unit);

        $this->accept($sales, $manager, $quotation);

        $this->actingAs($sales)->get(route('challans.create', ['quotation' => $quotation->id]))
            ->assertOk()
            ->assertSee('Other…')
            ->assertSee('Pieces (pcs)')
            ->assertDontSee('Dozen (dz)');

        $line = $quotation->fresh()?->currentRevision?->lines()->firstOrFail();
        $this->assertInstanceOf(QuotationLine::class, $line);

        $this->actingAs($sales)->from(route('challans.create', ['quotation' => $quotation->id]))
            ->post('/challans', [
                'quotation_id' => $quotation->id,
                'challan_date' => '2026-11-06',
                'lines' => [[
                    'quotation_line_id' => $line->id,
                    'quantity' => '1',
                    'unit' => '',
                ]],
            ])->assertSessionHasErrors('lines.0.unit');

        $this->actingAs($sales)->post('/challans', [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-06',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();
        $this->assertSame('pcs', DeliveryChallanLine::query()->firstOrFail()->unit);

        $this->actingAs($sales)->post('/challans', [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-06',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => '1',
                'unit' => '  coil  ',
            ]],
        ])->assertRedirect();
        $challan = DeliveryChallan::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('coil', $challan->lines()->firstOrFail()->unit);
        $this->assertSame('pcs', $line->fresh()?->unit);

        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();

        $this->actingAs($sales)->get(route('invoices.create', ['quotation' => $quotation->id]))
            ->assertOk()
            ->assertSee('Other…')
            ->assertDontSee('Dozen (dz)');

        $this->actingAs($sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-06',
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                'quantity' => '1',
                'unit' => 'box',
            ]],
        ])->assertRedirect();
        $invoice = CommercialInvoice::query()->firstOrFail();
        $this->assertSame('box', $invoice->lines()->firstOrFail()->unit);
        $this->assertSame('coil', $challan->lines()->firstOrFail()->fresh()?->unit);

        $this->actingAs($sales)->post(route('invoices.issue', $invoice))->assertRedirect();
        $this->actingAs($accountant)->post('/tax/mushak', [
            'commercial_invoice_id' => $invoice->id,
        ])->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();
        $mushakLine = $mushak->lines()->firstOrFail();
        $this->assertSame('box', $mushakLine->unit);

        $this->actingAs($accountant)->get(route('mushak.edit', $mushak))
            ->assertOk()
            ->assertSee('Other…')
            ->assertSee('Box (box)')
            ->assertDontSee('Dozen (dz)');

        $this->actingAs($accountant)->put(route('mushak.update', $mushak), $this->mushakPayload($mushakLine, 'set'))
            ->assertRedirect();
        $this->assertSame('set', $mushakLine->fresh()?->unit);
        $this->assertSame('box', $invoice->lines()->firstOrFail()->fresh()?->unit);

        $this->actingAs($accountant)->from(route('mushak.edit', $mushak))
            ->put(route('mushak.update', $mushak), $this->mushakPayload($mushakLine, str_repeat('x', 41)))
            ->assertSessionHasErrors('unit');
        $this->assertSame('set', $mushakLine->fresh()?->unit);
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationPayload(Party $party, string $unit): array
    {
        return [
            'party_id' => $party->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => $unit,
                'quantity' => '2',
                'unit_price' => '1500.00',
            ]],
        ];
    }

    private function accept(mixed $sales, mixed $manager, Quotation $quotation): void
    {
        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.send', $quotation), [
            'recipient_label' => 'Nadia Rahman',
        ])->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.respond', $quotation), [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ])->assertRedirect();
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->fresh()?->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function mushakPayload(MushakInvoiceLine $line, string $unit): array
    {
        return [
            'document_date' => '2026-11-06',
            'destination' => 'Site office, Dhaka',
            'calculation_precision' => 2,
            'rounding_method' => 'half_up',
            'buyer_source' => 'invoice',
            'lines' => [[
                'id' => $line->id,
                'description' => 'Network survey',
                'unit' => $unit,
                'base_amount' => '1500.00',
                'vat_rate' => '15',
                'adjustment_amount' => '0',
                'adjustment_reason' => '',
                'supplementary_duty_amount' => '',
                'final_amount' => '',
                'driver' => 'forward',
            ]],
        ];
    }

    private function party(): Party
    {
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }
}
