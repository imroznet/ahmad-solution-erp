<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\Decimal;
use App\Support\Money;

/**
 * Profit and loss, balance sheet, and direct cash flow from posted activity.
 * Cash flow uses receipts and payments that are not void. A manual journal that moves cash is left out.
 * Year-close journals, and reversals of those journals, are left out of profit and loss.
 */
final class FinancialStatements
{
    public function __construct(private PostedBalances $balances) {}

    /**
     * @return array{from: string, to: string, income: list<array{code: string, name: string, amount: string}>, expenses: list<array{code: string, name: string, amount: string}>, income_total: string, expense_total: string, profit: string}
     */
    public function profitAndLoss(mixed $from, mixed $to): array
    {
        [$start, $end] = $this->range($from, $to);
        $sums = $this->balances->sums($start, $end, [AccountType::Income->value, AccountType::Expense->value], true);
        $income = [];
        $expenses = [];
        $incomeTotal = Money::zero();
        $expenseTotal = Money::zero();

        foreach (Account::query()->orderBy('code')->get() as $account) {
            $pair = $sums[$account->id] ?? null;

            if ($pair === null) {
                continue;
            }

            if ($account->type === AccountType::Income) {
                $amount = $pair[1]->minus($pair[0]);

                if ($this->isZero($amount)) {
                    continue;
                }

                $income[] = ['code' => $account->code, 'name' => $account->name, 'amount' => $amount->amount()];
                $incomeTotal = $incomeTotal->plus($amount);
            }

            if ($account->type === AccountType::Expense) {
                $amount = $pair[0]->minus($pair[1]);

                if ($this->isZero($amount)) {
                    continue;
                }

                $expenses[] = ['code' => $account->code, 'name' => $account->name, 'amount' => $amount->amount()];
                $expenseTotal = $expenseTotal->plus($amount);
            }
        }

        return [
            'from' => $start,
            'to' => $end,
            'income' => $income,
            'expenses' => $expenses,
            'income_total' => $incomeTotal->amount(),
            'expense_total' => $expenseTotal->amount(),
            'profit' => $incomeTotal->minus($expenseTotal)->amount(),
        ];
    }

    /**
     * @return array{as_of: string, assets: list<array{code: string, name: string, amount: string}>, liabilities: list<array{code: string, name: string, amount: string}>, equity: list<array{code: string, name: string, amount: string}>, asset_total: string, liability_total: string, equity_total: string, earnings: string, equity_and_earnings: string, difference: string}
     */
    public function balanceSheet(mixed $asOf): array
    {
        $day = $this->day($asOf, $this->today());
        $sums = $this->balances->sums(null, $day);
        $assets = [];
        $liabilities = [];
        $equity = [];
        $assetTotal = Money::zero();
        $liabilityTotal = Money::zero();
        $equityTotal = Money::zero();
        $earnings = Money::zero();

        foreach (Account::query()->orderBy('code')->get() as $account) {
            $pair = $sums[$account->id] ?? [Money::zero(), Money::zero()];
            $debit = $pair[0];
            $credit = $pair[1];

            if ($account->type === AccountType::Asset) {
                $amount = $debit->minus($credit);
                $assets[] = $this->row($account, $amount);
                $assetTotal = $assetTotal->plus($amount);
            }

            if ($account->type === AccountType::Liability) {
                $amount = $credit->minus($debit);
                $liabilities[] = $this->row($account, $amount);
                $liabilityTotal = $liabilityTotal->plus($amount);
            }

            if ($account->type === AccountType::Equity) {
                $amount = $credit->minus($debit);
                $equity[] = $this->row($account, $amount);
                $equityTotal = $equityTotal->plus($amount);
            }

            if ($account->type === AccountType::Income) {
                $earnings = $earnings->plus($credit->minus($debit));
            }

            if ($account->type === AccountType::Expense) {
                $earnings = $earnings->minus($debit->minus($credit));
            }
        }

        $equityAndEarnings = $equityTotal->plus($earnings);

        return [
            'as_of' => $day,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'asset_total' => $assetTotal->amount(),
            'liability_total' => $liabilityTotal->amount(),
            'equity_total' => $equityTotal->amount(),
            'earnings' => $earnings->amount(),
            'equity_and_earnings' => $equityAndEarnings->amount(),
            'difference' => $assetTotal->minus($liabilityTotal)->minus($equityAndEarnings)->amount(),
        ];
    }

