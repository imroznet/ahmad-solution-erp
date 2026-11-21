<?php

namespace Tests\Feature;

use App\Accounting\AccountingException;
use App\Accounting\AccountingInstaller;
use App\Accounting\JournalBalance;
use App\Accounting\JournalWriter;
use App\Accounting\Ledger;
use App\Authorization\PermissionCatalog;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\FinancialYear;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\MfsAccount;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('administrator');
        $today = now()->timezone('Asia/Dhaka');
        CompanyProfile::query()->create([
            'legal_name' => 'Ahmad Solution Ltd.',
            'financial_year_starts_on' => $today->copy()->startOfYear()->toDateString(),
            'financial_year_ends_on' => $today->copy()->endOfYear()->toDateString(),
            'currency_code' => 'BDT',
        ]);
    }

    public function test_the_chart_seeds_cash_and_no_bank_or_mfs_account(): void
    {
        $this->assertSame(7, Account::query()->count());
        $this->assertSame(0, BankAccount::query()->count());
        $this->assertSame(0, MfsAccount::query()->count());
        $this->assertNotNull(Account::query()->where('system_key', 'cash')->where('name', 'Cash in Hand')->first());
        $this->assertSame(0, Account::query()->where('name', 'like', '%Bank%')->count());
        $this->assertSame(
            ['cash'],
            Account::query()->forSettlement()->pluck('system_key')->all(),
        );

        $ids = array_column(PermissionCatalog::permissions(), 'id');
        $this->assertNotContains(82, $ids);
        $this->assertNotContains(83, $ids);
        $this->assertNotContains(86, $ids);
        $this->assertNotContains('receipts.view', array_column(PermissionCatalog::permissions(), 'slug'));
    }

    public function test_an_unbalanced_journal_cannot_post_and_a_posted_journal_cannot_change(): void
    {
        $cash = $this->account('cash');
        $sales = $this->account('sales_revenue');
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '9999.99', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '1.00'],
        ]))->assertRedirect();
        $draft = Journal::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('journals.post', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('journal');
        $this->assertSame('draft', $draft->fresh()?->status->value);

        $this->actingAs($this->admin)->put(route('journals.update', $draft), $this->payload([
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '100.00'],
        ]))->assertRedirect();
        $this->actingAs($this->admin)->post(route('journals.post', $draft))->assertRedirect();
        $posted = $draft->fresh();
        $this->assertNotNull($posted);
        $this->assertSame('posted', $posted->status->value);

        $this->actingAs($this->admin)->put(route('journals.update', $posted), $this->payload([
            ['account_id' => $cash->id, 'debit' => '5.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '5.00'],
        ]))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('journals.destroy', $posted))->assertForbidden();
        $this->assertSame('100.00', Money::of($posted->fresh()->lines()->where('account_id', $cash->id)->firstOrFail()->decimalAttribute('debit'))->amount());

        $this->expectException(AccountingException::class);
        app(JournalWriter::class)->updateDraft($posted->fresh(), [
            'journal_date' => $this->today(),
            'memo' => 'Changed',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '5.00', 'credit' => '0'],
                ['account_id' => $sales->id, 'debit' => '0', 'credit' => '5.00'],
            ],
        ], $this->admin);
    }

    public function test_a_reversal_negates_the_original_and_a_second_reversal_is_refused(): void
    {
        $journal = $this->postedJournal();
        $this->actingAs($this->admin)->post(route('journals.reverse', $journal))->assertRedirect();
        $reversal = Journal::query()->where('reverses_journal_id', $journal->id)->firstOrFail();
        $this->assertSame('posted', $reversal->status->value);
        $this->assertSame('reversal', $reversal->source->value);

        $originalLines = $journal->lines()->orderBy('position')->get();
        $reversalLines = $reversal->lines()->orderBy('position')->get();
        $this->assertCount($originalLines->count(), $reversalLines);
        $debits = [];
        $credits = [];

        foreach ($originalLines as $index => $line) {
            $reversed = $reversalLines[$index];
            $this->assertInstanceOf(JournalLine::class, $line);
            $this->assertInstanceOf(JournalLine::class, $reversed);
            $this->assertSame($line->account_id, $reversed->account_id);
            $this->assertSame(
                Money::of($line->decimalAttribute('debit'))->amount(),
                Money::of($reversed->decimalAttribute('credit'))->amount(),
            );
            $this->assertSame(
                Money::of($line->decimalAttribute('credit'))->amount(),
                Money::of($reversed->decimalAttribute('debit'))->amount(),
            );
            $debits[] = Money::of($reversed->decimalAttribute('debit'))->amount();
            $credits[] = Money::of($reversed->decimalAttribute('credit'))->amount();
        }

        $this->assertTrue(JournalBalance::isBalanced($debits, $credits));
        $this->actingAs($this->admin)
            ->post(route('journals.reverse', $journal))
            ->assertRedirect()
            ->assertSessionHasErrors('journal');
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $journal->id)->count());
    }

    public function test_a_reversal_posts_against_an_account_deactivated_after_the_original(): void
    {
        $this->actingAs($this->admin)->post(route('accounts.store'), [
            'code' => '5100',
            'name' => 'Office supplies',
            'type' => 'expense',
            'parent_id' => '',
        ])->assertRedirect();
        $expense = Account::query()->where('code', '5100')->firstOrFail();
        $cash = $this->account('cash');
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $expense->id, 'debit' => '25.00', 'credit' => ''],
            ['account_id' => $cash->id, 'debit' => '', 'credit' => '25.00'],
        ]))->assertRedirect();
        $journal = Journal::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('journals.post', $journal))->assertRedirect();
        $this->actingAs($this->admin)->put(route('accounts.update', $expense), [
            'name' => 'Office supplies',
            'parent_id' => '',
            'is_active' => '0',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $expense->id, 'debit' => '1.00', 'credit' => ''],
            ['account_id' => $cash->id, 'debit' => '', 'credit' => '1.00'],
        ]))->assertRedirect()->assertSessionHasErrors('journal');
        $this->actingAs($this->admin)->post(route('journals.reverse', $journal))->assertRedirect();
        $this->assertSame(1, Journal::query()->where('reverses_journal_id', $journal->id)->count());
    }

    public function test_a_failed_create_does_not_consume_a_journal_number(): void
    {
        $year = now()->timezone('Asia/Dhaka')->format('Y');
        $writer = app(JournalWriter::class);
        $input = [
            'journal_date' => $this->today(),
            'memo' => 'Rolled back',
            'lines' => $this->balancedLines(),
        ];

        DB::beginTransaction();
        $first = $writer->createDraft($input, $this->admin);
        DB::rollBack();

        try {
            DB::transaction(function () use ($writer, $input): void {
                $writer->createDraft($input, $this->admin);
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
        }

        $second = $writer->createDraft($input, $this->admin);

        $this->assertSame('JE-'.$year.'-0001', $first->number);
        $this->assertSame('JE-'.$year.'-0001', $second->number);
        $this->assertSame(1, Journal::query()->count());
        $this->assertSame(1, (int) DB::table('journal_number_sequences')->where('year', $year)->value('last_number'));
    }

    public function test_an_account_with_a_posting_cannot_be_deleted_and_a_system_account_cannot_be_renamed(): void
    {
        $cash = $this->account('cash');
        $this->actingAs($this->admin)->put(route('accounts.update', $cash), [
            'name' => 'Petty cash',
            'parent_id' => '',
            'is_active' => '1',
        ])->assertForbidden();
        $this->assertSame('Cash in Hand', $cash->fresh()?->name);

        $this->actingAs($this->admin)->post(route('accounts.store'), [
            'code' => '5100',
            'name' => 'Office supplies',
            'type' => 'expense',
            'parent_id' => '',
        ])->assertRedirect();
        $expense = Account::query()->where('code', '5100')->firstOrFail();
        $sales = $this->account('sales_revenue');
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $expense->id, 'debit' => '25.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '25.00'],
        ]))->assertRedirect();

        $this->actingAs($this->admin)
            ->delete(route('accounts.destroy', $expense))
            ->assertRedirect()
            ->assertSessionHasErrors('account');
        $this->assertModelExists($expense);

        $this->actingAs($this->admin)->put(route('accounts.update', $expense), [
            'name' => 'Office supplies',
            'parent_id' => '',
            'is_active' => '0',
        ])->assertRedirect();
        $this->actingAs($this->admin)->get(route('journals.create'))->assertDontSee('Office supplies');
    }

    public function test_the_trial_balance_uses_posted_journals_and_balances(): void
    {
        $cash = $this->account('cash');
        $sales = $this->account('sales_revenue');
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '9999.99', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '1.00'],
        ]))->assertRedirect();
        $this->postedJournal();

        $report = app(Ledger::class)->trialBalance();
        $this->assertSame('0.00', $report['difference']);
        $this->assertSame($report['debit_total'], $report['credit_total']);
        $this->assertSame('100.00', collect($report['rows'])->firstWhere('code', '1000')['debit']);

        $this->actingAs($this->admin)
            ->get(route('reports.trial-balance'))
            ->assertOk()
            ->assertSee('Cash in Hand')
            ->assertSee('100.00')
            ->assertSee('0.00')
            ->assertDontSee('9,999.99');
    }

    public function test_the_trial_balance_shows_each_account_at_its_net_balance(): void
    {
        $cash = $this->account('cash');
        $sales = $this->account('sales_revenue');
        $this->postedJournal();
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $sales->id, 'debit' => '40.00', 'credit' => ''],
            ['account_id' => $cash->id, 'debit' => '', 'credit' => '40.00'],
        ]))->assertRedirect();
        $refund = Journal::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('journals.post', $refund))->assertRedirect();

        $report = app(Ledger::class)->trialBalance();
        $cashRow = collect($report['rows'])->firstWhere('code', $cash->code);
        $salesRow = collect($report['rows'])->firstWhere('code', $sales->code);
        $this->assertIsArray($cashRow);
        $this->assertIsArray($salesRow);
        $this->assertSame('60.00', $cashRow['debit']);
        $this->assertSame('0.00', $cashRow['credit']);
        $this->assertSame('0.00', $salesRow['debit']);
        $this->assertSame('60.00', $salesRow['credit']);
        $this->assertSame('0.00', $report['difference']);
        $this->assertSame($report['debit_total'], $report['credit_total']);
    }

    public function test_the_general_ledger_runs_in_date_order_and_can_filter_by_party(): void
    {
        $cash = $this->account('cash');
        $sales = $this->account('sales_revenue');
        $party = Party::query()->create([
            'legal_name' => 'Padma Engineering Ltd.',
            'party_kind' => 'company',
            'status' => 'active',
            'currency_code' => 'BDT',
        ]);
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '100.00'],
        ]))->assertRedirect();
        $first = Journal::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('journals.post', $first))->assertRedirect();
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '25.00', 'credit' => '', 'party_id' => $party->id],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '25.00'],
        ]))->assertRedirect();
        $second = Journal::query()->whereKeyNot($first->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('journals.post', $second))->assertRedirect();

        $rows = app(Ledger::class)->generalLedger($cash, null, null, null);
        $this->assertSame(['100.00', '125.00'], array_column($rows, 'balance'));
        $filtered = app(Ledger::class)->generalLedger($cash, $this->today(), $this->today(), $party->id);
        $this->assertCount(1, $filtered);
        $this->assertSame('25.00', $filtered[0]['balance']);

        $this->actingAs($this->admin)
            ->get(route('reports.general-ledger', ['account' => $cash->id, 'party' => $party->id]))
            ->assertOk()
            ->assertSee('Padma Engineering Ltd.')
            ->assertSee('25.00');
    }

    public function test_opening_balances_are_once_per_account_and_a_date_outside_every_year_is_refused(): void
    {
        $cash = $this->account('cash');
        $sales = $this->account('sales_revenue');
        $this->actingAs($this->admin)->get(route('opening-balances.edit'))->assertOk()->assertSee('Opening date');
        $this->actingAs($this->admin)->post(route('opening-balances.store'), [
            'journal_date' => $this->today(),
            'memo' => 'Opening balances',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '500.00', 'credit' => ''],
                ['account_id' => $sales->id, 'debit' => '', 'credit' => '500.00'],
            ],
        ])->assertRedirect();
        $this->assertSame(1, Journal::query()->where('source', 'opening')->count());
        $this->assertSame(2, DB::table('account_openings')->count());
        $opening = Journal::query()->where('source', 'opening')->firstOrFail();

        try {
            DB::table('account_openings')->insert([
                'account_id' => $cash->id,
                'journal_id' => $opening->id,
            ]);
            $this->fail('A second opening row for the same account was stored.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(2, DB::table('account_openings')->count());
        }

        $this->actingAs($this->admin)->post(route('opening-balances.store'), [
            'journal_date' => $this->today(),
            'memo' => 'Again',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '1.00', 'credit' => ''],
                ['account_id' => $sales->id, 'debit' => '', 'credit' => '1.00'],
            ],
        ])->assertRedirect()->assertSessionHasErrors('opening');
        $this->assertSame(1, Journal::query()->where('source', 'opening')->count());

        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '10.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '10.00'],
        ], '1999-01-01'))->assertRedirect()->assertSessionHasErrors('journal');

        $this->actingAs($this->admin)->post(route('financial-years.store'), [
            'starts_on' => '1999-01-01',
            'ends_on' => '1999-12-31',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '10.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '10.00'],
        ], '1999-06-15'))->assertRedirect()->assertSessionHasNoErrors();
        $oldYear = Journal::query()->whereDate('journal_date', '1999-06-15')->firstOrFail();
        $this->assertSame('1999-01-01', $oldYear->financial_year_starts_on?->toDateString());
        $this->assertSame('1999-12-31', $oldYear->financial_year_ends_on?->toDateString());
    }

    public function test_editing_a_used_financial_year_keeps_its_journals_covered(): void
    {
        $cash = $this->account('cash');
        $sales = $this->account('sales_revenue');
        $this->actingAs($this->admin)->post(route('financial-years.store'), [
            'starts_on' => '1999-01-01',
            'ends_on' => '1999-12-31',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $cash->id, 'debit' => '10.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '10.00'],
        ], '1999-06-15'))->assertRedirect()->assertSessionHasNoErrors();
        $year = FinancialYear::query()->whereDate('starts_on', '1999-01-01')->firstOrFail();

        $this->actingAs($this->admin)->put(route('financial-years.update', $year), [
            'starts_on' => '2000-01-01',
            'ends_on' => '2000-12-31',
        ])->assertRedirect()->assertSessionHasErrors('year');
        $year->refresh();
        $this->assertSame('1999-01-01', $year->startsOn());
        $journal = Journal::query()->whereDate('journal_date', '1999-06-15')->firstOrFail();
        $this->assertSame('1999-12-31', $journal->financial_year_ends_on?->toDateString());

        $this->actingAs($this->admin)->put(route('financial-years.update', $year), [
            'starts_on' => '1999-01-01',
            'ends_on' => '1999-06-30',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $journal->refresh();
        $this->assertSame('1999-06-30', $journal->financial_year_ends_on?->toDateString());
        $this->actingAs($this->admin)->post(route('journals.post', $journal))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('posted', $journal->fresh()?->status->value);
    }

    public function test_a_bank_opening_balance_cannot_predate_the_account(): void
    {
        $this->actingAs($this->admin)->post(route('accounts.store'), [
            'code' => '3100',
            'name' => 'Owner funds',
            'type' => 'equity',
        ])->assertRedirect();
        $equity = Account::query()->where('code', '3100')->firstOrFail();
        $opened = now()->timezone('Asia/Dhaka')->startOfYear()->month(11)->day(2)->toDateString();
        $tooEarly = now()->timezone('Asia/Dhaka')->startOfYear()->month(11)->day(1)->toDateString();
        $this->actingAs($this->admin)->post(route('bank-accounts.store'), [
            'code' => '1500',
            'bank_name' => 'Test Bank',
            'branch' => 'Test Branch',
            'account_title' => 'Test settlement',
            'account_number' => '0000000000',
            'account_type' => 'current',
            'opened_on' => $opened,
        ])->assertRedirect();
        $bank = BankAccount::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('opening-balances.store'), [
            'journal_date' => $tooEarly,
            'memo' => 'Too early',
            'lines' => [
                ['account_id' => $bank->account_id, 'debit' => '100.00', 'credit' => ''],
                ['account_id' => $equity->id, 'debit' => '', 'credit' => '100.00'],
            ],
        ])->assertRedirect()->assertSessionHasErrors('opening');
        $this->assertSame(0, Journal::query()->where('source', 'opening')->count());

        $this->actingAs($this->admin)->post(route('opening-balances.store'), [
            'journal_date' => $opened,
            'memo' => 'Bank opening',
            'lines' => [
                ['account_id' => $bank->account_id, 'debit' => '100.00', 'credit' => ''],
                ['account_id' => $equity->id, 'debit' => '', 'credit' => '100.00'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Journal::query()->where('source', 'opening')->count());

        $this->actingAs($this->admin)->post(route('opening-balances.store'), [
            'journal_date' => $opened,
            'memo' => 'Again',
            'lines' => [
                ['account_id' => $bank->account_id, 'debit' => '1.00', 'credit' => ''],
                ['account_id' => $equity->id, 'debit' => '', 'credit' => '1.00'],
            ],
        ])->assertRedirect()->assertSessionHasErrors('opening');
    }

    public function test_bank_and_mfs_accounts_are_assets_and_leave_the_settlement_list_when_deactivated(): void
    {
        $this->actingAs($this->admin)->post(route('bank-accounts.store'), [
            'code' => '5100',
            'bank_name' => 'Test Bank',
            'branch' => 'Test Branch',
            'account_title' => 'Test settlement',
            'account_number' => '0000000000',
            'account_type' => 'current',
            'opened_on' => $this->today(),
        ])->assertSessionHasErrors('code');
        $this->assertSame(0, BankAccount::query()->count());

        $this->actingAs($this->admin)->post(route('bank-accounts.store'), [
            'code' => '1500',
            'bank_name' => 'Test Bank',
            'branch' => 'Test Branch',
            'account_title' => 'Test settlement',
            'account_number' => '0000000000',
            'account_type' => 'current',
            'opened_on' => $this->today(),
        ])->assertRedirect();
        $bank = BankAccount::query()->with('account')->firstOrFail();
        $this->assertSame('asset', $bank->account?->type->value);
        $this->assertSame('1500', $bank->account?->code);
        $this->assertSame('Test settlement', $bank->account?->name);
        $this->assertTrue(Account::query()->forSettlement()->where('code', '1500')->exists());

        $this->actingAs($this->admin)->post(route('mfs-accounts.store'), [
            'code' => '1510',
            'provider' => 'bkash',
            'mobile_number' => '01000000000',
            'account_title' => 'Test wallet',
            'opened_on' => $this->today(),
        ])->assertRedirect();
        $wallet = MfsAccount::query()->with('account')->firstOrFail();
        $this->assertSame('asset', $wallet->account?->type->value);
        $this->assertSame('bkash', $wallet->provider->value);

        $this->actingAs($this->admin)->put(route('bank-accounts.update', $bank), [
            'bank_name' => 'Test Bank',
            'branch' => 'Test Branch',
            'account_title' => 'Test settlement renamed',
            'account_number' => '0000000000',
            'account_type' => 'savings',
            'opened_on' => $this->today(),
            'parent_id' => '',
            'is_active' => '0',
        ])->assertRedirect();
        $this->assertSame('Test settlement renamed', $bank->account?->fresh()?->name);
        $this->assertFalse(Account::query()->forSettlement()->where('code', '1500')->exists());
        $this->assertTrue(Account::query()->forSettlement()->where('system_key', 'cash')->exists());
        $this->actingAs($this->admin)->get(route('journals.create'))->assertDontSee('Test settlement renamed');

        $sales = $this->account('sales_revenue');
        $this->actingAs($this->admin)->put(route('bank-accounts.update', $bank), [
            'bank_name' => 'Test Bank',
            'branch' => 'Test Branch',
            'account_title' => 'Test settlement renamed',
            'account_number' => '0000000000',
            'account_type' => 'savings',
            'opened_on' => $this->today(),
            'is_active' => '1',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $bank->account_id, 'debit' => '40.00', 'credit' => ''],
            ['account_id' => $sales->id, 'debit' => '', 'credit' => '40.00'],
        ]))->assertRedirect();
        $this->actingAs($this->admin)
            ->delete(route('bank-accounts.destroy', $bank))
            ->assertRedirect()
            ->assertSessionHasErrors('bank');
        $this->assertModelExists($bank);
    }

    public function test_the_installer_grants_accounting_permissions_to_the_administrator_and_the_accountant(): void
    {
        $this->assertSame(1, Role::query()->where('slug', 'accountant')->count());
        $administrator = Role::query()->where('slug', 'administrator')->firstOrFail();
        $accountant = Role::query()->where('slug', 'accountant')->firstOrFail();
        $keep = Permission::query()->where('slug', 'dashboard.view')->firstOrFail();
        $administrator->permissions()->sync([$keep->id]);
        $accountant->permissions()->sync([$keep->id]);
        app(AccountingInstaller::class)->install();

        $slugs = $administrator->fresh()->permissions()->pluck('slug')->all();
        $this->assertContains('dashboard.view', $slugs);
        $this->assertContains('journals.post', $slugs);
        $this->assertContains('chart_of_accounts.manage', $slugs);
        $this->assertTrue($accountant->fresh()->permissions()->where('slug', 'journals.post')->exists());
        $this->assertTrue($accountant->fresh()->permissions()->where('slug', 'opening_balances.manage')->exists());

        $manager = Role::query()->where('slug', 'accounts-manager')->firstOrFail();
        $this->assertFalse($manager->permissions()->where('slug', 'journals.post')->exists());
        $this->actingAs($this->userWithRole('accounts-manager'))
            ->get(route('journals.index'))
            ->assertForbidden();
        $this->actingAs($this->userWithRole('accountant'))
            ->get(route('journals.index'))
            ->assertOk();
        $this->actingAs($this->admin)->get(route('accounting.home'))->assertOk()->assertSee('Bank and MFS accounts');
    }

    private function postedJournal(): Journal
    {
        $this->actingAs($this->admin)->post(route('journals.store'), $this->payload([
            ['account_id' => $this->account('cash')->id, 'debit' => '100.00', 'credit' => ''],
            ['account_id' => $this->account('sales_revenue')->id, 'debit' => '', 'credit' => '100.00'],
        ]))->assertRedirect();
        $journal = Journal::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('journals.post', $journal))->assertRedirect();

        return $journal->fresh() ?? $journal;
    }

    /**
     * @param  list<array{account_id: int, debit: string, credit: string, party_id?: int}>  $lines
     * @return array<string, mixed>
     */
    private function payload(array $lines, ?string $date = null): array
    {
        return [
            'journal_date' => $date ?? $this->today(),
            'memo' => 'Test journal',
            'lines' => array_map(fn (array $line): array => [
                'account_id' => $line['account_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'party_id' => $line['party_id'] ?? '',
                'memo' => '',
            ], $lines),
        ];
    }

    /**
     * @return list<array{account_id: int, debit: string, credit: string, party_id: string, memo: string}>
     */
    private function balancedLines(): array
    {
        return [
            ['account_id' => $this->account('cash')->id, 'debit' => '100.00', 'credit' => '0', 'party_id' => '', 'memo' => ''],
            ['account_id' => $this->account('sales_revenue')->id, 'debit' => '0', 'credit' => '100.00', 'party_id' => '', 'memo' => ''],
        ];
    }

    private function account(string $key): Account
    {
        return Account::query()->where('system_key', $key)->firstOrFail();
    }

    private function today(): string
    {
        return now()->timezone('Asia/Dhaka')->toDateString();
    }
}
