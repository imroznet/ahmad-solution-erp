<?php

namespace Tests\Feature;

use App\Documents\CompanySnapshot;
use App\Documents\LetterheadAddresses;
use App\Models\AuditLog;
use App\Models\CompanyAddress;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\QuotationRevision;
use App\Quotations\QuotationPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfAddressDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_flags_change_new_letterheads_and_leave_an_issued_snapshot(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->put('/company/profile', $this->profilePayload())->assertRedirect();
        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload())->assertRedirect();
        $this->actingAs($admin)->post('/company/addresses', $this->addressPayload([
            'address_type' => 'corporate_office',
            'line_1' => '88 Gulshan Avenue',
            'area' => 'Gulshan',
        ]))->assertRedirect();

        $registered = CompanyAddress::query()->where('address_type', 'registered_office')->firstOrFail();
        $corporate = CompanyAddress::query()->where('address_type', 'corporate_office')->firstOrFail();
        $this->assertTrue($registered->show_on_pdf);
        $this->assertTrue($corporate->show_on_pdf);

        $letterhead = app(QuotationPdfRenderer::class)->letterhead();
        $this->assertSame(LetterheadAddresses::LAYOUT_LABELLED, $letterhead['address_layout']);
        $this->assertSame(
            'Registered Office: 12 Green Road, Dhanmondi, Dhanmondi, Dhaka, Dhaka, 1205, Bangladesh',
            $letterhead['address'][0],
        );
        $this->assertStringStartsWith('Corporate Office: 88 Gulshan Avenue', $letterhead['address'][1]);

        $snapshot = app(CompanySnapshot::class)->capture();
        $this->actingAs($admin)->put(route('company.addresses.update', $registered), $this->addressPayload([
            'show_on_pdf' => '0',
        ]))->assertRedirect();
        $this->actingAs($admin)->put(route('company.addresses.update', $corporate), $this->addressPayload([
            'address_type' => 'corporate_office',
            'line_1' => '88 Gulshan Avenue',
            'area' => 'Gulshan',
            'show_on_pdf' => '0',
        ]))->assertRedirect();

        $this->assertSame($snapshot['address_lines'], app(CompanySnapshot::class)->forIssued($snapshot)['address']);
        $this->assertSame([], app(QuotationPdfRenderer::class)->letterhead()['address']);
        $this->assertFalse($registered->fresh()->show_on_pdf);
        $audit = AuditLog::query()->where('event', 'company_address_updated')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertArrayHasKey('show_on_pdf', $audit->new_values);

        $old = app(CompanySnapshot::class)->forIssued([
            'legal_name' => 'Ahmad Solution Ltd.',
            'address_lines' => ['12 Green Road', 'Dhaka'],
        ]);
        $html = view('pdf.letterhead', ['company' => $old, 'logo' => null])->render();
        $this->assertStringContainsString('12 Green Road, Dhaka', $html);
        $this->assertStringNotContainsString('Registered Office:', $html);
    }

    public function test_a_customer_address_flows_on_one_line_and_pairs_post_office_with_code(): void
    {
        $address = new PartyAddress([
            'address_line_1' => 'Village road',
            'district' => 'Tangail',
            'post_office' => 'Sakhipur',
            'postcode' => '1940',
            'country' => 'Bangladesh',
        ]);
        $this->assertSame(
            ['Village road', 'Tangail', 'Sakhipur-1940', 'Bangladesh'],
            $address->lines(),
        );

        $codeOnly = new PartyAddress([
            'address_line_1' => 'Village road',
            'district' => 'Tangail',
            'postcode' => '1940',
        ]);
        $this->assertSame(['Village road', 'Tangail', '1940'], $codeOnly->lines());

        $revision = new QuotationRevision;
        $revision->billing_address = ['lines' => ['Village road', 'Tangail', '1940']];
        $this->assertSame('Village road, Tangail, 1940', $revision->addressText('billing_address'));

        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->post('/customers', [
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'roles' => ['customer'],
        ])->assertRedirect();
        $party = Party::query()->firstOrFail();
        $this->actingAs($admin)->post(route('parties.addresses.store', $party), [
            'address_type' => 'billing',
            'address_line_1' => 'Village road',
            'district' => 'Tangail',
            'post_office' => 'Sakhipur',
            'postcode' => '1940',
            'country' => 'Bangladesh',
            'is_active' => '1',
        ])->assertRedirect();
        $stored = PartyAddress::query()->firstOrFail();
        $this->assertSame('Sakhipur', $stored->post_office);
        $this->assertContains('Sakhipur-1940', $stored->lines());
    }

    public function test_the_pdf_address_upgrade_sql_names_the_migration(): void
    {
        $sql = file_get_contents(base_path('deploy/upgrade-pdf-addresses.sql'));
        $this->assertIsString($sql);
        $this->assertStringContainsString('2026_11_20_100001_add_pdf_address_columns', $sql);
        $this->assertStringContainsString('show_on_pdf', $sql);
        $this->assertStringContainsString('post_office', $sql);
        $this->assertStringContainsString('information_schema.COLUMNS', $sql);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Ahmad Solution Ltd.',
            'trading_name' => '',
            'rjsc_registration_number' => 'C-123456',
            'incorporation_date' => '2018-03-15',
            'business_type' => 'Private company limited by shares',
            'tin' => '123456789012',
            'bin' => '000123456',
            'trade_licence_number' => 'TRAD-9',
            'trade_licence_issuing_authority' => 'Dhaka South City Corporation',
            'trade_licence_issued_on' => '2025-07-01',
            'trade_licence_expires_on' => '2026-06-30',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
            'website' => '',
            'email' => '',
            'phone' => '',
            'description' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'address_type' => 'registered_office',
            'line_1' => '12 Green Road',
            'line_2' => '',
            'area' => 'Dhanmondi',
            'upazila' => 'Dhanmondi',
            'district' => 'Dhaka',
            'division' => 'Dhaka',
            'postcode' => '1205',
            'country' => 'Bangladesh',
            'is_active' => '1',
            'effective_from' => '2020-01-01',
            'effective_until' => '',
            'remarks' => '',
        ], $overrides);
    }
}
