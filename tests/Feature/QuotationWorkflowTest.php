<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Models\QuotationSend;
use App\Models\User;
use App\Quotations\QuotationNumberAllocator;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationStatus;
use App\Quotations\QuotationWorkflow;
use App\Quotations\QuotationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_creator_cannot_approve_and_a_draft_cannot_be_accepted(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $super = $this->userWithRole('super-administrator');
        $customer = $this->party();
        $supplier = $this->party('supplier', ['legal_name' => 'Only Supplier Ltd.']);

        $this->actingAs($sales)->post('/quotations', $this->payload($supplier))->assertSessionHasErrors('party_id');
        $this->actingAs($sales)->post('/quotations', $this->payload($customer))->assertRedirect();
        $year = now()->timezone('Asia/Dhaka')->format('Y');
        $this->assertSame('QT-'.$year.'-0001', Quotation::query()->sole()->number);

        $own = $this->createQuotation($super, $customer);
        $this->actingAs($super)->post(route('quotations.submit', $own))->assertRedirect();
        $this->actingAs($super)->post(route('quotations.approve', $own))->assertForbidden();

        try {
            app(QuotationWorkflow::class)->approve($own->fresh() ?? $own, $super);
            $this->fail('The creator must not approve their own revision.');
        } catch (QuotationRuleException $exception) {
            $this->assertStringContainsString('cannot approve', $exception->getMessage());
        }

        $this->assertSame(QuotationStatus::SUBMITTED, $own->fresh()?->status);
        $this->actingAs($manager)->post(route('quotations.approve', $own))->assertRedirect();
        $this->assertSame(QuotationStatus::APPROVED, $own->fresh()?->status);

        $draft = $this->createQuotation($sales, $customer);
        $this->actingAs($sales)->post(route('quotations.respond', $draft), [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'confirmed' => '1',
        ])->assertForbidden();

        try {
            app(QuotationWorkflow::class)->respond(
                $draft->fresh() ?? $draft,
                $sales,
                'accepted',
                'internal',
                'Nadia Rahman',
                null,
                null,
                null,
                '127.0.0.1',
                'phpunit',
            );
            $this->fail('A draft cannot be accepted.');
        } catch (QuotationRuleException) {
            $this->assertSame(QuotationStatus::DRAFT, $draft->fresh()?->status);
            $this->assertDatabaseCount('quotation_responses', 0);
        }
    }

    public function test_return_keeps_the_revision_and_a_new_revision_preserves_the_issued_copy(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $customer = $this->party();
        $quotation = $this->createQuotation($sales, $customer);
        $originalId = $quotation->current_revision_id;

        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.return', $quotation), [
            'remarks' => 'Please correct the quantity.',
        ])->assertRedirect();

        $quotation->refresh();
        $this->assertSame($originalId, $quotation->current_revision_id);
        $this->assertSame(QuotationStatus::DRAFT, $quotation->status);
        $this->assertDatabaseHas('quotation_approvals', [
            'quotation_revision_id' => $originalId,
            'action' => 'returned',
        ]);

        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();

        $issued = QuotationRevision::query()->findOrFail($originalId);
        $issued->terms = 'Changed after approval';

        try {
            $issued->save();
            $this->fail('An issued revision must keep its content.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot change its content', $exception->getMessage());
        }

        $this->actingAs($sales)->post(route('quotations.revisions.store', $quotation), $this->payload($customer, [
            'lines' => [[
                'description' => 'Revised cable route',
                'unit' => 'meter',
                'quantity' => '3',
                'unit_price' => '100.00',
            ]],
        ]))->assertRedirect();

        $quotation->refresh();
        $issued->refresh();
        $current = $quotation->currentRevision()->firstOrFail();

        $this->assertSame(QuotationStatus::APPROVED, $issued->status);
        $this->assertSame('Network survey', $issued->lines()->first()?->description);
        $this->assertSame('Payment within 15 days.', $issued->terms);
        $this->assertSame(2, $current->revision_number);
        $this->assertSame(QuotationStatus::DRAFT, $current->status);
        $this->assertSame(QuotationStatus::DRAFT, $quotation->status);
        $this->assertSame('Revised cable route', $current->lines()->first()?->description);
        $this->assertNotSame($issued->id, $current->id);
        $this->assertSame(2, QuotationRevision::query()->where('quotation_id', $quotation->id)->count());
        $this->assertSame(1, QuotationLine::query()->where('quotation_revision_id', $issued->id)->count());
    }

    public function test_send_does_not_email_and_closed_quotations_cannot_be_revised(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $customer = $this->party();
        $quotation = $this->advanceToSent($sales, $manager, $customer);

        $this->assertSame(QuotationStatus::SENT, $quotation->status);
        $this->assertDatabaseHas('quotation_sends', [
            'quotation_revision_id' => $quotation->current_revision_id,
            'delivery_status' => QuotationSend::EMAIL_NOT_CONFIGURED,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation_sent']);

        $accepted = $this->advanceToSent($sales, $manager, $customer);
        $this->actingAs($sales)->post(route('quotations.respond', $accepted), [
            'decision' => 'accepted',
            'respondent_name' => 'Nadia Rahman',
            'respondent_designation' => 'Manager',
            'confirmed' => '1',
        ])->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.revisions.store', $accepted), $this->payload($customer))->assertForbidden();
        $this->assertSame(QuotationStatus::ACCEPTED, $accepted->fresh()?->status);

        try {
            app(QuotationWriter::class)->revise($accepted->fresh() ?? $accepted, $sales, $this->payload($customer));
            $this->fail('An accepted quotation cannot be revised.');
        } catch (QuotationRuleException) {
            $this->assertSame(1, QuotationRevision::query()->where('quotation_id', $accepted->id)->count());
        }

        $cancelled = $this->createQuotation($sales, $customer);
        $this->actingAs($sales)->post(route('quotations.cancel', $cancelled), [
            'cancel_reason' => 'Customer postponed the work.',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('quotations.cancel', $cancelled), [
            'cancel_reason' => 'Customer postponed the work.',
        ])->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.revisions.store', $cancelled), $this->payload($customer))->assertForbidden();
        $this->assertNotNull(Quotation::query()->find($cancelled->id));
        $this->assertSame(QuotationStatus::CANCELLED, $cancelled->fresh()?->status);
    }

    public function test_numbers_are_sequential_roll_back_and_follow_the_dhaka_year(): void
    {
        $allocator = app(QuotationNumberAllocator::class);
        $year = (int) now()->timezone('Asia/Dhaka')->format('Y');

        DB::beginTransaction();
        $rolledBack = $allocator->allocate();
        DB::rollBack();

        $first = $allocator->allocate();
        $second = $allocator->allocate();

        $this->assertSame($rolledBack, $first);
        $this->assertSame(sprintf('QT-%d-0001', $year), $first);
        $this->assertSame(sprintf('QT-%d-0002', $year), $second);
        $this->assertSame(2, (int) DB::table('quotation_number_sequences')->where('year', $year)->value('last_number'));
    }

    public function test_the_sequence_year_changes_at_midnight_in_dhaka(): void
    {
        $allocator = app(QuotationNumberAllocator::class);

        try {
            Carbon::setTestNow(Carbon::parse('2026-12-31 17:00:00', 'UTC'));
            $this->assertSame('QT-2026-0001', $allocator->allocate());

            Carbon::setTestNow(Carbon::parse('2026-12-31 18:30:00', 'UTC'));
            $this->assertSame('QT-2027-0001', $allocator->allocate());
            $this->assertSame(1, (int) DB::table('quotation_number_sequences')->where('year', 2027)->value('last_number'));
            $this->assertSame(1, (int) DB::table('quotation_number_sequences')->where('year', 2026)->value('last_number'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_a_past_validity_date_can_expire_an_approved_quotation(): void
    {
        $sales = $this->userWithRole('sales-executive');
        $manager = $this->userWithRole('sales-manager');
        $customer = $this->party();
        $quotation = $this->createQuotation($sales, $customer, [
            'quotation_date' => '2026-10-01',
            'valid_until' => '2026-10-15',
        ]);

        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.expire', $quotation))->assertRedirect();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::EXPIRED, $quotation->status);
        $this->assertSame(QuotationStatus::EXPIRED, $quotation->currentRevision?->status);
        $this->assertNotNull($quotation->currentRevision?->expired_at);
        $this->actingAs($sales)->post(route('quotations.send', $quotation))->assertForbidden();
    }

    private function advanceToSent(User $sales, User $manager, Party $customer): Quotation
    {
        $quotation = $this->createQuotation($sales, $customer);
        $this->actingAs($sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($sales)->post(route('quotations.send', $quotation), [
            'recipient_label' => 'Nadia Rahman',
        ])->assertRedirect()->assertSessionHas('status', 'Send recorded. No email was sent.');

        return $quotation->fresh() ?? $quotation;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createQuotation(User $actor, Party $customer, array $overrides = []): Quotation
    {
        $this->actingAs($actor)->post('/quotations', $this->payload($customer, $overrides))->assertRedirect();

        return Quotation::query()->orderByDesc('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function party(string $role = 'customer', array $overrides = []): Party
    {
        $party = Party::query()->create(array_merge([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
            'tin' => '012345678901',
            'email' => 'accounts@padma.example',
            'phone' => '01711000001',
        ], $overrides));
        $party->roles()->create(['role' => $role]);

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
