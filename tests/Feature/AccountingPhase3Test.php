<?php

namespace Tests\Feature;

use App\Accounting\AccountType;
use App\Accounting\FinancialStatements;
use App\Accounting\JournalSource;
use App\Accounting\JournalWriter;
use App\Accounting\PayablesAging;
use App\Accounting\PaymentWriter;
use App\Accounting\ReceiptWriter;
use App\Models\Account;
use App\Models\FinancialYear;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountingPhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $sales;

    private User $accountsManager;

    private User $super;

    private Party $supplier;

    private Party $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Dhaka'));
        FinancialYear::query()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
        ]);
        $this->accountant = $this->userWithRole('accountant');
        $this->sales = $this->userWithRole('sales-executive');
        $this->accountsManager = $this->userWithRole('accounts-manager');
        $this->super = $this->userWithRole('super-administrator');
        $this->supplier = $this->party('Jamuna Supplies Ltd.', 'supplier');
        $this->customer = $this->party('Padma Engineering Ltd.', 'customer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_supplier_payment_posts_payable_and_leaves_an_advance(): void
    {
        $obligation = $this->obligation('2026-10-12', '100.00');
        $this->actingAs($this->sales)->get(route('payments.create'))->assertForbidden();
        $this->actingAs($this->accountsManager)->get(route('payments.create'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('payments.create'))->assertOk()->assertSee('Cash in Hand');

        $this->actingAs($this->accountant)->post(route('payments.store'), [
            'party_id' => $this->customer->id,
            'account_id' => $this->accountId('cash'),
            'payment_date' => '2026-11-21',
            'amount' => '10.00',
            'mode' => 'cash',
            'cash_flow_category' => 'operating',
        ])->assertSessionHasErrors('payment');

        $this->actingAs($this->accountant)->post(route('payments.store'), $this->paymentPayload('40.00', '25.00', $obligation->id, 'investing'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('PAYM-2026-0001', $payment->number);
        $this->assertSame('investing', $payment->cash_flow_category);
        $this->assertSame('recorded', $payment->status->value);
        $journal = Journal::query()->where('source', JournalSource::Payment)->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame([
            ['accounts_payable', '40.00', '0.00', $this->supplier->id],
            ['cash', '0.00', '40.00', null],
        ], $this->postingLines($journal));

        $this->actingAs($this->accountant)->post(route('payments.store'), $this->paymentPayload('80.00', '80.00', $obligation->id))
            ->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::query()->count());

        $this->actingAs($this->accountant)->post(route('journals.reverse', $obligation))
            ->assertSessionHasErrors('journal');
        $this->assertSame(0, Journal::query()->where('reverses_journal_id', $obligation->id)->count());

        $this->actingAs($this->accountant)->post(route('payments.void', $payment), [
            'void_reason' => 'Paid the wrong supplier',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $journal->id)->count());
        $this->actingAs($this->accountant)->post(route('payments.void', $payment), [
            'void_reason' => 'Again',
        ])->assertSessionHasErrors('payment');

        $this->actingAs($this->accountant)->post(route('journals.reverse', $obligation))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->accountant)->get(route('payments.show', $payment))->assertOk()->assertSee('Void');
    }

    public function test_payables_aging_matches_the_posted_payable(): void
    {
        $current = $this->obligation('2026-11-21', '100.00');
        $recent = $this->obligation('2026-11-05', '80.00');
        $middle = $this->obligation('2026-10-12', '70.00');
        $older = $this->obligation('2026-09-01', '60.00');
        $this->obligation('2026-11-22', '15.00');
        $equity = Account::query()->create([
            'code' => '3100',
            'name' => 'Owner equity',
            'type' => AccountType::Equity,
            'is_active' => true,
        ]);
        app(JournalWriter::class)->postOpening([
            'journal_date' => '2026-08-01',
            'memo' => 'Opening payable',
            'lines' => [
                ['account_id' => $this->accountId('accounts_payable'), 'debit' => '', 'credit' => '50.00', 'party_id' => $this->supplier->id, 'memo' => ''],
                ['account_id' => $equity->id, 'debit' => '50.00', 'credit' => '', 'party_id' => '', 'memo' => ''],
            ],
        ], $this->accountant);
        $this->actingAs($this->accountant)->post(route('payments.store'), $this->paymentPayload('30.00', '20.00', $middle->id))->assertRedirect();
        $this->actingAs($this->accountant)->post(route('payments.store'), [
            'party_id' => $this->supplier->id,
            'account_id' => $this->accountId('cash'),
            'payment_date' => '2026-11-22',
            'amount' => '5.00',
            'mode' => 'cash',
            'cash_flow_category' => 'operating',
        ])->assertRedirect();

        $report = app(PayablesAging::class)->build();
        $this->assertSame('2026-11-21', $report['as_of']);
        $this->assertCount(1, $report['parties']);
        $party = $report['parties'][0];
        $this->assertSame($this->supplier->id, $party['party_id']);
        $this->assertSame('10.00', $party['advance']);
        $this->assertSame('330.00', $party['total']);
        $this->assertSame($party['total'], $party['payable']);
        $this->assertSame('330.00', $report['total']);

        $byLabel = [];

        foreach ($party['lines'] as $line) {
            $byLabel[$line['label']] = $line;
        }

        $this->assertSame('current', $byLabel[$current->number]['bucket']);
        $this->assertSame('100.00', $byLabel[$current->number]['outstanding']);
        $this->assertSame('1_30', $byLabel[$recent->number]['bucket']);
        $this->assertSame('31_60', $byLabel[$middle->number]['bucket']);
        $this->assertSame('70.00', $byLabel[$middle->number]['invoiced']);
        $this->assertSame('20.00', $byLabel[$middle->number]['allocated']);
        $this->assertSame('50.00', $byLabel[$middle->number]['outstanding']);
        $this->assertSame('61_90', $byLabel[$older->number]['bucket']);
        $this->assertSame('90_plus', $byLabel['Opening balance']['bucket']);
        $this->assertArrayNotHasKey($this->futureNumber(), $byLabel);

        $this->actingAs($this->accountsManager)->get(route('reports.payables-aging'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('reports.payables-aging'))
            ->assertOk()
            ->assertSee('Opening balance')
            ->assertSee('31–60')
            ->assertSee('330.00');
    }

    public function test_statements_use_posted_activity_and_the_chosen_cash_flow_category(): void
    {
        $this->pastYear();
        $this->movement('2026-01-15', $this->accountId('cash'), $this->accountId('sales_revenue'), '100.00');
        $expense = $this->expenseAccount();
        $this->movement('2026-02-01', $expense->id, $this->accountId('cash'), '40.00');
        app(ReceiptWriter::class)->record([
            'party_id' => $this->customer->id,
            'account_id' => $this->accountId('cash'),
            'receipt_date' => '2026-11-21',
            'amount_received' => '100.00',
            'vds_deducted' => '5.00',
            'ait_deducted' => '0.00',
            'mode' => 'cash',
            'cash_flow_category' => 'financing',
        ], $this->accountant);
        app(PaymentWriter::class)->record([
            'party_id' => $this->supplier->id,
            'account_id' => $this->accountId('cash'),
            'payment_date' => '2026-11-21',
            'amount' => '30.00',
            'mode' => 'bank_transfer',
            'cash_flow_category' => 'investing',
        ], $this->accountant);
        $this->movement('2026-11-21', $this->accountId('cash'), $this->accountId('sales_revenue'), '500.00');

        $statements = app(FinancialStatements::class);
        $profit = $statements->profitAndLoss('2025-07-01', '2026-06-30');
        $this->assertSame('60.00', $profit['profit']);
        $cash = $statements->cashFlow('2026-11-01', '2026-11-21');
        $byKey = [];

        foreach ($cash['sections'] as $section) {
            $byKey[$section['key']] = $section;
        }

        $this->assertSame('0.00', $byKey['operating']['inflow']);
        $this->assertSame('100.00', $byKey['financing']['inflow']);
        $this->assertSame('30.00', $byKey['investing']['outflow']);
        $this->assertSame('70.00', $cash['net']);

        $this->actingAs($this->accountant)->get(route('reports.profit-and-loss', ['from' => '2025-07-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertSee('60.00');
        $this->actingAs($this->accountant)->get(route('reports.cash-flow', ['from' => '2026-11-01', 'to' => '2026-11-21']))
            ->assertOk()
            ->assertSee('Investing')
            ->assertSee('Financing');
        $this->actingAs($this->accountsManager)->get(route('reports.balance-sheet'))->assertForbidden();
    }

    public function test_closing_a_past_year_carries_the_result_and_only_a_super_administrator_can_reopen_it(): void
    {
        $past = $this->pastYear();
        $this->movement('2026-01-15', $this->accountId('cash'), $this->accountId('sales_revenue'), '100.00');
        $this->movement('2026-02-01', $this->expenseAccount()->id, $this->accountId('cash'), '40.00');
        $later = FinancialYear::query()->create([
            'starts_on' => '2027-07-01',
            'ends_on' => '2028-06-30',
        ]);
        $current = FinancialYear::query()->whereDate('starts_on', '2026-07-01')->firstOrFail();

        $this->actingAs($this->accountant)->post(route('financial-years.close', $current), [
            'close_reason' => 'The current year is still open',
        ])->assertSessionHasErrors(['year' => 'The financial year that contains today stays open.']);
        $this->actingAs($this->accountant)->post(route('financial-years.close', $later), [
            'close_reason' => 'There is no following year',
        ])->assertSessionHasErrors(['year' => 'Add the next financial year before closing this one.']);

        $this->actingAs($this->accountant)->post(route('financial-years.close', $past), [
            'close_reason' => 'Books agreed',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $closed = $past->fresh();
        $this->assertNotNull($closed);
        $this->assertTrue($closed->isClosed());
        $carry = Journal::query()->where('source', JournalSource::CarryForward)->firstOrFail();
        $this->assertSame('2026-07-01', $carry->journalDateString());
        $this->assertSame('financial_year', $carry->source_type);
        $this->assertSame($past->id, (int) $carry->source_id);
        $this->assertSame('60.00', $this->signed($carry, 'retained_earnings'));
        $this->assertSame('60.00', app(FinancialStatements::class)->profitAndLoss('2025-07-01', '2026-06-30')['profit']);
        $this->assertSame('0.00', app(FinancialStatements::class)->profitAndLoss('2026-07-01', '2026-11-21')['profit']);

        $before = app(FinancialStatements::class)->balanceSheet('2026-06-30');
        $this->assertSame('60.00', $before['earnings']);
        $this->assertSame('0.00', $this->balance($before, '3000'));
        $this->assertSame('0.00', $before['difference']);
        $after = app(FinancialStatements::class)->balanceSheet('2026-07-01');
        $this->assertSame('0.00', $after['earnings']);
        $this->assertSame('60.00', $this->balance($after, '3000'));
        $this->assertSame('0.00', $after['difference']);

        $this->actingAs($this->accountant)->post(route('journals.store'), [
            'journal_date' => '2026-03-01',
            'memo' => 'Late draft',
            'lines' => [
                ['account_id' => $this->accountId('cash'), 'debit' => '1.00', 'credit' => '', 'party_id' => '', 'memo' => ''],
                ['account_id' => $this->accountId('sales_revenue'), 'debit' => '', 'credit' => '1.00', 'party_id' => '', 'memo' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $draft = Journal::query()->where('status', 'draft')->firstOrFail();
        $this->actingAs($this->accountant)->post(route('journals.post', $draft))->assertSessionHasErrors('journal');
        $this->assertSame('draft', $draft->fresh()?->status->value);
        $this->actingAs($this->accountant)->post(route('payments.store'), $this->paymentPayload('1.00', '', null, 'operating', '2026-03-01'))
            ->assertSessionHasErrors('payment');

        $this->actingAs($this->accountant)->post(route('financial-years.reopen', $past), [
            'reopen_reason' => 'Need a correction',
        ])->assertForbidden();
        $this->actingAs($this->super)->post(route('financial-years.reopen', $past), [
            'reopen_reason' => 'Need a correction',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($past->fresh()?->isClosed() ?? true);
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $carry->id)->count());

        $this->actingAs($this->super)->post(route('financial-years.close', $past), [
            'close_reason' => 'Closed again',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $reclosed = app(FinancialStatements::class)->balanceSheet('2026-11-21');
        $this->assertSame('60.00', $this->balance($reclosed, '3000'));
        $this->assertSame('0.00', $reclosed['difference']);
        $this->actingAs($this->accountant)->get(route('financial-years.index'))->assertOk()->assertSee('Closed again');
    }

    private function pastYear(): FinancialYear
    {
        return FinancialYear::query()->create([
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
        ]);
    }

    private function obligation(string $date, string $amount): Journal
    {
        return $this->movement($date, $this->expenseAccount()->id, $this->accountId('accounts_payable'), $amount, $this->supplier->id);
    }

    private function movement(string $date, int $debitAccount, int $creditAccount, string $amount, ?int $partyId = null): Journal
    {
        $journal = app(JournalWriter::class)->createDraft([
            'journal_date' => $date,
            'memo' => 'Test movement',
            'lines' => [
                ['account_id' => $debitAccount, 'debit' => $amount, 'credit' => '', 'party_id' => '', 'memo' => ''],
                ['account_id' => $creditAccount, 'debit' => '', 'credit' => $amount, 'party_id' => $partyId ?? '', 'memo' => ''],
            ],
        ], $this->accountant);

        return app(JournalWriter::class)->post($journal, $this->accountant);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(string $amount, string $allocated, ?int $journalId, string $category = 'operating', string $date = '2026-11-21'): array
    {
        return [
            'party_id' => $this->supplier->id,
            'account_id' => $this->accountId('cash'),
            'payment_date' => $date,
            'amount' => $amount,
            'mode' => 'cash',
            'reference' => 'CHQ-1',
            'cash_flow_category' => $category,
            'allocations' => $journalId === null ? [] : [[
                'journal_id' => $journalId,
                'amount' => $allocated,
            ]],
        ];
    }

    private function expenseAccount(): Account
    {
        $existing = Account::query()->where('code', '5100')->first();

        if ($existing instanceof Account) {
            return $existing;
        }

        return Account::query()->create([
            'code' => '5100',
            'name' => 'Office expense',
            'type' => AccountType::Expense,
            'is_active' => true,
        ]);
    }

    private function party(string $name, string $role): Party
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

    private function accountId(string $key): int
    {
        return (int) Account::query()->where('system_key', $key)->value('id');
    }

    /**
     * @return list<array{0: string|null, 1: string, 2: string, 3: int|null}>
     */
    private function postingLines(Journal $journal): array
    {
        $lines = [];

        foreach ($journal->lines()->with('account')->orderBy('position')->orderBy('id')->get() as $line) {
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

    private function signed(Journal $journal, string $key): string
    {
        $accountId = $this->accountId($key);
        $amount = Money::zero();

        foreach ($journal->lines()->where('account_id', $accountId)->get() as $line) {
            $amount = $amount->plus(Money::of($line->decimalAttribute('credit')))->minus(Money::of($line->decimalAttribute('debit')));
        }

        return $amount->amount();
    }

    /**
     * @param  array{equity: list<array{code: string, name: string, amount: string}>}  $sheet
     */
    private function balance(array $sheet, string $code): string
    {
        foreach ($sheet['equity'] as $row) {
            if ($row['code'] === $code) {
                return $row['amount'];
            }
        }

        return 'missing';
    }

    private function futureNumber(): string
    {
        return (string) Journal::query()->whereDate('journal_date', '2026-11-22')->value('number');
    }
}
