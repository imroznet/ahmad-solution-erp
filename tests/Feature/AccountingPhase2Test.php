<?php

namespace Tests\Feature;

use App\Accounting\AccountType;
use App\Accounting\JournalSource;
use App\Accounting\JournalWriter;
use App\Accounting\ReceivablesAging;
use App\Documents\DocumentStatus;
use App\Models\Account;
use App\Models\CommercialInvoice;
use App\Models\CompanyAddress;
use App\Models\CompanyProfile;
use App\Models\DeliveryChallan;
use App\Models\FinancialYear;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\MushakInvoice;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Receipt;
use App\Models\User;
use App\Models\VdsRecord;
use App\Quotations\QuotationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingPhase2Test extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private User $manager;

    private User $accountant;

    private User $accountsManager;

    private Party $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Dhaka'));
        FinancialYear::query()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
        ]);
        $this->sales = $this->userWithRole('sales-executive');
        $this->manager = $this->userWithRole('sales-manager');
        $this->accountant = $this->userWithRole('accountant');
        $this->accountsManager = $this->userWithRole('accounts-manager');
        $this->customer = $this->party('Padma Engineering Ltd.');
        $this->company();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_issued_invoice_posts_revenue_and_a_zero_total_posts_nothing(): void
    {
        $invoice = $this->issuedInvoice($this->customer, '2026-11-06', '1', '1500.00');
        $journal = $this->onlyJournal(JournalSource::Invoice, (int) $invoice->id);

        $this->assertSame('commercial_invoice', $journal->source_type);
        $this->assertSame('2026-11-06', $journal->journalDateString());
        $this->assertSame('Commercial invoice '.$invoice->number, $journal->memo);
        $this->assertSame([
            ['accounts_receivable', '1500.00', '0.00', $this->customer->id],
            ['sales_revenue', '0.00', '1500.00', null],
        ], $this->postingLines($journal));

        $this->actingAs($this->sales)->post(route('invoices.issue', $invoice))->assertSessionHasErrors();
        $this->assertSame(1, $this->journalsFor(JournalSource::Invoice, (int) $invoice->id));

        $zero = $this->issuedInvoice($this->customer, '2026-11-06', '1', '0.00');
        $this->assertSame(0, $this->journalsFor(JournalSource::Invoice, (int) $zero->id));
        $this->assertSame('0.00', $zero->decimalAttribute('total'));
    }

    public function test_an_issued_mushak_posts_vat_rounded_half_up_and_zero_vat_posts_nothing(): void
    {
        $invoice = $this->issuedInvoice($this->customer, '2026-11-06', '1', '100.00');
        $mushak = $this->draftMushak($invoice, '2026-11-06', '0');
        $mushak->forceFill([
            'subtotal_base' => '100.0000',
            'total_vat' => '15.5550',
            'total_adjustment' => '0.0000',
            'total_final' => '115.5550',
        ])->save();
        $this->actingAs($this->accountant)->post(route('mushak.issue', $mushak))->assertRedirect()->assertSessionHasNoErrors();
        $mushak->refresh();
        $journal = $this->onlyJournal(JournalSource::Mushak, (int) $mushak->id);

        $this->assertSame('mushak_invoice', $journal->source_type);
        $this->assertSame('2026-11-06', $journal->journalDateString());
        $this->assertSame('Mushak 6.3 '.$mushak->number, $journal->memo);
        $this->assertSame([
            ['accounts_receivable', '15.56', '0.00', $this->customer->id],
            ['output_vat_payable', '0.00', '15.56', null],
        ], $this->postingLines($journal));

        $this->actingAs($this->accountant)->post(route('mushak.issue', $mushak))->assertNotFound();
        $this->assertSame(1, $this->journalsFor(JournalSource::Mushak, (int) $mushak->id));

        $plain = $this->issuedInvoice($this->customer, '2026-11-06', '1', '100.00');
        $zero = $this->draftMushak($plain, '2026-11-06', '0');
        $this->actingAs($this->accountant)->post(route('mushak.issue', $zero))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $this->journalsFor(JournalSource::Mushak, (int) $zero->id));
    }

    public function test_void_reverses_once_and_a_document_without_a_posting_still_voids(): void
    {
        $invoice = $this->issuedInvoice($this->customer, '2026-11-06', '1', '1500.00');
        $mushak = $this->draftMushak($invoice, '2026-11-06', '15');
        $this->actingAs($this->accountant)->post(route('mushak.issue', $mushak))->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->manager)
            ->post(route('invoices.void', $invoice), ['void_reason' => 'Wrong amount'])
            ->assertSessionHasErrors('commercial_invoice_id');
        $this->assertSame(DocumentStatus::ISSUED, $invoice->fresh()?->stringAttribute('status'));
        $this->assertSame(0, Journal::query()->where('source', JournalSource::Reversal)->count());

        $this->actingAs($this->accountsManager)->post(route('mushak.void', $mushak), ['void_reason' => 'Wrong VAT'])->assertRedirect();
        $this->actingAs($this->accountsManager)->post(route('mushak.void', $mushak), ['void_reason' => 'Again'])->assertNotFound();
        $this->assertSame(1, Journal::query()->where('source', JournalSource::Reversal)->where('reverses_journal_id', $this->onlyJournal(JournalSource::Mushak, (int) $mushak->id)->id)->count());

        $posted = $this->onlyJournal(JournalSource::Invoice, (int) $invoice->id);
        $this->actingAs($this->manager)->post(route('invoices.void', $invoice), ['void_reason' => 'Wrong amount'])->assertRedirect();
        $this->actingAs($this->manager)->post(route('invoices.void', $invoice), ['void_reason' => 'Again'])->assertSessionHasErrors();
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $posted->id)->count());

        $challan = DeliveryChallan::query()->firstOrFail();
        $before = Journal::query()->count();
        $this->actingAs($this->manager)->post(route('challans.void', $challan), ['void_reason' => 'Delivered in error'])->assertRedirect();
        $this->assertSame($before, Journal::query()->count());

        $earlier = $this->issuedInvoice($this->customer, '2026-11-08', '1', '40.00');
        $old = $this->onlyJournal(JournalSource::Invoice, (int) $earlier->id);
        DB::table('journal_lines')->where('journal_id', $old->id)->delete();
        DB::table('journal_entries')->where('id', $old->id)->delete();
        $this->actingAs($this->manager)->post(route('invoices.void', $earlier), ['void_reason' => 'Issued before the ledger'])->assertRedirect();
        $this->assertSame(DocumentStatus::VOIDED, $earlier->fresh()?->stringAttribute('status'));
        $this->assertSame(0, Journal::query()->where('reverses_journal_id', $old->id)->count());
    }

    public function test_a_receipt_allocates_partially_and_fully_and_refuses_invalid_allocations(): void
    {
        $invoice = $this->issuedInvoice($this->customer, '2026-11-06', '1', '1500.00');
        $cash = Account::query()->where('system_key', 'cash')->firstOrFail();

        $this->actingAs($this->sales)->get(route('receipts.create'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('receipts.create'))->assertOk()->assertSee('Cash in Hand');

        $partial = $this->recordReceipt($invoice, '500.00', '400.00', '2026-11-21');
        $this->assertSame('RCPT-2026-0001', $partial->number);
        $this->assertSame([
            ['cash', '500.00', '0.00', null],
            ['accounts_receivable', '0.00', '500.00', $this->customer->id],
        ], $this->postingLines($this->onlyJournal(JournalSource::Receipt, (int) $partial->id)));

        $full = $this->recordReceipt($invoice, '1100.00', '1100.00', '2026-11-21');
        $this->assertSame('1100.00', $full->allocations()->firstOrFail()->decimalAttribute('amount'));

        $this->recordReceipt($invoice, '1.00', '1.00', '2026-11-21', false);
        $this->assertSame(2, Receipt::query()->count());

        $draft = $this->draftInvoice($this->customer, '2026-11-06', '1', '80.00');
        $this->recordReceipt($draft, '10.00', '10.00', '2026-11-21', false);

        $other = $this->party('Other Buyer Ltd.');
        $otherInvoice = $this->issuedInvoice($other, '2026-11-06', '1', '80.00');
        $this->actingAs($this->accountant)->post(route('receipts.store'), $this->receiptPayload($this->customer, $cash, '2026-11-21', '10.00', $otherInvoice, '10.00'))->assertSessionHasErrors('receipt');

        $early = $this->issuedInvoice($this->customer, '2026-11-20', '1', '80.00');
        $this->actingAs($this->accountant)->post(route('receipts.store'), $this->receiptPayload($this->customer, $cash, '2026-11-10', '10.00', $early, '10.00'))->assertSessionHasErrors('receipt');

        $voided = $this->issuedInvoice($this->customer, '2026-11-09', '1', '80.00');
        $this->actingAs($this->manager)->post(route('invoices.void', $voided), ['void_reason' => 'Not sent'])->assertRedirect();
        $this->actingAs($this->accountant)->post(route('receipts.store'), $this->receiptPayload($this->customer, $cash, '2026-11-21', '10.00', $voided, '10.00'))->assertSessionHasErrors('receipt');
    }

    public function test_vds_and_ait_post_only_through_a_receipt_and_voiding_frees_the_invoice(): void
    {
        $invoice = $this->issuedInvoice($this->customer, '2026-11-06', '1', '1500.00');
        $this->actingAs($this->accountant)->post('/tax/vds', [
            'direction' => 'withheld_from_us',
            'record_date' => '2026-11-21',
            'counterparty_name' => 'Padma Engineering Ltd.',
            'taxable_basis' => '1500.00',
            'deducted_amount' => '75.00',
        ])->assertRedirect();
        $vds = VdsRecord::query()->firstOrFail();
        $this->actingAs($this->accountant)->post(route('deductions.record', ['kind' => 'vds', 'record' => $vds->id]))->assertRedirect();
        $this->assertSame(0, JournalLine::query()->where('account_id', $this->accountId('vds_receivable'))->count());
        $this->assertSame(0, JournalLine::query()->where('account_id', $this->accountId('output_vat_payable'))->count());

        $cash = Account::query()->where('system_key', 'cash')->firstOrFail();
        $this->actingAs($this->accountant)->post(route('receipts.store'), [
            'party_id' => $this->customer->id,
            'account_id' => $cash->id,
            'receipt_date' => '2026-11-21',
            'amount_received' => '100.00',
            'vds_deducted' => '10.00',
            'ait_deducted' => '5.00',
            'mode' => 'bank_transfer',
            'reference' => 'TX-100',
            'allocations' => [[
                'commercial_invoice_id' => $invoice->id,
                'amount' => '115.00',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $receipt = Receipt::query()->firstOrFail();
        $journal = $this->onlyJournal(JournalSource::Receipt, (int) $receipt->id);
        $this->assertSame([
            ['cash', '100.00', '0.00', null],
            ['vds_receivable', '10.00', '0.00', $this->customer->id],
            ['ait_receivable', '5.00', '0.00', $this->customer->id],
            ['accounts_receivable', '0.00', '115.00', $this->customer->id],
        ], $this->postingLines($journal));
        $this->assertFalse($journal->lines()->where('account_id', $this->accountId('output_vat_payable'))->exists());

        $this->actingAs($this->manager)
            ->post(route('invoices.void', $invoice), ['void_reason' => 'Paid already'])
            ->assertSessionHasErrors('commercial_invoice_id');

        $this->actingAs($this->accountant)->post(route('receipts.void', $receipt), [
            'void_reason' => 'Recorded on the wrong customer',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $journal->id)->count());
        $this->actingAs($this->accountant)->post(route('receipts.void', $receipt), [
            'void_reason' => 'Again',
        ])->assertSessionHasErrors('receipt');
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $journal->id)->count());

        $this->actingAs($this->manager)->post(route('invoices.void', $invoice), ['void_reason' => 'Paid already'])->assertRedirect();
        $this->assertSame(DocumentStatus::VOIDED, $invoice->fresh()?->stringAttribute('status'));
    }

    public function test_receivables_aging_buckets_match_the_posted_receivable(): void
    {
        $quotation = $this->acceptedQuotation($this->customer, '4', '100.00', '2026-08-15');
        $challan = $this->issuedChallan($quotation, '4', '2026-09-01');
        $current = $this->invoiceFromChallan($quotation, $challan, '2026-11-21');
        $recent = $this->invoiceFromChallan($quotation, $challan, '2026-11-05');
        $middle = $this->invoiceFromChallan($quotation, $challan, '2026-10-12');
        $older = $this->invoiceFromChallan($quotation, $challan, '2026-09-01');
        $mushak = $this->draftMushak($middle, '2026-10-12', '15');
        $this->actingAs($this->accountant)->post(route('mushak.issue', $mushak))->assertRedirect()->assertSessionHasNoErrors();

        $equity = Account::query()->create([
            'code' => '3100',
            'name' => 'Owner equity',
            'type' => AccountType::Equity,
            'is_active' => true,
        ]);
        app(JournalWriter::class)->postOpening([
            'journal_date' => '2026-08-01',
            'memo' => 'Opening receivable',
            'lines' => [
                ['account_id' => $this->accountId('accounts_receivable'), 'debit' => '50.00', 'credit' => '', 'party_id' => $this->customer->id, 'memo' => ''],
                ['account_id' => $equity->id, 'debit' => '', 'credit' => '50.00', 'party_id' => '', 'memo' => ''],
            ],
        ], $this->accountant);
        $this->recordReceipt($middle, '80.00', '40.00', '2026-11-21');

        $report = app(ReceivablesAging::class)->build();
        $this->assertSame('2026-11-21', $report['as_of']);
        $this->assertCount(1, $report['parties']);
        $party = $report['parties'][0];
        $this->assertSame($this->customer->id, $party['party_id']);
        $this->assertSame('40.00', $party['advance']);
        $this->assertSame('385.00', $party['total']);
        $this->assertSame($party['total'], $party['receivable']);
        $this->assertSame('385.00', $report['total']);

        $byLabel = [];

        foreach ($party['lines'] as $line) {
            $byLabel[$line['label']] = $line;
        }

        $this->assertSame('current', $byLabel[$current->number]['bucket']);
        $this->assertSame('100.00', $byLabel[$current->number]['outstanding']);
        $this->assertSame('1_30', $byLabel[$recent->number]['bucket']);
        $this->assertSame('31_60', $byLabel[$middle->number]['bucket']);
        $this->assertSame('115.00', $byLabel[$middle->number]['invoiced']);
        $this->assertSame('40.00', $byLabel[$middle->number]['allocated']);
        $this->assertSame('75.00', $byLabel[$middle->number]['outstanding']);
        $this->assertSame('61_90', $byLabel[$older->number]['bucket']);
        $this->assertSame('90_plus', $byLabel['Opening balance']['bucket']);
        $this->assertSame('50.00', $byLabel['Opening balance']['outstanding']);

        $this->actingAs($this->accountsManager)->get(route('reports.receivables-aging'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('reports.receivables-aging'))
            ->assertOk()
            ->assertSee('Opening balance')
            ->assertSee('31–60')
            ->assertSee('385.00');
    }

    public function test_aging_leaves_out_documents_dated_after_today(): void
    {
        $current = $this->issuedInvoice($this->customer, '2026-11-21', '1', '100.00');
        $future = $this->issuedInvoice($this->customer, '2026-11-22', '1', '25.00');
        $this->recordReceipt($current, '10.00', '10.00', '2026-11-22');

        $report = app(ReceivablesAging::class)->build();
        $party = $report['parties'][0];
        $labels = array_column($party['lines'], 'label');

        $this->assertNotContains($future->number, $labels);
        $this->assertSame('100.00', $party['lines'][0]['outstanding']);
        $this->assertSame('0.00', $party['advance']);
        $this->assertSame('100.00', $party['total']);
        $this->assertSame($party['total'], $party['receivable']);
    }

    public function test_aging_includes_a_manual_receivable_for_a_customer_with_no_invoice(): void
    {
        $other = $this->party('Meghna Traders');
        $equity = Account::query()->create([
            'code' => '3100',
            'name' => 'Owner equity',
            'type' => AccountType::Equity,
            'is_active' => true,
        ]);
        $writer = app(JournalWriter::class);
        $journal = $writer->createDraft([
            'journal_date' => '2026-11-01',
            'memo' => 'Opening correction',
            'lines' => [
                ['account_id' => $this->accountId('accounts_receivable'), 'debit' => '25.00', 'credit' => '', 'party_id' => $other->id, 'memo' => ''],
                ['account_id' => $equity->id, 'debit' => '', 'credit' => '25.00', 'party_id' => '', 'memo' => ''],
            ],
        ], $this->accountant);
        $writer->post($journal, $this->accountant);

        $report = app(ReceivablesAging::class)->build();
        $this->assertCount(1, $report['parties']);
        $party = $report['parties'][0];
        $this->assertSame($other->id, $party['party_id']);
        $this->assertSame('Adjustment '.$journal->number, $party['lines'][0]['label']);
        $this->assertSame('25.00', $party['lines'][0]['outstanding']);
        $this->assertSame('25.00', $party['total']);
        $this->assertSame($party['total'], $party['receivable']);
        $this->assertSame('25.00', $report['total']);
    }

    public function test_a_second_receipt_cannot_allocate_more_than_the_invoice_has_left(): void
    {
        $invoice = $this->issuedInvoice($this->customer, '2026-11-06', '1', '100.00');
        $this->recordReceipt($invoice, '60.00', '60.00', '2026-11-21');
        $this->recordReceipt($invoice, '50.00', '50.00', '2026-11-21', false);
        $this->assertSame(1, Receipt::query()->where('status', 'recorded')->count());
    }

    private function recordReceipt(CommercialInvoice $invoice, string $received, string $allocated, string $date, bool $ok = true): Receipt
    {
        $cash = Account::query()->where('system_key', 'cash')->firstOrFail();
        $response = $this->actingAs($this->accountant)->post(route('receipts.store'), $this->receiptPayload($this->customer, $cash, $date, $received, $invoice, $allocated));

        if (! $ok) {
            $response->assertSessionHasErrors('receipt');

            return new Receipt;
        }

        $response->assertRedirect()->assertSessionHasNoErrors();

        return Receipt::query()->orderByDesc('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(Party $customer, Account $account, string $date, string $received, CommercialInvoice $invoice, string $allocated): array
    {
        return [
            'party_id' => $customer->id,
            'account_id' => $account->id,
            'receipt_date' => $date,
            'amount_received' => $received,
            'vds_deducted' => '0.00',
            'ait_deducted' => '0.00',
            'mode' => 'cash',
            'reference' => '',
            'allocations' => [[
                'commercial_invoice_id' => $invoice->id,
                'amount' => $allocated,
            ]],
        ];
    }

    private function issuedInvoice(Party $customer, string $date, string $quantity, string $price): CommercialInvoice
    {
        $quotation = $this->acceptedQuotation($customer, $quantity, $price, '2026-11-01');
        $challan = $this->issuedChallan($quotation, $quantity, $date);

        return $this->invoiceFromChallan($quotation, $challan, $date);
    }

    private function draftInvoice(Party $customer, string $date, string $quantity, string $price): CommercialInvoice
    {
        $quotation = $this->acceptedQuotation($customer, $quantity, $price, '2026-11-01');
        $challan = $this->issuedChallan($quotation, $quantity, $date);
        $this->actingAs($this->sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => $date,
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();

        return CommercialInvoice::query()->orderByDesc('id')->firstOrFail();
    }

    private function invoiceFromChallan(Quotation $quotation, DeliveryChallan $challan, string $date): CommercialInvoice
    {
        $this->actingAs($this->sales)->post('/invoices', [
            'quotation_id' => $quotation->id,
            'invoice_date' => $date,
            'lines' => [[
                'delivery_challan_line_id' => $challan->lines()->firstOrFail()->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();
        $invoice = CommercialInvoice::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($this->sales)->post(route('invoices.issue', $invoice))->assertRedirect()->assertSessionHasNoErrors();

        return $invoice->fresh() ?? $invoice;
    }

    private function issuedChallan(Quotation $quotation, string $quantity, string $date): DeliveryChallan
    {
        $this->actingAs($this->sales)->post('/challans', $this->challanPayload($quotation, $quantity, $date))->assertRedirect();
        $challan = DeliveryChallan::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($this->sales)->post(route('challans.issue', $challan))->assertRedirect()->assertSessionHasNoErrors();

        return $challan->fresh() ?? $challan;
    }

    private function acceptedQuotation(Party $customer, string $quantity, string $price, string $date): Quotation
    {
        $this->actingAs($this->sales)->post('/quotations', [
            'party_id' => $customer->id,
            'quotation_date' => $date,
            'valid_until' => '2026-12-31',
            'lines' => [[
                'description' => 'Network survey',
                'unit' => 'job',
                'quantity' => $quantity,
                'unit_price' => $price,
            ]],
        ])->assertRedirect();
        $quotation = Quotation::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($this->sales)->post(route('quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($this->manager)->post(route('quotations.approve', $quotation))->assertRedirect();
        $this->actingAs($this->sales)->post(route('quotations.send', $quotation), [
            'recipient_label' => 'Nadia Rahman',
        ])->assertRedirect();
        $this->actingAs($this->sales)->post(route('quotations.respond', $quotation), [
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
    private function challanPayload(Quotation $quotation, string $quantity, string $date): array
    {
        $quotation->loadMissing('currentRevision.lines');
        $line = $quotation->currentRevision?->lines->first();
        $this->assertInstanceOf(QuotationLine::class, $line);

        return [
            'quotation_id' => $quotation->id,
            'challan_date' => $date,
            'lines' => [[
                'quotation_line_id' => $line->id,
                'quantity' => $quantity,
            ]],
        ];
    }

    private function draftMushak(CommercialInvoice $invoice, string $date, string $rate): MushakInvoice
    {
        $this->actingAs($this->accountant)->post('/tax/mushak', ['commercial_invoice_id' => $invoice->id])->assertRedirect();
        $mushak = MushakInvoice::query()->where('commercial_invoice_id', $invoice->id)->firstOrFail();
        $lineId = (int) $mushak->lines()->firstOrFail()->id;
        $this->actingAs($this->accountant)->put(route('mushak.update', $mushak), [
            'document_date' => $date,
            'destination' => 'Site office, Dhaka',
            'calculation_precision' => 2,
            'rounding_method' => 'half_up',
            'buyer_source' => 'invoice',
            'lines' => [[
                'id' => $lineId,
                'description' => 'Network survey',
                'base_amount' => $invoice->decimalAttribute('total'),
                'vat_rate' => $rate,
                'adjustment_amount' => '0',
                'adjustment_reason' => '',
                'driver' => 'forward',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $mushak->fresh() ?? $mushak;
    }

    private function company(): void
    {
        $profile = CompanyProfile::query()->create([
            'legal_name' => 'Ahmad Solution Ltd.',
            'bin' => '123456789-0001',
            'financial_year_starts_on' => '2026-07-01',
            'financial_year_ends_on' => '2027-06-30',
            'currency_code' => 'BDT',
        ]);
        CompanyAddress::query()->create([
            'company_profile_id' => $profile->id,
            'address_type' => 'registered_office',
            'line_1' => 'House 1, Road 2',
            'district' => 'Dhaka',
            'country' => 'Bangladesh',
            'is_active' => true,
            'show_on_pdf' => true,
        ]);
    }

    private function party(string $name): Party
    {
        $party = Party::query()->create([
            'legal_name' => $name,
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $party->roles()->create(['role' => 'customer']);

        return $party;
    }

    private function onlyJournal(JournalSource $source, int $sourceId): Journal
    {
        $this->assertSame(1, $this->journalsFor($source, $sourceId));

        return Journal::query()->where('source', $source)->where('source_id', $sourceId)->firstOrFail();
    }

    private function journalsFor(JournalSource $source, int $sourceId): int
    {
        return Journal::query()->where('source', $source)->where('source_id', $sourceId)->count();
    }

    /**
     * @return list<array{0: string|null, 1: string, 2: string, 3: int|null}>
     */
    private function postingLines(Journal $journal): array
    {
        $lines = [];

        foreach ($journal->lines()->with('account')->orderBy('position')->orderBy('id')->get() as $line) {
            if (! $line instanceof JournalLine) {
                continue;
            }

            $key = $line->account?->system_key;
            $lines[] = [
                is_string($key) ? $key : null,
                $line->decimalAttribute('debit'),
                $line->decimalAttribute('credit'),
                $line->party_id === null ? null : (int) $line->party_id,
            ];
        }

        return $lines;
    }

    private function accountId(string $key): int
    {
        return (int) Account::query()->where('system_key', $key)->value('id');
    }
}
