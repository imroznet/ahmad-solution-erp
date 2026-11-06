<?php

namespace Tests\Feature;

use App\Models\CommercialInvoice;
use App\Models\CompanyAddress;
use App\Models\CompanyProfile;
use App\Models\DeliveryChallan;
use App\Models\MushakDocument;
use App\Models\MushakDocumentLine;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\TaxRule;
use App\Models\User;
use App\Tax\MushakFieldMap;
use App\Tax\MushakPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MushakDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_is_manual_and_an_approved_rate_is_not_copied(): void
    {
        $accountant = $this->userWithRole('accountant');
        $manager = $this->userWithRole('accounts-manager');
        $sales = $this->userWithRole('sales-executive');
        $documents = $this->userWithRole('document-manager');
        $this->company();
        $this->actingAs($manager)->post('/tax-rules', [
            'tax_type' => 'output_vat',
            'rule_key' => 'LOCAL-NOTE',
            'name' => 'Not applied',
            'legal_instrument' => 'Unverified note',
            'effective_from' => '2026-01-01',
            'rate' => '15',
            'rate_kind' => 'percentage',
        ])->assertRedirect();
        $rule = TaxRule::query()->firstOrFail();
        $this->actingAs($manager)->post(route('tax-rules.approve', $rule))->assertRedirect();

        $this->actingAsGuest()->get('/mushak')->assertRedirect(route('login'));
        $this->actingAs($documents)->get('/mushak')->assertForbidden();
        $this->actingAs($sales)->post('/mushak', $this->payload())->assertForbidden();

        $create = $this->actingAs($accountant)->get('/mushak/create');
        $create->assertOk();
        $create->assertSee('name="lines[0][vat_rate]" value=""', false);
        $create->assertDontSee('name="lines[0][vat_rate]" value="15', false);
        $create->assertSee('The ERP did not select, infer, or calculate a statutory rate', false);

        $this->actingAs($accountant)->post('/mushak', $this->payload([
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1000.00',
                'line_value' => '2000.00',
                'supplementary_duty' => '0.00',
                'vat_rate' => '15',
                'vat_amount' => '1.00',
                'inclusive_value' => '2001.00',
            ]],
            'total_value' => '2000.00',
            'total_supplementary_duty' => '0.00',
            'total_vat' => '1.00',
            'total_inclusive' => '2001.00',
        ]))->assertRedirect();

        $document = MushakDocument::query()->firstOrFail();
        $line = $document->lines()->firstOrFail();
        $this->assertSame('15.0000', $line->nullableDecimal('vat_rate'));
        $this->assertSame('1.00', $line->nullableDecimal('vat_amount'));
        $this->assertNotSame('300.00', $line->nullableDecimal('vat_amount'));
        $this->assertFalse((bool) $document->rate_determined_by_erp);
        $this->assertSame(1, Party::query()->count());

        $this->actingAs($sales)->post(route('mushak.issue', $document))->assertForbidden();
        $this->actingAs($accountant)->post(route('mushak.issue', $document))->assertRedirect();
        $document->refresh();
        $this->assertSame('issued', $document->stringAttribute('status'));
        $this->assertNotNull($document->number);
        $this->assertSame('review_required', $document->stringAttribute('tax_review_status'));
        $this->assertSame('1.00', $document->lines()->firstOrFail()->nullableDecimal('vat_amount'));
        $this->assertSame('Ahmad Solution Ltd.', $document->company_snapshot['legal_name'] ?? null);
        $this->assertSame('000123456-0001', $document->company_snapshot['bin'] ?? null);

        CompanyProfile::query()->update(['legal_name' => 'Renamed Later Ltd.', 'bin' => '999999999-9999']);
        Party::query()->update(['legal_name' => 'Renamed Buyer Ltd.']);
        $html = view('tax.mushak.pdf', app(MushakPdfRenderer::class)->viewData($document->fresh() ?? $document, 'original'))->render();
        $this->assertStringContainsString('Ahmad Solution Ltd.', $html);
        $this->assertStringContainsString('000123456-0001', $html);
        $this->assertStringNotContainsString('Renamed Later Ltd.', $html);
        $this->assertStringNotContainsString('Renamed Buyer Ltd.', $html);
        $this->assertStringNotContainsString('SECRET-NOTE', $html);
        foreach (MushakFieldMap::printedLabels() as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $pdf = $this->actingAs($accountant)->get(route('mushak.pdf', $document));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertMatchesRegularExpression('/private/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/no-store/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->actingAs($sales)->get(route('mushak.pdf', ['mushak' => $document, 'copy' => 'nope']))->assertNotFound();

        $this->actingAs($accountant)->post(route('mushak.void', $document), ['void_reason' => 'No'])->assertForbidden();
        $this->actingAs($accountant)->post(route('mushak.treatment', $document), [
            'tax_rule_id' => $rule->id,
            'treatment_note' => 'The operator typed the figures. The 15 percent rule was not applied.',
            'confirmed' => '1',
        ])->assertRedirect();
        $document->refresh();
        $this->assertSame('treatment_recorded', $document->stringAttribute('tax_review_status'));
        $this->assertSame('1.00', $document->nullableDecimal('total_vat'));
        $this->assertSame(1, (int) ($document->treatment_rule_snapshot['version'] ?? 0));

        $this->actingAs($manager)->post(route('tax-rules.amend', $rule), ['effective_from' => '2026-08-01'])->assertRedirect();
        $amendment = TaxRule::query()->where('status', 'draft')->firstOrFail();
        $this->actingAs($manager)->put(route('tax-rules.update', $amendment), [
            'tax_type' => 'output_vat',
            'rule_key' => 'LOCAL-NOTE',
            'name' => 'Later note',
            'legal_instrument' => 'Unverified note',
            'effective_from' => '2026-08-01',
            'rate' => '5',
            'rate_kind' => 'percentage',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('tax-rules.approve', $amendment))->assertRedirect();
        $document->refresh();
        $this->assertSame('1.00', $document->lines()->firstOrFail()->nullableDecimal('vat_amount'));
        $this->assertSame('15.0000', $document->treatment_rule_snapshot['rate'] ?? null);

        $this->actingAs($manager)->post(route('mushak.void', $document), [
            'void_reason' => 'Internal cancellation only.',
        ])->assertRedirect();
        $document->refresh();
        $this->assertSame('voided', $document->stringAttribute('status'));
        $this->assertSame('2000.00', $document->lines()->firstOrFail()->nullableDecimal('line_value'));
    }

    public function test_issue_requires_a_company_bin_and_a_manual_buyer_does_not_create_a_party(): void
    {
        $accountant = $this->userWithRole('accountant');
        $this->company(['bin' => null]);
        $before = Party::query()->count();
        $this->actingAs($accountant)->post('/mushak', $this->payload([
            'buyer_source' => 'manual',
            'party_id' => null,
            'buyer_name' => 'One Off Buyer',
            'buyer_bin' => null,
        ]))->assertRedirect();
        $this->assertSame($before, Party::query()->count());
        $document = MushakDocument::query()->firstOrFail();
        $this->assertNull($document->party_id);
        $this->actingAs($accountant)->post(route('mushak.issue', $document))
            ->assertSessionHasErrors('seller_bin');
        $this->assertNull($document->fresh()->number);

        CompanyProfile::query()->update(['bin' => '000123456-0001']);
        CompanyAddress::query()->delete();
        $this->actingAs($accountant)->post(route('mushak.issue', $document))
            ->assertSessionHasErrors('seller_issue_address');
    }

    public function test_a_blank_percentage_stays_blank_and_a_linked_invoice_is_not_rewritten(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $accountant = $this->userWithRole('accountant');
        $this->company();
        $quotation = $this->acceptedQuotation($sales, $manager, $customer);
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation))->assertRedirect();
        $challan = DeliveryChallan::query()->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();
        $challan->refresh();
        $this->actingAs($accountant)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-04',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();
        $invoice = CommercialInvoice::query()->firstOrFail();
        $this->actingAs($accountant)->post(route('invoices.issue', $invoice))->assertRedirect();
        $invoice->refresh();
        $invoiceTotal = $invoice->decimalAttribute('total');

        $form = $this->actingAs($accountant)->get(route('mushak.create', ['invoice' => $invoice->id]));
        $form->assertOk();
        $form->assertSee('value="'.$invoice->lines()->firstOrFail()->decimalAttribute('line_total').'"', false);
        $form->assertSee('name="lines[0][vat_rate]" value=""', false);
        $this->actingAs($accountant)->get(route('invoices.show', $invoice))->assertSee('Prepare a separate Mushak 6.3');

        $this->actingAs($accountant)->post('/mushak', $this->payload([
            'commercial_invoice_id' => $invoice->id,
            'party_id' => $customer->id,
            'lines' => [[
                'commercial_invoice_line_id' => $invoice->lines()->firstOrFail()->id,
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '1',
                'unit_price' => '1500.50',
                'line_value' => '1500.50',
                'supplementary_duty' => '0.00',
                'vat_amount' => '0.00',
                'inclusive_value' => '1500.50',
            ]],
            'total_value' => '1500.50',
            'total_supplementary_duty' => '0.00',
            'total_vat' => '0.00',
            'total_inclusive' => '1500.50',
        ]))->assertRedirect();

        $document = MushakDocument::query()->firstOrFail();
        $this->assertNull($document->lines()->firstOrFail()->nullableDecimal('vat_rate'));
        $this->actingAs($accountant)->post(route('mushak.issue', $document))->assertRedirect();
        $invoice->refresh();
        $this->assertSame('excluded', $invoice->stringAttribute('tax_treatment'));
        $this->assertSame($invoiceTotal, $invoice->decimalAttribute('total'));
        $this->assertSame('1500.50', $document->fresh()->lines()->firstOrFail()->nullableDecimal('line_value'));

        $role = Role::query()->where('slug', 'accountant')->firstOrFail();
        $role->permissions()->detach(Permission::query()->whereIn('slug', [
            'parties.view',
            'customers.view',
            'customers.manage',
        ])->pluck('id'));
        $accountant->unsetRelation('roles');
        $this->actingAs($accountant)->get(route('mushak.show', $document))->assertNotFound();
        $this->actingAs($accountant)->get(route('mushak.pdf', $document))->assertNotFound();
        $this->actingAs($accountant)->get('/mushak')->assertOk()->assertDontSee('Padma');
    }

    public function test_an_issued_line_cannot_be_rewritten(): void
    {
        $accountant = $this->userWithRole('accountant');
        $this->company();
        $this->actingAs($accountant)->post('/mushak', $this->payload())->assertRedirect();
        $document = MushakDocument::query()->firstOrFail();
        $this->actingAs($accountant)->post(route('mushak.issue', $document))->assertRedirect();
        $line = MushakDocumentLine::query()->firstOrFail();

        $this->expectException(\LogicException::class);
        $line->vat_amount = '99.00';
        $line->save();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $base = [
            'buyer_source' => 'party',
            'destination' => 'Site office, Dhaka',
            'responsible_name' => 'Karim Ahmed',
            'responsible_designation' => 'Manager',
            'issue_date' => '2026-11-06',
            'issue_time' => '10:30',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '1',
                'unit_price' => '100.00',
                'line_value' => '100.00',
                'supplementary_duty' => '0.00',
                'vat_amount' => '0.00',
                'inclusive_value' => '100.00',
            ]],
            'total_value' => '100.00',
            'total_supplementary_duty' => '0.00',
            'total_vat' => '0.00',
            'total_inclusive' => '100.00',
        ];

        if (($overrides['buyer_source'] ?? 'party') === 'party' && ! array_key_exists('party_id', $overrides)) {
            $customer = Party::query()->first();
            $base['party_id'] = $customer instanceof Party ? $customer->id : $this->party()->id;
        }

        return array_merge($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function company(array $overrides = []): CompanyProfile
    {
        $profile = CompanyProfile::query()->create(array_merge([
            'legal_name' => 'Ahmad Solution Ltd.',
            'bin' => '000123456-0001',
            'tin' => '1234567890',
            'email' => 'accounts@ahmad.example',
            'phone' => '01710000000',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
        ], $overrides));
        $profile->addresses()->create([
            'address_type' => 'registered_office',
            'line_1' => '12 Dilkusha',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_active' => true,
        ]);

        return $profile;
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

    private function party(): Party
    {
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'bin' => '000222333-0002',
            'email' => 'accounts@padma.example',
        ]);
        $party->roles()->create(['role' => 'customer']);

        return $party;
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

        return $quotation->fresh() ?? $quotation;
    }

    /**
     * @return array<string, mixed>
     */
    private function challanPayload(Quotation $quotation): array
    {
        $quotation->loadMissing('currentRevision.lines');

        return [
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-04',
            'lines' => [[
                'quotation_line_id' => $quotation->currentRevision?->lines->first()?->id,
                'quantity' => '1',
            ]],
        ];
    }
}
