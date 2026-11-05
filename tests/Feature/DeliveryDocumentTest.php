<?php

namespace Tests\Feature;

use App\Documents\DocumentNumberAllocator;
use App\Documents\DocumentPdfRenderer;
use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\CompanyProfile;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanEvent;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use App\Quotations\QuotationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class DeliveryDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_an_accepted_quotation_can_start_a_challan_or_invoice(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $customer = $this->party();
        $quotation = $this->createQuotation($sales, $customer);

        foreach ([
            QuotationStatus::DRAFT,
            QuotationStatus::SUBMITTED,
            QuotationStatus::APPROVED,
            QuotationStatus::SENT,
            QuotationStatus::REJECTED,
            QuotationStatus::EXPIRED,
            QuotationStatus::CANCELLED,
        ] as $status) {
            DB::table('quotations')->where('id', $quotation->id)->update(['status' => $status]);
            DB::table('quotation_revisions')->where('quotation_id', $quotation->id)->update(['status' => $status]);
            $quotation->refresh();

            $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation))
                ->assertSessionHasErrors('quotation_id');
            $this->actingAs($sales)->post('/invoices', $this->invoicePayload($quotation, 1))
                ->assertSessionHasErrors('quotation_id');
        }

        $this->assertSame(0, DeliveryChallan::query()->count());
        $accepted = $this->acceptedQuotation($sales, $manager, $customer);
        $this->actingAs($sales)->post('/challans', $this->challanPayload($accepted, '1'))->assertRedirect();
        $this->assertSame(QuotationStatus::ACCEPTED, $accepted->fresh()?->status);
        $this->assertSame($accepted->current_revision_id, DeliveryChallan::query()->firstOrFail()->quotation_revision_id);
    }

    public function test_a_challan_keeps_the_accepted_snapshot_and_partial_deliveries_cannot_exceed_it(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $quotation = $this->acceptedQuotation($sales, $manager, $customer, [
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '10',
                'unit_price' => '1500.50',
            ]],
        ]);
        $revisionId = (int) $quotation->current_revision_id;
        $customer->update(['legal_name' => 'Renamed Customer Ltd.']);

        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, '4', [
            'delivery_location' => 'Site office',
            'vehicle_or_carrier' => 'Covered van',
        ]))->assertRedirect();
        $first = DeliveryChallan::query()->firstOrFail();

        $this->assertNull($first->number);
        $this->assertSame(DocumentStatus::DRAFT, $first->status);
        $this->assertSame($revisionId, (int) $first->quotation_revision_id);
        $this->assertSame('Padma Engineering Ltd.', $first->party_legal_name);
        $this->assertSame('SECRET-NOTE', $first->internal_notes);
        $this->assertSame('4.0000', $first->lines()->firstOrFail()->decimalAttribute('quantity'));

        $this->actingAs($sales)->post(route('challans.issue', $first))->assertRedirect();
        $first->refresh();
        $year = now()->timezone('Asia/Dhaka')->format('Y');
        $this->assertSame('DC-'.$year.'-0001', $first->number);
        $this->assertNotNull($first->company_snapshot);
        $this->assertSame(0, $first->events()->where('event', DeliveryChallanEvent::DISPATCHED)->count());

        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, '6'))->assertRedirect();
        $second = DeliveryChallan::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $second))->assertRedirect();
        $this->assertSame('DC-'.$year.'-0002', $second->fresh()?->number);

        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, '0.0001'))->assertSessionHasErrors('lines.0.quantity');
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, '1.12345'))->assertSessionHasErrors('lines.0.quantity');
        $this->assertSame(2, DeliveryChallan::query()->where('status', DocumentStatus::ISSUED)->count());
        $this->assertSame(2, (int) DB::table('delivery_challan_number_sequences')->where('year', $year)->value('last_number'));

        $customer->refresh();
        $this->assertSame('Padma Engineering Ltd.', $first->fresh()?->party_legal_name);
        $this->assertSame('Renamed Customer Ltd.', $customer->legal_name);
    }

    public function test_a_second_draft_can_be_saved_but_cannot_be_issued_above_the_accepted_quantity(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $quotation = $this->acceptedQuotation($sales, $manager, $customer);
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, '2'))->assertRedirect();
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, '2'))->assertRedirect();
        $challans = DeliveryChallan::query()->orderBy('id')->get();
        $this->assertCount(2, $challans);
        $this->assertNull($challans[0]->number);
        $this->assertNull($challans[1]->number);

        $this->actingAs($sales)->post(route('challans.issue', $challans[0]))->assertRedirect();
        $this->actingAs($sales)->from(route('challans.show', $challans[1]))
            ->post(route('challans.issue', $challans[1]))
            ->assertSessionHasErrors('lines.0.quantity');

        $this->assertSame(DocumentStatus::DRAFT, $challans[1]->fresh()?->status);
        $this->assertNull($challans[1]->fresh()?->number);
        $year = now()->timezone('Asia/Dhaka')->format('Y');
        $this->assertSame(1, (int) DB::table('delivery_challan_number_sequences')->where('year', $year)->value('last_number'));
    }

    public function test_invoices_follow_issued_challan_lines_and_rounding_cannot_exceed_the_accepted_amount(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $quotation = $this->acceptedQuotation($sales, $manager, $customer, [
            'lines' => [[
                'description' => 'Small part',
                'unit' => 'pc',
                'quantity' => '1',
                'unit_price' => '0.01',
            ]],
        ]);
        $line = $quotation->currentRevision?->lines()->firstOrFail();
        $this->assertInstanceOf(QuotationLine::class, $line);
        $this->assertSame('0.01', $line->decimalAttribute('line_total'));

        $firstChallan = $this->issueChallan($sales, $quotation, '0.5');
        $secondChallan = $this->issueChallan($sales, $quotation, '0.5');
        $this->actingAs($sales)->post('/invoices', $this->invoiceFromChallan($quotation, $firstChallan, '0.5'))->assertRedirect();
        $this->actingAs($sales)->post('/invoices', $this->invoiceFromChallan($quotation, $secondChallan, '0.5'))->assertRedirect();
        $invoices = CommercialInvoice::query()->orderBy('id')->get();
        $this->assertSame('0.01', $invoices[0]->lines()->firstOrFail()->decimalAttribute('line_total'));
        $this->assertSame('0.01', $invoices[1]->lines()->firstOrFail()->decimalAttribute('line_total'));

        $this->actingAs($sales)->post(route('invoices.issue', $invoices[0]))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($sales)->post(route('invoices.issue', $invoices[1]))->assertRedirect()->assertSessionHasNoErrors();
        $invoices[0]->refresh();
        $invoices[1]->refresh();
        $year = now()->timezone('Asia/Dhaka')->format('Y');
        $this->assertSame('CI-'.$year.'-0001', $invoices[0]->number);
        $this->assertSame('CI-'.$year.'-0002', $invoices[1]->number);
        $this->assertSame('0.01', $invoices[0]->lines()->firstOrFail()->decimalAttribute('line_total'));
        $this->assertSame('0.00', $invoices[1]->lines()->firstOrFail()->decimalAttribute('line_total'));
        $this->assertSame('0.01', $invoices[0]->decimalAttribute('total'));
        $this->assertSame('0.00', $invoices[1]->decimalAttribute('total'));
        $this->assertSame((int) $firstChallan->lines()->firstOrFail()->id, (int) $invoices[0]->lines()->firstOrFail()->delivery_challan_line_id);
        $this->assertSame((int) $line->getKey(), (int) $invoices[0]->lines()->firstOrFail()->quotation_line_id);
        $this->assertSame($quotation->current_revision_id, $invoices[0]->quotation_revision_id);

        $this->actingAs($sales)->post('/invoices', $this->invoiceFromChallan($quotation, $firstChallan, '0.0001'))
            ->assertSessionHasErrors('lines.0.quantity');
    }

    public function test_a_challan_used_by_an_open_invoice_cannot_be_voided_and_voids_stay_on_record(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $quotation = $this->acceptedQuotation($sales, $manager, $customer);
        $challan = $this->issueChallan($sales, $quotation, '2');
        $this->actingAs($sales)->post('/invoices', $this->invoiceFromChallan($quotation, $challan, '1'))->assertRedirect();
        $invoice = CommercialInvoice::query()->firstOrFail();

        $this->actingAs($sales)->post(route('challans.void', $challan), ['void_reason' => 'Wrong van'])->assertForbidden();
        $this->actingAs($manager)->from(route('challans.show', $challan))
            ->post(route('challans.void', $challan), ['void_reason' => 'Wrong van'])
            ->assertSessionHasErrors('challan');
        $this->assertSame(DocumentStatus::ISSUED, $challan->fresh()?->status);

        $this->actingAs($sales)->post(route('invoices.void', $invoice), ['void_reason' => 'Wrong amount'])->assertForbidden();
        $this->actingAs($manager)->post(route('invoices.void', $invoice), ['void_reason' => 'Wrong amount'])->assertRedirect();
        $this->assertSame(DocumentStatus::VOIDED, $invoice->fresh()?->status);
        $this->assertSame(DocumentStatus::ISSUED, $challan->fresh()?->status);
        $this->assertNull($invoice->fresh()?->number);

        $this->actingAs($manager)->post(route('challans.void', $challan), ['void_reason' => 'Customer returned it'])->assertRedirect();
        $this->assertSame(DocumentStatus::VOIDED, $challan->fresh()?->status);
        $this->assertSame('DC-'.now()->timezone('Asia/Dhaka')->format('Y').'-0001', $challan->fresh()?->number);
        $this->assertSame(1, CommercialInvoice::query()->count());
        $this->assertSame(1, DeliveryChallan::query()->count());
        $this->assertFalse($challan->fresh()?->delete());
        $this->assertFalse($invoice->fresh()?->delete());

        $replacement = $this->issueChallan($sales, $quotation, '2');
        $this->assertSame(DocumentStatus::ISSUED, $replacement->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'delivery_challan_voided']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'commercial_invoice_voided']);
    }

    public function test_issued_documents_are_immutable_and_a_pdf_does_not_record_dispatch(): void
    {
        [$sales, $manager, $customer] = $this->team();
        CompanyProfile::query()->create([
            'legal_name' => 'Issued Letterhead Ltd.',
            'financial_year_starts_on' => '2025-07-01',
            'financial_year_ends_on' => '2026-06-30',
            'currency_code' => 'BDT',
        ]);
        $quotation = $this->acceptedQuotation($sales, $manager, $customer);
        $challan = $this->issueChallan($sales, $quotation, '1');
        CompanyProfile::query()->update(['legal_name' => 'Later Letterhead Ltd.']);

        $html = view('challans.pdf', app(DocumentPdfRenderer::class)->challanData($challan->fresh() ?? $challan))->render();
        $this->assertStringContainsString('Issued Letterhead Ltd.', $html);
        $this->assertStringNotContainsString('Later Letterhead Ltd.', $html);
        $this->assertStringNotContainsString('SECRET-NOTE', $html);
        $this->assertStringContainsString('Dispatched: not recorded', $html);
        $this->assertStringContainsString('Acknowledged: not recorded', $html);
        $this->assertStringContainsString('does not record dispatch or receipt', $html);
        $this->assertStringNotContainsString('1500.50', $html);

        $pdf = $this->actingAs($sales)->get(route('challans.pdf', $challan));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertMatchesRegularExpression('/private/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/no-store/', (string) $pdf->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $challan->refresh();
        $this->assertNull($challan->dispatched_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'delivery_challan_pdf_downloaded']);
        $this->actingAsGuest()->get(route('challans.pdf', $challan))->assertRedirect(route('login'));

        $this->actingAs($sales)->post(route('challans.acknowledge', $challan), [
            'acknowledgment_name' => 'Nadia Rahman',
        ])->assertRedirect();
        $this->assertNotNull($challan->fresh()?->acknowledged_at);
        $this->assertNull($challan->fresh()?->dispatched_at);
        $this->actingAs($sales)->post(route('challans.dispatch', $challan), [])->assertRedirect();
        $this->actingAs($sales)->post(route('challans.dispatch', $challan), [])->assertSessionHasErrors('challan');

        $challan->refresh();
        $line = $challan->lines()->firstOrFail();
        try {
            $line->description = 'Changed';
            $line->save();
            $this->fail('An issued challan line was changed.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be changed', $exception->getMessage());
        }

        $this->assertFalse($line->delete());
        $this->assertFalse($challan->delete());
        $event = $challan->events()->firstOrFail();
        try {
            $event->remarks = 'Changed';
            $event->save();
            $this->fail('An event was changed.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->actingAs($sales)->get(route('challans.edit', $challan))->assertForbidden();
        $this->actingAs($sales)->put(route('challans.update', $challan), $this->challanPayload($quotation, '1'))->assertForbidden();
    }

    public function test_a_rolled_back_number_is_not_consumed_and_drafts_do_not_take_one(): void
    {
        $year = now()->timezone('Asia/Dhaka')->format('Y');
        DB::beginTransaction();
        $first = app(DocumentNumberAllocator::class)->allocate(DocumentNumberAllocator::CHALLAN);
        DB::rollBack();
        $second = app(DocumentNumberAllocator::class)->allocate(DocumentNumberAllocator::INVOICE);

        $this->assertSame('DC-'.$year.'-0001', $first);
        $this->assertSame('CI-'.$year.'-0001', $second);
        $this->assertSame(0, (int) DB::table('delivery_challan_number_sequences')->where('year', $year)->value('last_number'));
        $this->assertSame(1, (int) DB::table('commercial_invoice_number_sequences')->where('year', $year)->value('last_number'));
    }

    public function test_an_invoice_cannot_use_another_quotations_challan(): void
    {
        [$sales, $manager, $customer] = $this->team();
        $first = $this->acceptedQuotation($sales, $manager, $customer);
        $second = $this->acceptedQuotation($sales, $manager, $customer, [
            'lines' => [[
                'description' => 'Other work',
                'unit' => 'job',
                'quantity' => '1',
                'unit_price' => '20.00',
            ]],
        ]);
        $challan = $this->issueChallan($sales, $first, '1');

        $this->actingAs($sales)->post('/invoices', $this->invoiceFromChallan($second, $challan, '1'))
            ->assertSessionHasErrors('lines.0.delivery_challan_line_id');
        $this->assertSame(0, CommercialInvoiceLine::query()->count());
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

    private function acceptedQuotation(User $sales, User $manager, Party $customer, array $overrides = []): Quotation
    {
        return $this->accept($sales, $manager, $this->createQuotation($sales, $customer, $overrides));
    }

    private function createQuotation(User $sales, Party $customer, array $overrides = []): Quotation
    {
        $this->actingAs($sales)->post('/quotations', $this->payload($customer, $overrides))->assertRedirect();

        return Quotation::query()->orderByDesc('id')->firstOrFail();
    }

    private function accept(User $sales, User $manager, Quotation $quotation): Quotation
    {
        if ($quotation->status === QuotationStatus::DRAFT) {
            $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
            $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
            $this->actingAs($sales)->post(route('quotations.send', $quotation), [
                'recipient_label' => 'Nadia Rahman',
            ])->assertRedirect();
        }

        $this->actingAs($sales)->post(route('quotations.respond', $quotation), [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ])->assertRedirect();

        return $quotation->fresh() ?? $quotation;
    }

    private function issueChallan(User $sales, Quotation $quotation, string $quantity): DeliveryChallan
    {
        $this->actingAs($sales)->post('/challans', $this->challanPayload($quotation, $quantity))->assertRedirect();
        $challan = DeliveryChallan::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($sales)->post(route('challans.issue', $challan))->assertRedirect();

        return $challan->fresh() ?? $challan;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function challanPayload(Quotation $quotation, string $quantity = '2', array $extra = []): array
    {
        $quotation->loadMissing('currentRevision.lines');
        $line = $quotation->currentRevision?->lines->first();
        $this->assertInstanceOf(QuotationLine::class, $line);

        return array_merge([
            'quotation_id' => $quotation->id,
            'challan_date' => '2026-11-04',
            'customer_notes' => 'Leave at reception.',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => $quantity,
            ]],
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Quotation $quotation, int $challanLineId): array
    {
        return [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-04',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'delivery_challan_line_id' => $challanLineId,
                'quantity' => '1',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceFromChallan(Quotation $quotation, DeliveryChallan $challan, string $quantity): array
    {
        return [
            'quotation_id' => $quotation->id,
            'invoice_date' => '2026-11-04',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                'quantity' => $quantity,
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
            'phone' => '01711000001',
        ], $overrides));
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
            'terms' => 'Payment within 15 days.',
            'customer_notes' => 'Site visit included.',
            'internal_notes' => 'SECRET-NOTE',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => '2',
                'unit_price' => '1500.50',
            ]],
        ], $overrides);
    }
}
