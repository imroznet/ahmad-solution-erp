<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\JournalSource;
use App\Accounting\JournalWriter;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SaveOpeningBalanceRequest;
use App\Models\Account;
use App\Models\CompanyProfile;
use App\Models\Journal;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    use RedirectsAccountingErrors;

    public function edit(): View
    {
        $openings = Journal::query()
            ->where('source', JournalSource::Opening)
            ->with(['lines.account', 'lines.party'])
            ->orderBy('journal_date')
            ->orderBy('id')
            ->get();
        $used = $openings->flatMap(fn (Journal $journal) => $journal->lines->pluck('account_id'))->filter()->all();
        $profile = CompanyProfile::query()->orderBy('id')->first();
        $registered = $profile?->getAttribute('incorporation_date');
        $registrationDate = $registered instanceof Carbon
            ? $registered->toDateString()
            : (is_string($registered) && $registered !== '' ? substr($registered, 0, 10) : null);

        return view('accounting.opening.edit', [
            'openings' => $openings,
            'accounts' => Account::query()
                ->where('is_active', true)
                ->when($used !== [], fn ($query) => $query->whereNotIn('id', $used))
                ->orderBy('code')
                ->get(),
            'parties' => Party::query()->where('status', 'active')->orderBy('legal_name')->get(['id', 'legal_name']),
            'registrationDate' => $registrationDate,
        ]);
    }

    public function store(SaveOpeningBalanceRequest $request, JournalWriter $writer): RedirectResponse
    {
        $lines = $request->input('lines');

        try {
            $writer->postOpening([
                'journal_date' => $request->string('journal_date')->toString(),
                'memo' => $request->input('memo') ?: 'Opening balances',
                'lines' => is_array($lines) ? array_values($lines) : [],
            ], $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'opening');
        }

        return redirect()->route('opening-balances.edit')->with('status', 'Opening balances posted.');
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
