<?php

namespace Tests\Feature;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\CompanyProfile;
use App\Models\DeliveryChallan;
use App\Models\MushakInvoice;
use App\Models\MushakLine;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Role;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Models\VdsRecord;
use App\Tax\MushakPdfRenderer;
use App\Tax\TaxModuleInstaller;
use App\Tax\TaxModuleSql;
use App\Tax\TaxRuleStatus;
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

    public function test_vat_percentage_is_manual_and_adjustment_follows_the_invariant(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $lineId = $invoice->lines()->firstOrFail()->id;
        $parties = Party::query()->count();

        $this->actingAs($sales)->post('/mushak', $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '',
            'edited' => 'base',
        ]))->assertRedirect();

        $draft = MushakInvoice::query()->firstOrFail();
        $this->assertNull($draft->lines()->firstOrFail()->vat_rate);
        $this->assertNull($draft->vat_total);
        $this->assertSame($parties, Party::query()->count());
        $this->assertSame('20.0000', $draft->lines()->firstOrFail()->decimalAttribute('base_amount'));

        $this->actingAs($sales)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '5',
            'adjustment_amount' => '-0.25',
            'adjustment_reason' => 'Poisha remainder agreed with the buyer.',
            'edited' => 'base',
        ]))->assertRedirect();
        $draft->refresh();
        $line = $draft->lines()->firstOrFail();
        $this->assertSame('5.0000', $line->decimalAttribute('vat_rate'));
        $this->assertSame('1.0000', $line->decimalAttribute('vat_amount'));
        $this->assertSame('-0.2500', $line->decimalAttribute('adjustment_amount'));
        $this->assertSame('20.7500', $line->decimalAttribute('final_amount'));
        $this->assertSame('20.7500', $draft->decimalAttribute('final_total'));
        $this->assertNotSame(15.0, (float) $line->vat_rate);

        $this->actingAs($sales)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '5',
            'base_amount' => '20.24',
            'adjustment_amount' => '-0.25',
            'adjustment_reason' => 'Poisha remainder agreed with the buyer.',
            'final_amount' => '21.00',
            'edited' => 'final',
        ]))->assertRedirect();
        $line = $draft->fresh()?->lines()->firstOrFail();
        $this->assertInstanceOf(MushakLine::class, $line);
        $this->assertSame('5.0000', $line->decimalAttribute('vat_rate'));
        $this->assertSame('-0.2500', $line->decimalAttribute('adjustment_amount'));
        $this->assertSame('21.0000', $line->decimalAttribute('final_amount'));
        $this->assertSame('20.2400', $line->decimalAttribute('base_amount'));
        $this->assertSame('1.0100', $line->decimalAttribute('vat_amount'));

        $this->actingAs($sales)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '5',
            'base_amount' => '999',
            'adjustment_amount' => '0.50',
            'adjustment_reason' => 'Buyer asked for a poisha adjustment.',
            'final_amount' => '1',
            'edited' => 'adjustment',
        ]))->assertRedirect();
        $adjusted = $draft->fresh();
        $this->assertInstanceOf(MushakInvoice::class, $adjusted);
        $adjustedLine = $adjusted->lines()->firstOrFail();
        $this->assertSame('20.2400', $adjustedLine->decimalAttribute('base_amount'));
        $this->assertSame('1.0100', $adjustedLine->decimalAttribute('vat_amount'));
        $this->assertSame('5.0000', $adjustedLine->decimalAttribute('vat_rate'));
        $this->assertSame('0.5000', $adjustedLine->decimalAttribute('adjustment_amount'));
        $this->assertSame('21.7500', $adjustedLine->decimalAttribute('final_amount'));

        $this->actingAs($sales)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '5',
            'base_amount' => '20.24',
            'adjustment_amount' => '0.50',
            'adjustment_reason' => 'Buyer asked for a poisha adjustment.',
            'edited' => 'base',
            'calculation_precision' => 4,
            'rounding_method' => 'half_even',
        ]))->assertRedirect();
        $precise = $draft->fresh();
        $this->assertInstanceOf(MushakInvoice::class, $precise);
        $preciseLine = $precise->lines()->firstOrFail();
        $this->assertSame(4, $precise->calculation_precision);
        $this->assertSame('half_even', $precise->rounding_method);
        $this->assertSame('1.0120', $preciseLine->decimalAttribute('vat_amount'));
        $this->assertSame('21.7520', $preciseLine->decimalAttribute('final_amount'));

        $this->actingAs($sales)->put(route('mushak.update', $draft), $this->mushakPayload($invoice, $lineId, [
            'adjustment_amount' => '0.50',
            'adjustment_reason' => '',
        ]))->assertSessionHasErrors('lines.0.adjustment_reason');
    }

    public function test_issue_freezes_the_snapshot_and_a_later_rule_does_not_rewrite_it(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        CompanyProfile::query()->create([
            'legal_name' => 'Issued Letterhead Ltd.',
            'bin' => '000111222333',
            'financial_year_starts_on' => '2026-07-01',
            'financial_year_ends_on' => '2027-06-30',
            'currency_code' => 'BDT',
        ]);
        $version = $this->approvedRule($accountant, $this->userWithRole('accounts-manager'), '15');
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $lineId = $invoice->lines()->firstOrFail()->id;
        $this->actingAs($sales)->post('/mushak', $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '5',
            'supply_classification' => 'Software support',
        ]))->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();

        $this->actingAs($sales)->post(route('mushak.issue', $mushak), [])->assertSessionHasErrors('legal_treatment_confirmed');
        CompanyProfile::query()->update(['bin' => null]);
        $this->actingAs($sales)->post(route('mushak.issue', $mushak), $this->issuePayload())->assertSessionHasErrors('company_bin');
        CompanyProfile::query()->update(['bin' => '000111222333']);

        $this->actingAs($sales)->post(route('mushak.issue', $mushak), $this->issuePayload())->assertRedirect();
        $mushak->refresh();
        $this->assertSame(DocumentStatus::ISSUED, $mushak->status);
        $this->assertSame('M63-2026-0001', $mushak->number);
        $this->assertSame($version->id, $mushak->tax_rule_version_id);
        $this->assertSame('5.0000', $mushak->lines()->firstOrFail()->decimalAttribute('vat_rate'));
        $this->assertSame('Issued Letterhead Ltd.', $mushak->company_snapshot['legal_name'] ?? null);
        $this->assertSame('000111222333', $mushak->company_snapshot['bin'] ?? null);
        $this->assertSame('manual_buyer', $mushak->buyer_name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mushak_issued']);
        CompanyProfile::query()->update(['legal_name' => 'Later Letterhead Ltd.']);
        $this->assertSame('Issued Letterhead Ltd.', $mushak->fresh()?->company_snapshot['legal_name'] ?? null);

        try {
            $version->forceFill(['rate_value' => '7.5000'])->save();
            $this->fail('An approved tax rule version accepted a rewrite.');
        } catch (\LogicException) {
            $this->assertSame('15.0000', $version->fresh()?->decimalAttribute('rate_value'));
        }

        $this->assertSame('5.0000', $mushak->fresh()?->lines()->firstOrFail()->decimalAttribute('vat_rate'));

        try {
            $mushak->forceFill(['buyer_name' => 'Changed'])->save();
            $this->fail('An issued Mushak accepted a content change.');
        } catch (\LogicException) {
            $this->assertSame('manual_buyer', $mushak->fresh()?->buyer_name);
        }
    }

    public function test_pdf_is_private_and_shows_the_adjustment_apart_from_vat(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        CompanyProfile::query()->create([
            'legal_name' => 'Issued Letterhead Ltd.',
            'bin' => '000111222333',
            'financial_year_starts_on' => '2026-07-01',
            'financial_year_ends_on' => '2027-06-30',
            'currency_code' => 'BDT',
        ]);
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $lineId = $invoice->lines()->firstOrFail()->id;
        $this->actingAs($sales)->post('/mushak', $this->mushakPayload($invoice, $lineId, [
            'vat_rate' => '5',
            'adjustment_amount' => '-0.25',
            'adjustment_reason' => 'Poisha remainder agreed with the buyer.',
            'internal_notes' => 'SECRET-NOTE',
        ]))->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();
        $this->actingAs($sales)->post(route('mushak.issue', $mushak), $this->issuePayload())->assertRedirect();
        $mushak->refresh();

        $html = view('mushak.pdf', app(MushakPdfRenderer::class)->data($mushak))->render();
        $this->assertStringContainsString('Mushak 6.3', $html);
        $this->assertStringContainsString('মূসক-৬.৩', $html);
        $this->assertStringContainsString('Rounding / Decimal Adjustment', $html);
        $this->assertStringContainsString('Issued Letterhead Ltd.', $html);
        $this->assertStringContainsString('000111222333', $html);
        $this->assertStringContainsString('manual_buyer', $html);
        $this->assertStringContainsString('-0.2500', $html);
        $this->assertStringNotContainsString('SECRET-NOTE', $html);
        $this->assertStringNotContainsString('Later Letterhead', $html);
        $this->assertStringContainsString('not selected or certified', $html);

        $this->actingAsGuest()->get(route('mushak.pdf', $mushak))->assertRedirect(route('login'));
        $documents = $this->userWithRole('document-manager');
        $this->actingAs($documents)->get(route('mushak.pdf', $mushak))->assertForbidden();
        $pdf = $this->actingAs($sales)->get(route('mushak.pdf', $mushak));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertMatchesRegularExpression('/private/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'mushak_pdf_downloaded']);

        $role = Role::query()->where('slug', 'accountant')->firstOrFail();
        $role->permissions()->detach(Permission::query()->whereIn('slug', [
            'parties.view',
            'customers.view',
            'customers.manage',
        ])->pluck('id'));
        $accountant->unsetRelation('roles');
        $this->actingAs($accountant)->get(route('mushak.show', $mushak))->assertNotFound();
        $this->actingAs($sales)->get(route('mushak.show', $mushak))->assertOk()->assertSee('SECRET-NOTE');
    }

    public function test_rule_dates_do_not_overlap_and_do_not_supply_a_percentage(): void
    {
        [$sales, $manager, $accountant] = [$this->userWithRole('sales-executive'), $this->userWithRole('accounts-manager'), $this->userWithRole('accountant')];
        $payload = $this->rulePayload();
        $this->actingAs($manager)->post('/tax-rules', $payload)->assertRedirect();
        $version = TaxRuleVersion::query()->firstOrFail();
        $this->actingAs($manager)->post(route('tax-rules.approve', $version))->assertSessionHasErrors('approved_by');
        $this->actingAs($this->userWithRole('administrator'))->post(route('tax-rules.approve', $version))->assertRedirect();

        $this->actingAs($accountant)->post('/tax-rules', $this->rulePayload(['rule_code' => 'VAT-OTHER']))
            ->assertSessionHasErrors('effective_from');
        $this->actingAs($accountant)->post('/tax-rules', $this->rulePayload([
            'rule_code' => 'VAT-NEXT',
            'effective_from' => '2026-06-30',
            'effective_to' => '2026-12-31',
        ]))->assertSessionHasErrors('effective_from');
        $this->actingAs($accountant)->get('/tax-rules?tax_type=output_vat&classification=Software+support&on=2026-06-30')
            ->assertOk()
            ->assertSee('was not copied');
        $this->actingAs($manager)->post(route('tax-rules.retire', $version), [
            'effective_to' => '2026-06-30',
            'retirement_reason' => 'Closed before the next instrument.',
        ])->assertRedirect();
        $this->actingAs($accountant)->post('/tax-rules', $this->rulePayload([
            'rule_code' => 'VAT-NEXT',
            'effective_from' => '2026-07-01',
            'effective_to' => '',
            'rate_kind' => 'none',
            'rate_value' => '',
        ]))->assertRedirect();

        $this->actingAs($accountant)->get('/tax-rules?tax_type=output_vat&classification=Software+support&on=2026-07-01')
            ->assertOk()
            ->assertSee('No approved rule');
        $this->assertSame(TaxRuleStatus::RETIRED, $version->fresh()?->status);
    }

    public function test_vds_and_ait_stay_out_of_output_vat(): void
    {
        [$sales, $manager, $accountant, $customer] = $this->team();
        CompanyProfile::query()->create([
            'legal_name' => 'Issued Letterhead Ltd.',
            'bin' => '000111222333',
            'financial_year_starts_on' => '2026-07-01',
            'financial_year_ends_on' => '2027-06-30',
            'currency_code' => 'BDT',
        ]);
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $lineId = $invoice->lines()->firstOrFail()->id;
        $this->actingAs($sales)->post('/mushak', $this->mushakPayload($invoice, $lineId, ['vat_rate' => '5']))->assertRedirect();
        $mushak = MushakInvoice::query()->firstOrFail();
        $this->actingAs($sales)->post(route('mushak.issue', $mushak), $this->issuePayload())->assertRedirect();
        $vat = $mushak->fresh()?->decimalAttribute('vat_total');

        $this->actingAs($sales)->get('/vds')->assertForbidden();
        $this->actingAs($accountant)->post('/vds', [
            'direction' => 'withheld_by_customer',
            'party_id' => $customer->id,
            'supply_description' => 'June support invoice',
            'amount' => '1.25',
            'record_date' => '2026-11-06',
            'mushak_invoice_id' => $mushak->id,
            'internal_notes' => 'SECRET-VDS',
        ])->assertRedirect();
        $this->actingAs($accountant)->post('/ait', [
            'direction' => 'deducted_from_supplier',
            'party_name' => 'One-off supplier',
            'supply_description' => 'Consulting fee',
            'amount' => '2.00',
            'record_date' => '2026-11-06',
        ])->assertRedirect();

        $this->assertSame($vat, $mushak->fresh()?->decimalAttribute('vat_total'));
        $this->assertSame(1, VdsRecord::query()->count());
        $this->assertSame('not_issued_form_unverified', VdsRecord::query()->firstOrFail()->certificate_status);
        $this->actingAs($accountant)->get(route('vds.show', VdsRecord::query()->firstOrFail()))->assertOk()->assertSee('SECRET-VDS');
        $this->actingAs($accountant)->get('/ait')->assertOk()->assertSee('One-off supplier');
        $this->assertSame(1, Party::query()->count());
    }

    public function test_permissions_and_upgrade_sql_keep_custom_grants(): void
    {
        $this->seedAccess();
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();
        $audit = Permission::query()->where('slug', 'audit.view')->firstOrFail();
        $viewer->permissions()->sync([$audit->id]);
        $sales = Role::query()->where('slug', 'sales-executive')->firstOrFail();
        $sales->permissions()->detach(Permission::query()->where('slug', 'mushak.view')->firstOrFail());
        $documents = Role::query()->where('slug', 'document-manager')->firstOrFail();
        $documents->permissions()->attach(Permission::query()->where('slug', 'mushak.view')->firstOrFail());

        app(TaxModuleInstaller::class)->install();

        $viewer->unsetRelation('permissions');
        $sales->unsetRelation('permissions');
        $documents->unsetRelation('permissions');
        $this->assertSame(['audit.view'], $viewer->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertTrue($sales->permissions->contains('slug', 'mushak.view'));
        $this->assertFalse($sales->permissions->contains('slug', 'mushak.void'));
        $this->assertFalse($sales->permissions->contains('slug', 'vds.view'));
        $this->assertTrue($documents->permissions->contains('slug', 'mushak.view'));

        $committed = file_get_contents(base_path('deploy/upgrade-tax-module.sql'));
        $this->assertIsString($committed);
        $this->assertSame($committed, TaxModuleSql::upgrade());
        $this->assertStringContainsString('decimal(20,4)', $committed);
        $this->assertStringNotContainsString('DELETE FROM `roles`', $committed);
        $reference = file_get_contents(base_path('deploy/reference-data.sql'));
        $this->assertIsString($reference);
        $this->assertStringContainsString('mushak.view', $reference);
    }

    public function test_preview_uses_decimal_arithmetic(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $this->actingAs($sales)->postJson('/mushak/calculate', [
            'calculation_precision' => 3,
            'rounding_method' => 'half_even',
            'edited' => 'base',
            'base_amount' => '10',
            'vat_rate' => '10.025',
            'adjustment_amount' => '0',
        ])->assertOk()
            ->assertJsonPath('vat_amount', '1.002')
            ->assertJsonPath('final_amount', '11.002')
            ->assertJsonPath('display_vat', '1.00');
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Party}
     */
    private function team(): array
    {
        $customer = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'bin' => '000999888',
        ]);
        $customer->roles()->create(['role' => 'customer']);
        PartyAddress::query()->create([
            'party_id' => $customer->id,
            'address_type' => 'billing',
            'address_line_1' => '12 Lake Road',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_default_billing' => true,
            'is_active' => true,
        ]);

        return [
            $this->userWithRole('sales-executive'),
            $this->userWithRole('sales-manager'),
            $this->userWithRole('accountant'),
            $customer,
        ];
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
                'unit_price' => '10.00',
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
        $quotation->load('currentRevision.lines');
        $line = $quotation->currentRevision?->lines->first();
        $this->assertInstanceOf(QuotationLine::class, $line);
        $this->actingAs($sales)->post('/challans', [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-04',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => '2',
            ]],
        ])->assertRedirect();
        $challan = DeliveryChallan::query()->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();
        $this->actingAs($sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-04',
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
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
    private function mushakPayload(CommercialInvoice $invoice, int $lineId, array $overrides = []): array
    {
        $line = CommercialInvoiceLine::query()->findOrFail($lineId);

        $payload = [
            'commercial_invoice_id' => $invoice->id,
            'document_date' => '2026-11-06',
            'buyer_source' => 'manual',
            'buyer_name' => 'manual_buyer',
            'buyer_address' => '1 Test Road, Dhaka',
            'buyer_bin' => '000123123',
            'supply_destination' => 'Dhaka',
            'supply_classification' => 'Software support',
            'calculation_precision' => 2,
            'rounding_method' => 'half_up',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'commercial_invoice_line_id' => $lineId,
                'description' => $line->stringAttribute('description'),
                'unit' => $line->stringAttribute('unit'),
                'quantity' => '2',
                'base_amount' => '20.00',
                'vat_rate' => '5',
                'adjustment_amount' => '0',
                'adjustment_reason' => '',
                'final_amount' => '',
                'edited' => 'base',
            ]],
        ];
        $lineKeys = [
            'vat_rate', 'adjustment_amount', 'adjustment_reason', 'final_amount', 'edited',
            'base_amount', 'quantity', 'description', 'unit',
        ];

        foreach ($overrides as $key => $value) {
            if (in_array($key, $lineKeys, true)) {
                $payload['lines'][0][$key] = $value;
            } else {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function issuePayload(): array
    {
        return [
            'legal_treatment_confirmed' => '1',
            'legal_treatment_note' => 'The buyer and our accounts lead agreed this percentage for this invoice.',
            'review_reason' => 'No statutory rate is being claimed. The percentage was typed for this supply.',
            'prepared_by_designation' => 'Accounts officer',
        ];
    }

    private function approvedRule(User $author, User $approver, string $rate): TaxRuleVersion
    {
        $this->actingAs($author)->post('/tax-rules', $this->rulePayload(['rate_value' => $rate]))->assertRedirect();
        $version = TaxRuleVersion::query()->firstOrFail();
        $this->actingAs($approver)->post(route('tax-rules.approve', $version))->assertRedirect();

        return $version->fresh() ?? $version;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'tax_type' => 'output_vat',
            'rule_code' => 'VAT-SUPPORT',
            'supply_classification' => 'Software support',
            'rate_kind' => 'percentage',
            'rate_value' => '15',
            'fixed_amount' => '',
            'legal_instrument' => 'Owner confirmation, not an activated statutory rate',
            'section_reference' => 'Reference only',
            'source_url' => 'https://nbr.gov.bd/regulations/sros/vat-sros/',
            'publication_date' => '2026-06-11',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'notes' => 'This percentage must not be copied onto a Mushak.',
        ], $overrides);
    }
}
