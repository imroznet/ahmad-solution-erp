<?php

namespace Tests\Feature;

use App\Models\AitRecord;
use App\Models\AuditLog;
use App\Models\CommercialInvoice;
use App\Models\CompanyAddress;
use App\Models\CompanyProfile;
use App\Models\DeliveryChallan;
use App\Models\MushakInvoice;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Role;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\VdsRecord;
use App\Quotations\QuotationStatus;
use App\Support\Decimal;
use App\Tax\TaxModuleInstaller;
use App\Tax\TaxModuleSql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaxModuleTest extends TestCase
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

    public function test_rules_do_not_overlap_or_rewrite_an_approved_version_or_supply_a_rate(): void
    {
        $accounts = $this->userWithRole('accounts-manager');
        $this->actingAs($accounts)->post('/tax/rules', $this->rulePayload())->assertRedirect();
        $rule = TaxRule::query()->firstOrFail();
        $this->assertSame('draft', $rule->approval_status);
        $this->assertNull($rule->documented_rate);

        $this->actingAs($accounts)->post(route('tax.rules.approve', $rule))->assertRedirect();
        $rule->refresh();
        $this->assertSame('approved', $rule->approval_status);

        $this->actingAs($accounts)->post('/tax/rules', $this->rulePayload([
            'effective_from' => '2026-06-01',
        ]))->assertRedirect();
        $overlap = TaxRule::query()->where('approval_status', 'draft')->firstOrFail();
        $this->actingAs($accounts)->post(route('tax.rules.approve', $overlap))->assertSessionHasErrors('effective_from');

        $this->actingAs($accounts)->put(route('tax.rules.update', $overlap), $this->rulePayload([
            'rule_code' => 'VAT-OUTPUT',
            'effective_from' => '2026-07-01',
            'effective_to' => '',
            'documented_rate' => '15',
            'rate_kind' => 'percentage',
        ]))->assertRedirect();
        $this->actingAs($accounts)->post(route('tax.rules.approve', $overlap))->assertRedirect();
        $rule->refresh();
        $overlap->refresh();
        $this->assertNull($rule->documented_rate);
        $this->assertSame('2026-06-30', $rule->dateString('effective_to'));
        $this->assertSame('15.0000', $overlap->documented_rate);
        $this->assertSame(2, (int) $overlap->version);

        $this->actingAs($accounts)->get(route('tax.rules.show', $rule).'?on_date=2025-12-01&classification=')
            ->assertOk()
            ->assertSee('No active approved rule matches');
        $this->actingAs($accounts)->get(route('tax.rules.show', $overlap).'?on_date=2026-08-01')
            ->assertOk()
            ->assertSee('not applied as a VAT percentage');
    }

    public function test_mushak_calculates_from_a_typed_rate_and_leaves_the_commercial_invoice_unchanged(): void
    {
        [$sales, $manager, $accountant] = $this->team();
        $customer = $this->party(['bin' => '000000000-0000']);
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $before = $invoice->only(['number', 'total', 'tax_treatment', 'party_legal_name']);
        $parties = Party::query()->count();
        $this->company('123456789-0001');

        $this->actingAs($accountant)->post('/tax/mushak', ['commercial_invoice_id' => $invoice->id])->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();
        $this->assertNull($mushak->number);
        $this->assertNull($mushak->lines()->firstOrFail()->vat_rate_percent);
        $this->assertSame('invoice', $mushak->buyer_source);

        $lineId = (int) $mushak->lines()->firstOrFail()->id;
        $this->actingAs($accountant)->put(route('mushak.update', $mushak), $this->mushakPayload($mushak, $lineId, [
            'vat_rate' => '15',
            'adjustment_amount' => '0.50',
            'adjustment_reason' => 'Poisha difference',
        ]))->assertRedirect();
        $mushak->refresh();
        $line = $mushak->lines()->firstOrFail();
        $this->assertSame('15.0000', $line->vat_rate_percent);
        $this->assertSame('225.0000', $line->vat_amount);
        $this->assertSame('1725.5000', $line->final_amount);
        $this->assertTrue($mushak->amountsReconcile());
        $this->assertSame($parties, Party::query()->count());

        $this->actingAs($accountant)->put(route('mushak.update', $mushak), $this->mushakPayload($mushak, $lineId, [
            'driver' => 'reverse',
            'final_amount' => '1725.50',
            'vat_rate' => '15',
            'adjustment_amount' => '0.50',
            'adjustment_reason' => 'Poisha difference',
            'calculation_precision' => 4,
            'rounding_method' => 'half_even',
        ]))->assertRedirect();
        $line->refresh();
        $this->assertSame('15.0000', $line->vat_rate_percent);
        $this->assertSame('0.5000', $line->adjustment_amount);
        $this->assertSame('1725.5000', $line->final_amount);
        $expectedBase = Decimal::of('1725.00')->multipliedBy(100)->dividedBy(Decimal::of(115), 4);
        $this->assertSame(0, Decimal::of($line->decimalAttribute('base_amount'))->compare($expectedBase));

        $this->actingAs($accountant)->post(route('mushak.issue', $mushak))->assertRedirect();
        $mushak->refresh();
        $invoice->refresh();
        $this->assertSame('M63-2026-0001', $mushak->number);
        $this->assertSame('123456789-0001', $mushak->seller_bin);
        $this->assertSame('review_required', $mushak->tax_review_status);
        $this->assertSame($before, $invoice->only(['number', 'total', 'tax_treatment', 'party_legal_name']));
        $this->assertDatabaseHas('audit_logs', ['event' => 'mushak_issued']);

        CompanyProfile::query()->update(['legal_name' => 'Later Name Ltd.', 'bin' => '999999999-9999']);
        $customer->update(['legal_name' => 'Renamed Buyer Ltd.']);
        $html = view('tax.mushak.pdf', ['invoice' => $mushak->fresh(), 'lines' => $mushak->lines])->render();
        $this->assertStringContainsString('Issued Letterhead Ltd.', $html);
        $this->assertStringContainsString('123456789-0001', $html);
        $this->assertStringNotContainsString('Later Name Ltd.', $html);
        $this->assertStringNotContainsString('SECRET-NOTE', $html);
        $this->assertStringContainsString('Supplementary calculation', $html);
        $this->assertStringContainsString('Rounding / decimal adjustment', $html);

        $this->actingAs($accountant)->put(route('mushak.update', $mushak), $this->mushakPayload($mushak, $lineId))->assertForbidden();
        $this->expectException(\LogicException::class);
        $mushak->buyer_name = 'Changed after issue';
        $mushak->save();
    }

    public function test_issue_is_blocked_without_a_company_bin_or_a_typed_rate_and_vds_stays_separate(): void
    {
        [$sales, $manager, $accountant] = $this->team();
        $customer = $this->party();
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $this->company(null);
        $this->actingAs($accountant)->post('/tax/mushak', ['commercial_invoice_id' => $invoice->id])->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();
        $lineId = (int) $mushak->lines()->firstOrFail()->id;

        $this->actingAs($accountant)->put(route('mushak.update', $mushak), $this->mushakPayload($mushak, $lineId, [
            'buyer_source' => 'manual',
            'buyer_name' => 'One Off Buyer',
            'buyer_address' => 'House 4, Dhaka',
            'vat_rate' => '',
        ]))->assertRedirect();
        $this->assertSame(1, Party::query()->count());
        $this->actingAs($accountant)->post(route('mushak.issue', $mushak))->assertSessionHasErrors('vat_rate');

        $this->actingAs($accountant)->put(route('mushak.update', $mushak), $this->mushakPayload($mushak, $lineId, [
            'buyer_source' => 'manual',
            'buyer_name' => 'One Off Buyer',
            'vat_rate' => '0',
        ]))->assertRedirect();
        $this->actingAs($accountant)->post(route('mushak.issue', $mushak))->assertSessionHasErrors('seller_bin');
        $this->assertNull($mushak->fresh()?->number);

        CompanyProfile::query()->update(['bin' => '123456789-0001']);
        $this->actingAs($accountant)->post(route('mushak.issue', $mushak))->assertRedirect();
        $vat = $mushak->fresh()?->total_vat;

        $this->actingAs($accountant)->post('/tax/vds', [
            'direction' => 'withheld_from_us',
            'record_date' => '2026-11-06',
            'counterparty_name' => 'Padma Engineering Ltd.',
            'taxable_basis' => '1500.00',
            'deducted_amount' => '75.00',
            'mushak_invoice_id' => $mushak->id,
            'internal_notes' => 'SECRET-NOTE',
        ])->assertRedirect();
        $vds = VdsRecord::query()->firstOrFail();
        $this->actingAs($accountant)->post(route('deductions.record', ['kind' => 'vds', 'record' => $vds->id]))->assertRedirect();
        $this->assertSame('unverified', $vds->fresh()?->certificate_form);
        $this->assertSame($vat, $mushak->fresh()?->total_vat);

        $this->actingAs($accountant)->post('/tax/ait', [
            'direction' => 'deducted_by_us',
            'record_date' => '2026-11-06',
            'counterparty_name' => 'Supplier Ltd.',
            'taxable_basis' => '500.00',
            'deducted_amount' => '25.00',
        ])->assertRedirect();
        $this->assertSame(1, AitRecord::query()->count());
        $pdf = $this->actingAs($accountant)->get(route('deductions.pdf', ['kind' => 'vds', 'record' => $vds->id]));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'vds_pdf_downloaded']);
        $html = view('tax.deductions.pdf', ['kind' => 'vds', 'title' => 'VDS', 'record' => $vds->fresh()])->render();
        $this->assertStringNotContainsString('SECRET-NOTE', $html);
        $this->assertStringContainsString('not output VAT', $html);
    }

    public function test_permissions_hide_tax_documents_and_custom_grants_survive_the_installer(): void
    {
        [$sales, $manager] = [$this->userWithRole('sales-executive'), $this->userWithRole('sales-manager')];
        $documents = $this->userWithRole('document-manager');
        $accountant = $this->userWithRole('accountant');
        $customer = $this->party();
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $this->company('123456789-0001');
        $this->actingAs($accountant)->post('/tax/mushak', ['commercial_invoice_id' => $invoice->id])->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();

        $this->actingAsGuest()->get('/tax/mushak')->assertRedirect(route('login'));
        $this->actingAs($documents)->get('/tax/mushak')->assertForbidden();
        $this->actingAs($documents)->get('/tax/rules')->assertForbidden();
        $this->actingAs($documents)->get('/tax/vds')->assertForbidden();
        $this->actingAs($sales)->post(route('mushak.void', $mushak), ['void_reason' => 'No'])->assertForbidden();
        $this->actingAs($sales)->get(route('mushak.show', $mushak))->assertOk();
        $this->actingAs($sales)->get('/tax/mushak/999999')->assertNotFound();

        $role = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $role->permissions()->detach(Permission::query()->whereIn('slug', [
            'parties.view',
            'customers.view',
            'customers.manage',
        ])->pluck('id'));
        $sales->unsetRelation('roles');
        $this->actingAs($sales)->get(route('mushak.show', $mushak))->assertNotFound();
        $this->actingAs($sales)->get(route('mushak.pdf', $mushak))->assertNotFound();

        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);
        $salesRole = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $salesRole->permissions()->detach(Permission::query()->where('slug', 'mushak.view')->firstOrFail());
        app(TaxModuleInstaller::class)->install();
        $viewer->unsetRelation('permissions');
        $salesRole->unsetRelation('permissions');
        $this->assertSame(['audit.view'], $viewer->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertTrue($salesRole->permissions->contains('slug', 'mushak.view'));
        $this->assertFalse($salesRole->permissions->contains('slug', 'mushak.void'));
        $this->assertFalse($salesRole->permissions->contains('slug', 'vds.view'));
        $this->assertSame(1, AuditLog::query()->where('event', 'mushak_prepared')->count());
    }

    public function test_tax_upgrade_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/upgrade-tax-module.sql'));
        $this->assertIsString($committed);
        $this->assertSame(TaxModuleSql::upgrade(), $committed);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `mushak_invoices`', $committed);
        $this->assertStringContainsString('tax_rules.view', $committed);
        $this->assertStringNotContainsString('15.0000', $committed);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function team(): array
    {
        return [
            $this->userWithRole('sales-executive'),
            $this->userWithRole('sales-manager'),
            $this->userWithRole('accountant'),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'tax_type' => 'output_vat',
            'rule_code' => 'VAT-OUTPUT',
            'supply_classification' => '',
            'rate_kind' => 'not_a_rate',
            'price_treatment' => 'unspecified',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'legal_instrument' => 'Value Added Tax and Supplementary Duty Rules, 2016',
            'section_reference' => 'Rule 40',
            'source_url' => 'https://dknvat.gov.bd/assets/document/form/form_175068235417Xz9DmBrT9uc6V.pdf',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function mushakPayload(MushakInvoice $mushak, int $lineId, array $line = []): array
    {
        return [
            'document_date' => '2026-11-06',
            'destination' => 'Site office, Dhaka',
            'calculation_precision' => $line['calculation_precision'] ?? 2,
            'rounding_method' => $line['rounding_method'] ?? 'half_up',
            'buyer_source' => $line['buyer_source'] ?? 'invoice',
            'buyer_name' => $line['buyer_name'] ?? '',
            'buyer_address' => $line['buyer_address'] ?? '',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'id' => $lineId,
                'description' => 'Network survey',
                'base_amount' => $line['base_amount'] ?? '1500.00',
                'vat_rate' => $line['vat_rate'] ?? '15',
                'adjustment_amount' => $line['adjustment_amount'] ?? '0',
                'adjustment_reason' => $line['adjustment_reason'] ?? '',
                'supplementary_duty_amount' => $line['supplementary_duty_amount'] ?? '',
                'final_amount' => $line['final_amount'] ?? '',
                'driver' => $line['driver'] ?? 'forward',
            ]],
        ];
    }

    private function company(?string $bin): void
    {
        $profile = CompanyProfile::query()->create([
            'legal_name' => 'Issued Letterhead Ltd.',
            'bin' => $bin,
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
        ]);
        CompanyAddress::query()->create([
            'company_profile_id' => $profile->id,
            'address_type' => 'registered_office',
            'line_1' => 'House 1, Road 2',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_active' => true,
        ]);
    }

    private function issuedInvoice(User $sales, User $manager, Party $customer): CommercialInvoice
    {
        $quotation = $this->acceptedQuotation($sales, $manager, $customer);
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation))->assertRedirect();
        $challan = DeliveryChallan::query()->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();
        $this->actingAs($sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-06',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();
        $invoice = CommercialInvoice::query()->firstOrFail();
        $this->actingAs($sales)->post(route('invoices.issue', $invoice))->assertRedirect();

        return $invoice->fresh() ?? $invoice;
    }

    private function acceptedQuotation(User $sales, User $manager, Party $customer): Quotation
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
                'unit_price' => '1500.00',
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
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->fresh()?->status);

        return $quotation->fresh() ?? $quotation;
    }

    /**
     * @return array<string, mixed>
     */
    private function challanPayload(Quotation $quotation): array
    {
        $quotation->loadMissing('currentRevision.lines');
        $line = $quotation->currentRevision?->lines->first();
        $this->assertInstanceOf(QuotationLine::class, $line);

        return [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-06',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => '1',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function party(array $overrides = []): Party
    {
        $party = Party::query()->create(array_merge([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ], $overrides));
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }
}