    /**
     * @return array{from: string, to: string, sections: list<array{key: string, label: string, inflow: string, outflow: string, net: string}>, inflow: string, outflow: string, net: string}
     */
    public function cashFlow(mixed $from, mixed $to): array
    {
        [$start, $end] = $this->range($from, $to);
        /** @var array<string, array{inflow: Money, outflow: Money}> $buckets */
        $buckets = [];

        foreach (CashFlowCategory::ordered() as $category) {
            $buckets[$category->value] = ['inflow' => Money::zero(), 'outflow' => Money::zero()];
        }

        foreach (Receipt::query()
            ->where('status', ReceiptStatus::Recorded->value)
            ->whereDate('receipt_date', '>=', $start)
            ->whereDate('receipt_date', '<=', $end)
            ->orderBy('id')
            ->get() as $receipt) {
            $key = $this->categoryKey($receipt->cash_flow_category);
            $buckets[$key]['inflow'] = $buckets[$key]['inflow']->plus(Money::of($receipt->decimalAttribute('amount_received')));
        }

        foreach (Payment::query()
            ->where('status', PaymentStatus::Recorded->value)
            ->whereDate('payment_date', '>=', $start)
            ->whereDate('payment_date', '<=', $end)
            ->orderBy('id')
            ->get() as $payment) {
            $key = $this->categoryKey($payment->cash_flow_category);
            $buckets[$key]['outflow'] = $buckets[$key]['outflow']->plus(Money::of($payment->decimalAttribute('amount')));
        }

        $sections = [];
        $inflow = Money::zero();
        $outflow = Money::zero();

        foreach (CashFlowCategory::ordered() as $category) {
            $row = $buckets[$category->value];
            $sections[] = [
                'key' => $category->value,
                'label' => $category->label(),
                'inflow' => $row['inflow']->amount(),
                'outflow' => $row['outflow']->amount(),
                'net' => $row['inflow']->minus($row['outflow'])->amount(),
            ];
            $inflow = $inflow->plus($row['inflow']);
            $outflow = $outflow->plus($row['outflow']);
        }

        return [
            'from' => $start,
            'to' => $end,
            'sections' => $sections,
            'inflow' => $inflow->amount(),
            'outflow' => $outflow->amount(),
            'net' => $inflow->minus($outflow)->amount(),
        ];
    }

    /**
     * @return array{code: string, name: string, amount: string}
     */
    private function row(Account $account, Money $amount): array
    {
        return [
            'code' => $account->code,
            'name' => $account->name,
            'amount' => $amount->amount(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(mixed $from, mixed $to): array
    {
        $today = $this->today();
        $start = $today;

        try {
            $start = app(FinancialYearCalendar::class)->boundsFor($today)['starts'];
        } catch (AccountingException) {
            $start = now()->timezone('Asia/Dhaka')->startOfYear()->toDateString();
        }

        $fromDay = $this->day($from, $start);
        $toDay = $this->day($to, $today);

        if ($fromDay > $toDay) {
            return [$toDay, $fromDay];
        }

        return [$fromDay, $toDay];
    }

    private function day(mixed $value, string $fallback): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }

    private function today(): string
    {
        return now()->timezone('Asia/Dhaka')->toDateString();
    }

    private function categoryKey(mixed $value): string
    {
        $category = is_string($value) ? CashFlowCategory::tryFrom($value) : null;

        return $category instanceof CashFlowCategory ? $category->value : CashFlowCategory::Operating->value;
    }

    private function isZero(Money $money): bool
    {
        return Decimal::of($money->amount())->isZero();
    }
}
