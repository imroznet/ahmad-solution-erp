<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class AccountingHomeController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $actor = $user instanceof User ? $user : null;

        return view('accounting.home', [
            'canChart' => $actor?->hasPermission('chart_of_accounts.view') ?? false,
            'canJournals' => $actor?->hasPermission('journals.view') ?? false,
            'canOpening' => $actor?->hasPermission('opening_balances.manage') ?? false,
            'canReports' => $actor?->hasPermission('reports.view') ?? false,
        ]);
    }
}
