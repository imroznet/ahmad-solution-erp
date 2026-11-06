<?php

namespace Tests\Feature;

use App\Documents\DocumentStatus;
use App\Models\AitRecord;
use App\Models\AuditLog;
use App\Models\CommercialInvoice;
use App\Models\CompanyAddress;
use App\Models\CompanyProfile;
use App\Models\DeliveryChallan;
use App\Models\Mushak63Document;
use App\Models\Mushak63Line;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Role;
use App\Models\TaxRule;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Models\VdsRecord;
use App\Quotations\QuotationStatus;
use App\Tax\MushakPdfRenderer;
use App\Tax\TaxModuleInstaller;
use App\Tax\TaxModuleSql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class TaxDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_no_statutory_rate_is_seeded_and_rules_do_not_overlap_or_rewrite_history(): void
    {
        $this->assertSame(0, TaxRule::query()->count());
        $this->assertSame(0, TaxRuleVersion::query()->count());

        $preparer = $this->userWithRole('administrator');
        $approver = $this->userWithRole('accounts-manager');
        $this->actingAs($preparer)->post('/tax/rules', $this->rulePayload([
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'rate_percent' => '15',
        ]))->assertRedirect();
        $rule = TaxRule::query()->firstOrFail();
        $first = $rule->versions()->firstOrFail();
        $this->assertSame('draft', $first->approval_status);
        $this->assertSame('15.0000', $first->nullableDecimal('rate_percent'));

        $this->actingAs($preparer)->post(route('tax-rules.versions.approve', [$rule, $first]))
            ->assertSessionHasErrors('approval');
        $this->actingAs($approver)->post(route('tax-rules.versions.approve', [$rule, $first]))->assertRedirect();
        $this->assertSame('approved', $first->fresh()?->approval_status);

        $this->actingAs($preparer)->post(route('tax-rules.versions.store', $rule), $this->rulePayload([
            'effective_from' => '2026-06-30',
            'effective_to' => '2026-12-31',
            'rate_percent' => '7.5',
        ]))->assertRedirect();
        $overlap = TaxRuleVersion::query()->where('version_number', 2)->firstOrFail();
        $this->actingAs($approver)->post(route('tax-rules.versions.approve', [$rule, $overlap]))
            ->assertSessionHasErrors('effective_from');

        $this->actingAs($preparer)->post(route('tax-rules.versions.store', $rule), $this->rulePayload([
            'effective_from' => '2026-07-01',
            'rate_percent' => '5',
        ]))->assertRedirect();
        $next = TaxRuleVersion::query()->where('version_number', 3)->firstOrFail();
        $this->actingAs($approver)->post(route('tax-rules.versions.approve', [$rule, $next]))->assertRedirect();
        $this->assertSame('15.0000', $first->fresh()?->nullableDecimal('rate_percent'));
        $this->assertSame('5.0000', $next->fresh()?->nullableDecimal('rate_percent'));

        $this->expectException(LogicException::class);
        $first->fresh()?->update(['rate_percent' => '1.0000']);
    }

    public function test_preview_reports_no_match_and_does_not_apply_a_rate(): void
    {
        $viewer = $this->userWithRole('accounts-manager');
        $this->actingAs($viewer)->get('/tax/rules?tax_type=output_vat&on=2026-11-06')
            ->assertOk()
            ->assertSee('No approved Output VAT rule covers 2026-11-06')
            ->assertSee('No percentage is applied');

        $preparer = $this->userWithRole('administrator');
        $this->actingAs($preparer)->post('/tax/rules', $this->rulePayload([
            'effective_from' => '2026-11-06',
            'effective_to' => '2026-11-06',
        ]))->assertRedirect();
        $rule = TaxRule::query()->firstOrFail();
        $version = $rule->versions()->firstOrFail();
        $this->actingAs($viewer)->post(route('tax-rules.versions.approve', [$rule, $version]))->assertRedirect();

        $this->actingAs($viewer)->get('/tax/rules?tax_type=output_vat&on=2026-11-06')
            ->assertOk()
            ->assertSee('None of them is applied automatically');
        $this->actingAs($viewer)->get('/tax/rules?tax_type=output_vat&on=2026-11-05')
            ->assertOk()
            ->assertSee('No approved Output VAT rule covers 2026-11-05');
        $this->actingAs($viewer)->get('/tax/rules?tax_type=vds&on=2026-11-06')
            ->assertOk()
            ->assertSee('No approved VDS rule covers 2026-11-06');
    }

    public function test_mushak_calculates_both_ways_and_leaves_the_commercial_invoice_untaxed(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $beforeTotal = $invoice->decimalAttribute('total');
        $parties = Party::query()->count();

        $this->actingAs($accountant)->get(route('mushak.create', ['invoice' => $invoice->id]))
            ->assertOk()
            ->assertDontSee('value="15"', false)
            ->assertSee('starts blank');

        $this->actingAs($sales)->post('/tax/mushak', $this->mushakPayload($invoice, '15'))->assertForbidden();

        $this->actingAs($accountant)->post('/tax/mushak', $this->mushakPayload($invoice, ''))
            ->assertRedirect();
        $draft = Mushak63Document::query()->firstOrFail();
        $line = $draft->lines()->firstOrFail();
        $this->assertNull($line->nullableDecimal('vat_rate_percent'));
        $this->assertNull($line->nullableDecimal('vat_amount'));
        $this->assertNull($draft->nullableDecimal('vat_total'));
        $this->assertSame('review_required', $draft->review_status);
        $this->assertSame($parties, Party::query()->count());

        $this->actingAs($accountant)->post(route('mushak.issue', $draft))->assertSessionHasErrors('lines');
        $this->assertSame(DocumentStatus::DRAFT, $draft->fresh()?->status);
        $this->assertNull($draft->fresh()?->number);

        $this->actingAs($accountant)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, '15', [
            'document_id' => $draft->id,
            'driver' => 'base',
        ]))->assertRedirect();
        $line = $draft->fresh()?->lines()->firstOrFail();
        $this->assertInstanceOf(Mushak63Line::class, $line);
        $this->assertSame('15.0000', $line->nullableDecimal('vat_rate_percent'));
        $this->assertSame('450.15', $line->nullableDecimal('vat_amount'));
        $this->assertSame('3451.15', $line->nullableDecimal('inclusive_amount'));
        $this->assertSame((int) $accountant->id, (int) $line->rate_entered_by);
        $this->assertSame('3001.00', $draft->fresh()?->decimalAttribute('base_total'));
        $this->assertSame('excluded', $invoice->fresh()?->tax_treatment);
        $this->assertSame($beforeTotal, $invoice->fresh()?->decimalAttribute('total'));

        $reversed = $this->actingAs($accountant)->postJson(route('mushak.calculate'), $this->mushakPayload($invoice, '15', [
            'document_id' => $draft->id,
            'driver' => 'inclusive',
            'inclusive_amount' => '100.00',
            'base_amount' => '3001.00',
        ]))->assertOk()->json();
        $this->assertSame('86.96', $reversed['lines'][0]['base']);
        $this->assertSame('13.04', $reversed['lines'][0]['vat']);
        $this->assertSame('100.00', $reversed['lines'][0]['inclusive']);

        $changed = $this->actingAs($accountant)->postJson(route('mushak.calculate'), $this->mushakPayload($invoice, '10', [
            'document_id' => $draft->id,
            'driver' => 'rate',
            'base_amount' => '86.96',
            'inclusive_amount' => '100.00',
        ]))->assertOk()->json();
        $this->assertSame('86.96', $changed['lines'][0]['base']);
        $this->assertSame('8.70', $changed['lines'][0]['vat']);
        $this->assertSame('95.66', $changed['lines'][0]['inclusive']);

        $this->actingAs($accountant)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, '0', [
            'document_id' => $draft->id,
        ]))->assertRedirect();
        $this->assertSame('0.00', $draft->fresh()?->lines()->firstOrFail()->nullableDecimal('vat_amount'));
        $this->assertSame('3001.00', $draft->fresh()?->nullableDecimal('inclusive_total'));
    }

    public function test_issue_snapshots_the_company_and_blocks_a_missing_bin(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $this->actingAs($accountant)->post('/tax/mushak', $this->mushakPayload($invoice, '15', [
            'buyer_source' => 'manual',
            'buyer_legal_name' => 'One-off Buyer Ltd.',
            'buyer_address' => "House 2\nDhaka",
            'party_id' => '',
        ]))->assertRedirect();
        $draft = Mushak63Document::query()->firstOrFail();
        $this->assertSame(1, Party::query()->count());
        $this->assertNull($draft->party_id);

        $this->actingAs($accountant)->from(route('mushak.show', $draft))
            ->post(route('mushak.issue', $draft))
            ->assertSessionHasErrors('company');
        $this->assertSame(DocumentStatus::DRAFT, $draft->fresh()?->status);

        $profile = CompanyProfile::query()->create([
            'legal_name' => 'Issued Letterhead Ltd.',
            'bin' => '000123456-0101',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
        ]);
        CompanyAddress::query()->create([
            'company_profile_id' => $profile->id,
            'address_type' => 'registered_office',
            'line_1' => '12 Tax Road',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($accountant)->post(route('mushak.issue', $draft))->assertRedirect();
        $draft->refresh();
        $this->assertSame('M63-2026-0001', $draft->number);
        $this->assertSame(DocumentStatus::ISSUED, $draft->status);
        $this->assertSame('000123456-0101', $draft->company_snapshot['bin'] ?? null);
        $this->assertSame('Issued Letterhead Ltd.', $draft->company_snapshot['legal_name'] ?? null);

        CompanyProfile::query()->update(['legal_name' => 'Later Letterhead Ltd.', 'bin' => '999']);
        $customer->update(['legal_name' => 'Renamed Customer Ltd.']);
        $html = view('tax.mushak.pdf', app(MushakPdfRenderer::class)->data($draft->fresh() ?? $draft))->render();
        $this->assertStringContainsString('Issued Letterhead Ltd.', $html);
        $this->assertStringContainsString('000123456-0101', $html);
        $this->assertStringContainsString('One-off Buyer Ltd.', $html);
        $this->assertStringContainsString('15%', $html);
        $this->assertStringContainsString('450.15', $html);
        $this->assertStringContainsString('does not decide whether the percentage is legally applicable', $html);
        $this->assertStringNotContainsString('Later Letterhead Ltd.', $html);
        $this->assertStringNotContainsString('SECRET-NOTE', $html);
        $this->assertStringNotContainsString('Renamed Customer Ltd.', $html);

        $pdf = $this->actingAs($accountant)->get(route('mushak.pdf', $draft));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertMatchesRegularExpression('/private/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'mushak_63_pdf_downloaded']);
        $this->actingAs($sales)->get(route('mushak.pdf', $draft))->assertForbidden();

        $this->actingAs($accountant)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, '10'))
            ->assertForbidden();
        $this->assertSame('15.0000', $draft->fresh()?->lines()->firstOrFail()->nullableDecimal('vat_rate_percent'));

        try {
            $draft->fresh()?->update(['buyer_legal_name' => 'Changed Buyer']);
            $this->fail('An issued Mushak 6.3 accepted an edit.');
        } catch (LogicException) {
            $this->assertSame('One-off Buyer Ltd.', $draft->fresh()?->buyer_legal_name);
        }

        $this->assertFalse($draft->fresh()?->delete());
    }

    public function test_vds_and_ait_stay_out_of_vat_and_a_void_frees_the_invoice(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        $this->companyWithBin();
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $this->actingAs($accountant)->post('/tax/mushak', $this->mushakPayload($invoice, '15'))->assertRedirect();
        $document = Mushak63Document::query()->firstOrFail();
        $this->actingAs($accountant)->post(route('mushak.issue', $document))->assertRedirect();
        $vat = $document->fresh()?->nullableDecimal('vat_total');

        $this->actingAs($accountant)->post('/tax/vds', [
            'direction' => 'withheld_from_us',
            'record_date' => '2026-11-06',
            'party_id' => $customer->id,
            'taxable_basis' => '3001.00',
            'tax_amount' => '150.05',
            'mushak_63_document_id' => $document->id,
            'certificate_reference' => 'VDS-1',
        ])->assertRedirect();
        $this->actingAs($accountant)->post('/tax/ait', [
            'direction' => 'deducted_by_us',
            'record_date' => '2026-11-06',
            'counterparty_name' => 'Typed Supplier',
            'taxable_basis' => '500.00',
            'tax_amount' => '25.00',
            'mushak_63_document_id' => $document->id,
        ])->assertRedirect();

        $this->assertSame($vat, $document->fresh()?->nullableDecimal('vat_total'));
        $this->assertSame('150.05', VdsRecord::query()->firstOrFail()->decimalAttribute('tax_amount'));
        $this->assertSame('25.00', AitRecord::query()->firstOrFail()->decimalAttribute('tax_amount'));
        $this->assertSame(1, Party::query()->count());
        $this->actingAs($accountant)->get(route('mushak.show', $document))
            ->assertOk()
            ->assertSee('not included in VAT');

        $this->actingAs($accountant)->post('/tax/mushak', $this->mushakPayload($invoice, '15'))
            ->assertSessionHasErrors('commercial_invoice_id');
        $this->actingAs($accountant)->post(route('mushak.void', $document), ['void_reason' => 'Wrong buyer'])->assertForbidden();
        $this->actingAs($manager)->post(route('mushak.void', $document), ['void_reason' => 'Wrong buyer'])->assertRedirect();
        $this->assertSame(DocumentStatus::VOIDED, $document->fresh()?->status);
        $this->assertSame('M63-2026-0001', $document->fresh()?->number);
        $this->assertSame(DocumentStatus::ISSUED, $invoice->fresh()?->status);

        $this->actingAs($accountant)->post('/tax/mushak', $this->mushakPayload($invoice, '10'))->assertRedirect();
        $this->assertSame(2, Mushak63Document::query()->count());
        $this->assertSame(1, Mushak63Document::query()->where('status', DocumentStatus::DRAFT)->count());
    }

    public function test_permissions_and_upgrade_sql_keep_custom_grants_and_separate_the_types(): void
    {
        $viewer = $this->userWithRole('viewer');
        $sales = $this->userWithRole('sales-executive');
        $this->actingAs($viewer)->get('/tax/mushak')->assertForbidden();
        $this->actingAs($sales)->get('/tax/mushak')->assertOk();
        $this->actingAs($sales)->get('/tax/rules')->assertForbidden();
        $this->actingAs($sales)->get('/tax/vds')->assertForbidden();

        $accountant = $this->userWithRole('accountant');
        $this->assertTrue($accountant->hasPermission('mushak.issue'));
        $this->assertFalse($accountant->hasPermission('mushak.void'));
        $this->assertFalse($accountant->hasPermission('tax_rules.approve'));
        $manager = $this->userWithRole('accounts-manager');
        $this->assertTrue($manager->hasPermission('tax_rules.approve'));
        $this->assertTrue($manager->hasPermission('vds.void'));
        $this->assertTrue($manager->hasPermission('ait.void'));

        $sql = TaxModuleSql::upgrade();
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `mushak_63_documents`', $sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `vds_records`', $sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `ait_records`', $sql);
        $this->assertStringNotContainsString('DELETE FROM', $sql);
        $this->assertStringNotContainsString('15.0000', $sql);
        $this->assertSame($sql, file_get_contents(base_path('deploy/upgrade-tax-module.sql')));

        $custom = Permission::query()->where('slug', 'mushak.view')->firstOrFail();
        $documentManager = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'document-manager'))->first();
        $this->assertNull($documentManager);
        $role = Role::query()->where('slug', 'document-manager')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$custom->id]);
        app(TaxModuleInstaller::class)->install();
        $this->assertTrue($role->fresh()?->permissions()->where('slug', 'mushak.view')->exists());
        $this->assertFalse($role->fresh()?->permissions()->where('slug', 'mushak.issue')->exists());
        $this->assertSame(0, AuditLog::query()->where('event', 'like', '%password%')->count());
        $this->assertSame(17, DB::table('permissions')->whereIn('permission_group', ['Tax rules', 'Mushak', 'VDS', 'AIT'])->count());
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Party}
     */
    private function team(): array
    {
        $customer = $this->party();

        return [
            $this->userWithRole('sales-executive'),
            $this->userWithRole('accounts-manager'),
            $this->userWithRole('accountant'),
            $customer,
        ];
    }

    private function party(): Party
    {
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'bin' => '000999888-0202',
        ]);
        $party->roles()->create(['role' => 'customer']);
        PartyAddress::query()->create([
            'party_id' => $party->id,
            'address_type' => 'billing',
            'address_line_1' => '4 River Road',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_default_billing' => true,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        return $party;
    }

    private function companyWithBin(): void
    {
        CompanyProfile::query()->create([
            'legal_name' => 'Ahmad Solution Ltd.',
            'bin' => '000123456-0101',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
        ]);
    }

    private function issuedInvoice(User $sales, User $manager, Party $customer): CommercialInvoice
    {
        $this->actingAs($sales)->post('/quotations', [
            'party_id' => $customer->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
            ]],
        ])->assertRedirect();
        $quotation = Quotation::query()->firstOrFail();
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
        $quotation->refresh();
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->status);
        $line = $quotation->currentRevision?->lines()->first();
        $this->assertInstanceOf(QuotationLine::class, $line);

        $this->actingAs($sales)->post('/challans', [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-04',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => '2',
            ]],
        ])->assertRedirect();
        $challan = DeliveryChallan::query()->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();
        $challanLine = $challan->fresh()?->lines()->firstOrFail();

        $this->actingAs($sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-04',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'delivery_challan_line_id' => $challanLine?->id,
                'quantity' => '2',
            ]],
        ])->assertRedirect();
        $invoice = CommercialInvoice::query()->firstOrFail();
        $this->actingAs($sales)->post(route('invoices.issue', $invoice))->assertRedirect();

        return $invoice->fresh() ?? $invoice;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mushakPayload(CommercialInvoice $invoice, string $rate, array $overrides = []): array
    {
        $invoice->loadMissing('lines');
        $line = $invoice->lines->first();
        $this->assertNotNull($line);
        $driver = is_string($overrides['driver'] ?? null) ? $overrides['driver'] : 'base';
        unset($overrides['driver']);

        return array_merge([
            'commercial_invoice_id' => $invoice->id,
            'document_date' => '2026-11-06',
            'buyer_source' => 'party',
            'party_id' => $invoice->quotation?->party_id,
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'commercial_invoice_line_id' => $line->id,
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
                'base_amount' => $overrides['base_amount'] ?? '3001.00',
                'vat_rate' => $rate,
                'inclusive_amount' => $overrides['inclusive_amount'] ?? '',
                'supplementary_duty_amount' => '',
                'driver' => $driver,
            ]],
        ], array_diff_key($overrides, ['base_amount' => true, 'inclusive_amount' => true]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'VAT-OUTPUT',
            'tax_type' => 'output_vat',
            'name' => 'Output VAT reference',
            'effective_from' => '2026-01-01',
            'legal_instrument' => 'Unverified working note',
            'section_reference' => 'Not a statutory determination',
            'rate_percent' => '',
        ], $overrides);
    }
}
