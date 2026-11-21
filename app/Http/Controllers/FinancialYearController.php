<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\FinancialYearCalendar;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SaveFinancialYearRequest;
use App\Models\FinancialYear;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinancialYearController extends Controller
{
    use RedirectsAccountingErrors;

    public function index(FinancialYearCalendar $calendar): View
    {
        $actor = $this->actor(request()->user());

        return view('accounting.financial-years.index', [
            'years' => FinancialYear::query()->orderBy('starts_on')->get(),
            'profileYear' => $calendar->profileBounds(),
            'canManage' => $actor->hasPermission('chart_of_accounts.manage'),
        ]);
    }

    public function store(SaveFinancialYearRequest $request, FinancialYearCalendar $calendar): RedirectResponse
    {
        try {
            $calendar->create(
                $request->string('starts_on')->toString(),
                $request->string('ends_on')->toString(),
            );
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'year');
        }

        return redirect()->route('financial-years.index')->with('status', 'Financial year added.');
    }

    public function update(SaveFinancialYearRequest $request, FinancialYear $financialYear, FinancialYearCalendar $calendar): RedirectResponse
    {
        try {
            $calendar->update(
                $financialYear,
                $request->string('starts_on')->toString(),
                $request->string('ends_on')->toString(),
            );
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'year');
        }

        return redirect()->route('financial-years.index')->with('status', 'Financial year updated.');
    }

    public function destroy(FinancialYear $financialYear): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('chart_of_accounts.manage'), 403);

        $used = Journal::query()
            ->whereDate('financial_year_starts_on', $financialYear->startsOn())
            ->whereDate('financial_year_ends_on', $financialYear->endsOn())
            ->exists();

        if ($used) {
            return back()->withErrors([
                'year' => 'A journal uses this financial year. Edit the dates instead of deleting it.',
            ]);
        }

        $financialYear->delete();

        return redirect()->route('financial-years.index')->with('status', 'Financial year deleted.');
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
