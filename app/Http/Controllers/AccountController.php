<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\AccountType;
use App\Accounting\AccountWriter;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SaveAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountController extends Controller
{
    use RedirectsAccountingErrors;

    public function index(): View
    {
        $accounts = Account::query()->with(['bankAccount', 'mfsAccount'])->orderBy('code')->get();
        $byId = $accounts->keyBy('id');
        $depths = [];

        foreach ($accounts as $account) {
            $depth = 0;
            $cursor = $account;
            $guard = 0;

            while ($cursor->parent_id !== null && $guard < 8) {
                $parent = $byId->get($cursor->parent_id);

                if (! $parent instanceof Account) {
                    break;
                }

                $depth++;
                $cursor = $parent;
                $guard++;
            }

            $depths[$account->id] = $depth;
        }

        return view('accounting.accounts.index', [
            'accounts' => $accounts,
            'depths' => $depths,
            'types' => AccountType::options(),
            'canManage' => $this->actor(request()->user())->hasPermission('chart_of_accounts.manage'),
        ]);
    }

    public function store(SaveAccountRequest $request, AccountWriter $writer): RedirectResponse
    {
        try {
            $writer->create([
                'code' => $request->string('code')->toString(),
                'name' => $request->string('name')->toString(),
                'type' => $request->string('type')->toString(),
                'parent_id' => $request->input('parent_id'),
            ], $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'account');
        }

        return redirect()->route('accounts.index')->with('status', 'Account added.');
    }

    public function update(UpdateAccountRequest $request, Account $account, AccountWriter $writer): RedirectResponse
    {
        if ($account->isSystem()) {
            abort(403);
        }

        try {
            $writer->update($account, [
                'name' => $request->string('name')->toString(),
                'parent_id' => $request->input('parent_id'),
                'is_active' => $request->boolean('is_active'),
            ], $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'account');
        }

        return redirect()->route('accounts.index')->with('status', 'Account updated.');
    }

    public function destroy(Account $account, AccountWriter $writer): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('chart_of_accounts.manage'), 403);

        if ($account->isSystem()) {
            abort(403);
        }

        try {
            $writer->delete($account, $actor);
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'account');
        }

        return redirect()->route('accounts.index')->with('status', 'Account deleted.');
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
