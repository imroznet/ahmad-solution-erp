<?php

namespace Tests\Feature;

use App\Models\AitRecord;
use App\Models\AuditLog;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\Mushak63Document;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Models\VdsRecord;
use App\Tax\IssuanceGate;
use App\Tax\TaxRuleException;
use App\Tax\TaxRuleSelector;
use App\Tax\TaxRuleWriter;
use App\Tax\TaxType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_no_rate_is_seeded_and_a_missing_rule_stays_in_review(): void
    {
        $this->seedAccess();

        $this->assertSame(0, TaxRuleVersion::query()->count());
        $this->assertFalse(IssuanceGate::mushakIssueAllowed());
        $this->assertFalse(IssuanceGate::certificateIssueAllowed());

        $selection = app(TaxRuleSelector::class)->select(TaxType::OUTPUT_VAT, 'S099.00', '', '2026-11-06');

        $this->assertSame('review_required', $selection->outcome);
        $this->assertNull($selection->version);
    }

    public function test_rules_reject_overlap_and_keep_an_approved_version_immutable(): void
    {
        $author = $this->userWithRole('administrator');
        $approver = $this->userWithRole('accounts-manager');
        $writer = app(TaxRuleWriter::class);
        $first = $writer->create($author, $this->ruleInput());
        $writer->approve($first, $approver);

        try {
            $writer->create($author, $this->ruleInput(['effective_from' => '2026-08-01']));
            $this->fail('An overlapping rule was saved.');
        } catch (TaxRuleException $exception) {
            $this->assertSame('effective_from', $exception->field);
        }

        $adjacent = $writer->create($author, $this->ruleInput([
            'supply_classification' => 'S099.10',
            'effective_from' => '2026-07-01',
            'effective_to' => '2026-09-30',
        ]));
        $writer->approve($adjacent, $approver);
        $next = $writer->create($author, $this->ruleInput([
            'supply_classification' => 'S099.10',
            'effective_from' => '2026-10-01',
        ]));

        $this->assertNotNull($next->id);
        $this->assertSame('7.5000', $first->fresh()?->nullableDecimal('rate_percent'));
    }

    public function test_an_amendment_closes_the_previous_range_without_rewriting_its_rate(): void
    {
        $author = $this->userWithRole('administrator');
        $approver = $this->userWithRole('accounts-manager');
        $writer = app(TaxRuleWriter::class);
        $first = $writer->approve($writer->create($author, $this->ruleInput([
            'effective_from' => '2026-07-01',
            'effective_to' => null,
        ])), $approver);
        $draft = $writer->amend($first, $author, $this->ruleInput([
            'effective_from' => '2026-10-01',
            'rate_percent' => '5.0000',
        ]));
        $writer->approve($draft, $approver);

        $first->refresh();
        $this->assertSame('2026-09-30', $first->dateString('effective_to'));
        $this->assertSame('7.5000', $first->nullableDecimal('rate_percent'));
        $this->assertSame('5.0000', $draft->fresh()?->nullableDecimal('rate_percent'));

        $selector = app(TaxRuleSelector::class);
        $this->assertSame((int) $first->getKey(), $selector->select(TaxType::OUTPUT_VAT, 'S099.00', '', '2026-09-30')->version?->getKey());
        $this->assertSame((int) $draft->getKey(), $selector->select(TaxType::OUTPUT_VAT, 'S099.00', '', '2026-10-01')->version?->getKey());
        $this->assertSame('review_required', $selector->select(TaxType::OUTPUT_VAT, 'S099.00', 'OTHER', '2026-10-01')->outcome);
        $this->assertSame('review_required', $selector->select(TaxType::VDS, 'S099.00', '', '2026-10-01')->outcome);
    }

    public function test_the_preparer_cannot_approve_and_sales_cannot_open_tax_rules(): void
    {
        $author = $this->userWithRole('accounts-manager');
        $sales = $this->userWithRole('sales-executive');
        $this->actingAs($author)->post('/tax-rules', $this->ruleInput())->assertRedirect();
        $rule = TaxRuleVersion::query()->firstOrFail();

        $this->actingAs($author)->post(route('tax-rules.approve', $rule))->assertSessionHasErrors('approved_by');
        $this->actingAs($sales)->get('/tax-rules')->assertForbidden();
        $this->actingAs($sales)->post('/tax-rules', $this->ruleInput(['supply_classification' => 'S100.00']))->assertForbidden();
        $this->assertSame(1, TaxRuleVersion::query()->count());
    }

    public function test_a_mushak_working_paper_does_not_change_or_issue_the_commercial_invoice(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $accounts = $this->userWithRole('accounts-manager');
        $author = $this->userWithRole('administrator');
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $beforeTotal = $invoice->decimalAttribute('total');
        $beforeTreatment = $invoice->stringAttribute('tax_treatment');
        $writer = app(TaxRuleWriter::class);
        $rule = $writer->approve($writer->create($author, $this->ruleInput()), $accounts);

        $this->actingAs($accounts)->post('/mushak', [
            'commercial_invoice_id' => $invoice->id,
            'supply_classification' => 'S099.00',
            'rule_code' => '',
            'pinned_tax_rule_version_id' => $rule->id,
            'internal_notes' => 'SECRET-NOTE',
        ])->assertRedirect();

        $paper = Mushak63Document::query()->firstOrFail();
        $invoice->refresh();
        $this->assertSame('draft', $paper->status);
        $this->assertSame($beforeTotal, $invoice->decimalAttribute('total'));
        $this->assertSame($beforeTreatment, $invoice->stringAttribute('tax_treatment'));
        $this->assertSame('excluded', $invoice->stringAttribute('tax_treatment'));
        $this->assertIsArray($paper->pinned_rule_snapshot);
        $this->assertSame('7.5000', $paper->pinned_rule_snapshot['rate_percent'] ?? null);
        $this->assertNotSame('', (string) ($paper->pinned_rule_snapshot['amount'] ?? ''));

        $later = $writer->amend($rule, $author, $this->ruleInput([
            'effective_from' => '2026-12-01',
            'rate_percent' => '5',
        ]));
        $writer->approve($later, $accounts);
        $paper->refresh();
        $this->assertSame('7.5000', $paper->pinned_rule_snapshot['rate_percent'] ?? null);

        $this->actingAs($accounts)->from(route('mushak.show', $paper))->post(route('mushak.issue', $paper))
            ->assertSessionHasErrors('status');
        $this->assertSame('draft', $paper->fresh()?->status);
        $this->assertTrue(AuditLog::query()->where('event', 'mushak_issue_blocked')->exists());

        $this->actingAs($accounts)->get(route('mushak.pdf', $paper))->assertOk();
        $this->assertSame('application/pdf', $this->actingAs($accounts)->get(route('mushak.pdf', $paper))->headers->get('content-type'));
        $this->actingAs($sales)->get(route('mushak.pdf', $paper))->assertForbidden();

        $viewer = $this->userWithRole('viewer');
        $viewer->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('slug', 'mushak.pdf')->firstOrFail());
        $this->actingAs($viewer)->get(route('mushak.pdf', $paper))->assertNotFound();
        $this->assertFileDoesNotExist(public_path('mushak-'.$paper->id.'.pdf'));
    }

    public function test_vds_and_ait_stay_in_separate_records_and_do_not_change_output_vat(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $supplier = $this->party(['legal_name' => 'Padma Supplies', 'email' => 'supply@padma.example']);
        $supplier->roles()->create(['role' => 'supplier']);
        $accounts = $this->userWithRole('accounts-manager');
        $invoice = $this->issuedInvoice($sales, $manager, $customer);
        $before = $invoice->decimalAttribute('total');

        $this->actingAs($accounts)->post('/vds', [
            'direction' => 'withheld_from_us',
            'party_id' => $customer->id,
            'supply_classification' => 'Rent of premises',
            'basis_amount' => '1000.00',
            'record_date' => '2026-11-06',
            'certificate_reference' => 'Received-14',
            'commercial_invoice_id' => $invoice->id,
        ])->assertRedirect();
        $this->actingAs($accounts)->post('/ait', [
            'direction' => 'deducted_by_us',
            'party_id' => $supplier->id,
            'supply_classification' => 'Contract supply',
            'basis_amount' => '500.00',
            'record_date' => '2026-11-06',
        ])->assertRedirect();

        $this->assertSame(1, VdsRecord::query()->count());
        $this->assertSame(1, AitRecord::query()->count());
        $this->assertNull(VdsRecord::query()->firstOrFail()->pinned_rule_snapshot);
        $this->assertSame($before, $invoice->fresh()?->decimalAttribute('total'));
        $this->assertSame('excluded', $invoice->fresh()?->stringAttribute('tax_treatment'));
        $this->actingAs($accounts)->get(route('vds.pdf', VdsRecord::query()->firstOrFail()))->assertOk();
        $this->actingAs($sales)->get('/vds')->assertForbidden();
        $this->actingAs($sales)->get('/ait')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ruleInput(array $overrides = []): array
    {
        return array_merge([
            'tax_type' => TaxType::OUTPUT_VAT,
            'supply_classification' => 'S099.00',
            'rule_code' => '',
            'rate_basis' => 'percentage',
            'rate_percent' => '7.5000',
            'fixed_amount' => '',
            'calculation_basis' => 'exclusive',
            'rounding_rule' => 'half_away_from_zero',
            'threshold_amount' => '',
            'threshold_treatment' => 'not_applicable',
            'effective_from' => '2026-07-01',
            'effective_to' => '',
            'legal_instrument' => 'SRO example for a verified classification',
            'section_reference' => 'Schedule item, not a default rate',
            'source_url' => 'https://nbr.gov.bd/regulations/sros/vat-sros/eng',
            'publication_date' => '2026-06-07',
            'legal_effective_date' => '2026-07-01',
            'internal_notes' => 'SECRET-NOTE',
        ], $overrides);
    }

    /**
     * @return array{0: User, 1: User, 2: Party}
     */
    private function team(): array
    {
        return [
            $this->userWithRole('sales-executive'),
            $this->userWithRole('sales-manager'),
            $this->party(),
        ];
    }

    private function issuedInvoice(User $sales, User $manager, Party $customer): CommercialInvoice
    {
        $quotation = $this->accept($sales, $manager, $this->createQuotation($sales, $customer));
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation))->assertRedirect();
        $challan = DeliveryChallan::query()->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();
        $this->actingAs($sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-06',
            'lines' => [[
                'delivery_challan_line_id' => $challan->fresh()?->lines()->firstOrFail()->id,
                'quantity' => '2',
            ]],
        ])->assertRedirect();
        $invoice = CommercialInvoice::query()->firstOrFail();
        $this->actingAs($sales)->post(route('invoices.issue', $invoice))->assertRedirect();

        return $invoice->fresh() ?? $invoice;
    }

    private function createQuotation(User $sales, Party $customer): Quotation
    {
        $this->actingAs($sales)->post('/quotations', [
            'party_id' => $customer->id,
            'quotation_date' => '2026-11-01',
            'valid_until' => '2026-12-01',
            'terms' => 'Payment within 15 days.',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
            ]],
        ])->assertRedirect();

        return Quotation::query()->firstOrFail();
    }

    private function accept(User $sales, User $manager, Quotation $quotation): Quotation
    {
        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.send', $quotation), ['recipient_label' => 'Nadia Rahman'])->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.respond', $quotation), [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ])->assertRedirect();

        return $quotation->fresh() ?? $quotation;
    }

    /**
     * @return array<string, mixed>
     */
    private function challanPayload(Quotation $quotation): array
    {
        $quotation->load('currentRevision.lines');
        $line = $quotation->currentRevision?->lines->first();
        $this->assertInstanceOf(QuotationLine::class, $line);

        return [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-06',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => '2',
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
            'email' => 'accounts@padma.example',
        ], $overrides));
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }
}
