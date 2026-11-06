<?php

namespace Tests\Feature;

use App\Models\AitRecord;
use App\Models\CompanyProfile;
use App\Models\MushakDocument;
use App\Models\Party;
use App\Models\VdsRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WithholdingRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_vds_and_ait_stay_separate_from_vat_in_both_directions(): void
    {
        $manager = $this->userWithRole('accounts-manager');
        $sales = $this->userWithRole('sales-executive');
        $customer = $this->party('customer', 'Customer Withholder Ltd.');
        $supplier = $this->party('supplier', 'Supplier Payee Ltd.');
        $this->company();
        $this->actingAs($manager)->post('/mushak', [
            'buyer_source' => 'party',
            'party_id' => $customer->id,
            'destination' => 'Dhaka',
            'responsible_name' => 'Karim Ahmed',
            'responsible_designation' => 'Manager',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '1',
                'unit_price' => '100.00',
                'line_value' => '100.00',
                'supplementary_duty' => '0.00',
                'vat_amount' => '5.00',
                'inclusive_value' => '105.00',
            ]],
            'total_value' => '100.00',
            'total_supplementary_duty' => '0.00',
            'total_vat' => '5.00',
            'total_inclusive' => '105.00',
        ])->assertRedirect();
        $document = MushakDocument::query()->firstOrFail();
        $this->actingAs($manager)->post(route('mushak.issue', $document))->assertRedirect();

        $this->actingAs($sales)->post('/vds-records', $this->record($customer->id, 'withheld_from_us'))->assertForbidden();
        $this->actingAs($manager)->post('/vds-records', $this->record($customer->id, 'withheld_from_us', [
            'mushak_document_id' => $document->id,
            'deducted_amount' => '7.50',
        ]))->assertRedirect();
        $this->actingAs($manager)->post('/vds-records', $this->record($supplier->id, 'deducted_by_us', [
            'deducted_amount' => '3.00',
            'counterparty_source' => 'manual',
            'party_id' => null,
            'counterparty_name' => 'Typed Supplier',
        ]))->assertRedirect();

        $this->actingAs($manager)->post('/ait-records', $this->record($customer->id, 'withheld_from_us', [
            'deducted_amount' => '4.00',
        ]))->assertRedirect();
        $this->actingAs($manager)->post('/ait-records', $this->record($supplier->id, 'deducted_by_us', [
            'deducted_amount' => '2.00',
        ]))->assertRedirect();

        $this->assertSame(2, VdsRecord::query()->count());
        $this->assertSame(2, AitRecord::query()->count());
        $this->assertTrue(Schema::hasColumn('vds_records', 'deducted_amount'));
        $this->assertFalse(Schema::hasColumn('vds_records', 'vat_amount'));
        $this->assertFalse(Schema::hasColumn('ait_records', 'vat_amount'));
        $this->assertSame('5.00', $document->fresh()->nullableDecimal('total_vat'));
        $this->assertSame(2, Party::query()->count());

        $page = $this->actingAs($manager)->get('/vds-records');
        $page->assertOk();
        $page->assertSee('Mushak 6.6 is not generated');
        $page->assertSee('not output VAT');
        $this->actingAs($manager)->get('/ait-records')->assertOk()->assertSee('not VAT');
        $manual = VdsRecord::query()->where('counterparty_source', 'manual')->firstOrFail();
        $this->actingAs($manager)->get(route('vds.show', $manual))->assertOk()->assertSee('No party was created');

        $this->actingAs($sales)->post(route('vds.void', VdsRecord::query()->firstOrFail()), [
            'void_reason' => 'No',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('ait.void', AitRecord::query()->firstOrFail()), [
            'void_reason' => 'Entered against the wrong payment.',
        ])->assertRedirect();
        $this->assertSame('5.00', $document->fresh()->nullableDecimal('total_vat'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function record(int $partyId, string $direction, array $overrides = []): array
    {
        return array_merge([
            'direction' => $direction,
            'counterparty_source' => 'party',
            'party_id' => $partyId,
            'underlying_reference' => 'Invoice CI-2026-0001',
            'basis_amount' => '100.00',
            'deducted_amount' => '5.00',
            'record_date' => '2026-11-06',
            'certificate_number' => 'CERT-1',
            'treasury_reference' => 'TR-1',
        ], $overrides);
    }

    private function party(string $role, string $name): Party
    {
        $party = Party::query()->create([
            'legal_name' => $name,
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $party->roles()->create(['role' => $role]);

        return $party;
    }

    private function company(): void
    {
        $profile = CompanyProfile::query()->create([
            'legal_name' => 'Ahmad Solution Ltd.',
            'bin' => '000123456-0001',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
        ]);
        $profile->addresses()->create([
            'address_type' => 'registered_office',
            'line_1' => '12 Dilkusha',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_active' => true,
        ]);
    }
}
