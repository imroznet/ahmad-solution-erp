<?php

namespace App\Http\Controllers;

use App\Accounting\FinancialStatements;
use App\Accounting\Ledger;
use App\Accounting\PayablesAging;
use App\Accounting\ReceivablesAging;
use App\Http\Requests\GeneralLedgerRequest;
use App\Models\Account;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingReportController extends Controller
{
    public function trialBalance(Ledger $ledger): View
    {
        return view('accounting.reports.trial-balance', [
            'report' => $ledger->trialBalance(),
        ]);
    }

    public function generalLedger(GeneralLedgerRequest $request, Ledger $ledger): View
    {
        $accountId = $request->integer('account');
        $account = $accountId > 0 ? Account::query()->find($accountId) : null;
        $partyId = $request->integer('party');
        $rows = $account instanceof Account
            ? $ledger->generalLedger(
                $account,
                $request->input('from'),
                $request->input('to'),
                $partyId > 0 ? $partyId : null,
            )
            : [];

        return view('accounting.reports.general-ledger', [
            'accounts' => Account::query()->orderBy('code')->get(),
            'parties' => Party::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'account' => $account instanceof Account ? $account : null,
            'rows' => $rows,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'party' => $partyId > 0 ? $partyId : null,
        ]);
    }

    public function receivablesAging(ReceivablesAging $aging): View
    {
        return view('accounting.reports.receivables-aging', [
            'report' => $aging->build(),
        ]);
    }

    public function payablesAging(PayablesAging $aging): View
    {
        return view('accounting.reports.payables-aging', [
            'report' => $aging->build(),
        ]);
    }

    public function profitAndLoss(Request $request, FinancialStatements $statements): View
    {
        return view('accounting.reports.profit-and-loss', [
            'report' => $statements->profitAndLoss($request->input('from'), $request->input('to')),
        ]);
    }

    public function balanceSheet(Request $request, FinancialStatements $statements): View
    {
        return view('accounting.reports.balance-sheet', [
            'report' => $statements->balanceSheet($request->input('as_of')),
        ]);
    }

    public function cashFlow(Request $request, FinancialStatements $statements): View
    {
        return view('accounting.reports.cash-flow', [
            'report' => $statements->cashFlow($request->input('from'), $request->input('to')),
        ]);
    }
}
