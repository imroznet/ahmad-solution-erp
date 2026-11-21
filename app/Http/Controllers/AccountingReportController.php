<?php

namespace App\Http\Controllers;

use App\Accounting\Ledger;
use App\Http\Requests\GeneralLedgerRequest;
use App\Models\Account;
use App\Models\Party;
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
}
