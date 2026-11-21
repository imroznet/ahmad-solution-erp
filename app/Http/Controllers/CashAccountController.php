<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\AccountType;
use App\Accounting\BankAccountType;
use App\Accounting\CashAccountWriter;
use App\Accounting\MfsProvider;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SaveBankAccountRequest;
use App\Http\Requests\SaveMfsAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Requests\UpdateMfsAccountRequest;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\MfsAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    use RedirectsAccountingErrors;

    public function index(): View
    {
        $actor = $this->actor(request()->user());

        return view('accounting.cash-accounts.index', [
            'banks' => BankAccount::query()->with('account')->orderBy('bank_name')->orderBy('account_title')->get(),
            'wallets' => MfsAccount::query()->with('account')->orderBy('provider')->orderBy('account_title')->get(),
            'parents' => Account::query()->where('type', AccountType::Asset)->orderBy('code')->get(),
            'bankTypes' => BankAccountType::options(),
            'providers' => MfsProvider::options(),
            'canManage' => $actor->hasPermission('chart_of_accounts.manage'),
        ]);
    }

    public function storeBank(SaveBankAccountRequest $request, CashAccountWriter $writer): RedirectResponse
    {
        try {
            $writer->createBank($this->bankInput($request), $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'bank');
        }

        return redirect()->route('cash-accounts.index')->with('status', 'Bank account added.');
    }

    public function updateBank(UpdateBankAccountRequest $request, BankAccount $bankAccount, CashAccountWriter $writer): RedirectResponse
    {
        try {
            $writer->updateBank($bankAccount, [
                'bank_name' => $request->string('bank_name')->toString(),
                'branch' => $request->string('branch')->toString(),
                'account_title' => $request->string('account_title')->toString(),
                'account_number' => $request->string('account_number')->toString(),
                'account_type' => $request->string('account_type')->toString(),
                'opened_on' => $request->string('opened_on')->toString(),
                'parent_id' => $request->input('parent_id'),
                'is_active' => $request->boolean('is_active'),
            ], $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'bank');
        }

        return redirect()->route('cash-accounts.index')->with('status', 'Bank account updated.');
    }

    public function destroyBank(BankAccount $bankAccount, CashAccountWriter $writer): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('chart_of_accounts.manage'), 403);

        try {
            $writer->deleteBank($bankAccount, $actor);
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'bank');
        }

        return redirect()->route('cash-accounts.index')->with('status', 'Bank account deleted.');
    }

    public function storeMfs(SaveMfsAccountRequest $request, CashAccountWriter $writer): RedirectResponse
    {
        try {
            $writer->createMfs($this->mfsInput($request), $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'mfs');
        }

        return redirect()->route('cash-accounts.index')->with('status', 'MFS account added.');
    }

    public function updateMfs(UpdateMfsAccountRequest $request, MfsAccount $mfsAccount, CashAccountWriter $writer): RedirectResponse
    {
        try {
            $writer->updateMfs($mfsAccount, [
                'provider' => $request->string('provider')->toString(),
                'mobile_number' => $request->string('mobile_number')->toString(),
                'account_title' => $request->string('account_title')->toString(),
                'opened_on' => $request->string('opened_on')->toString(),
                'parent_id' => $request->input('parent_id'),
                'is_active' => $request->boolean('is_active'),
            ], $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'mfs');
        }

        return redirect()->route('cash-accounts.index')->with('status', 'MFS account updated.');
    }

    public function destroyMfs(MfsAccount $mfsAccount, CashAccountWriter $writer): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('chart_of_accounts.manage'), 403);

        try {
            $writer->deleteMfs($mfsAccount, $actor);
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'mfs');
        }

        return redirect()->route('cash-accounts.index')->with('status', 'MFS account deleted.');
    }

    /**
     * @return array{code: string, bank_name: string, branch: string, account_title: string, account_number: string, account_type: string, opened_on: string, parent_id: mixed}
     */
    private function bankInput(SaveBankAccountRequest $request): array
    {
        return [
            'code' => $request->string('code')->toString(),
            'bank_name' => $request->string('bank_name')->toString(),
            'branch' => $request->string('branch')->toString(),
            'account_title' => $request->string('account_title')->toString(),
            'account_number' => $request->string('account_number')->toString(),
            'account_type' => $request->string('account_type')->toString(),
            'opened_on' => $request->string('opened_on')->toString(),
            'parent_id' => $request->input('parent_id'),
        ];
    }

    /**
     * @return array{code: string, provider: string, mobile_number: string, account_title: string, opened_on: string, parent_id: mixed}
     */
    private function mfsInput(SaveMfsAccountRequest $request): array
    {
        return [
            'code' => $request->string('code')->toString(),
            'provider' => $request->string('provider')->toString(),
            'mobile_number' => $request->string('mobile_number')->toString(),
            'account_title' => $request->string('account_title')->toString(),
            'opened_on' => $request->string('opened_on')->toString(),
            'parent_id' => $request->input('parent_id'),
        ];
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
