<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\MfsAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Adds a bank or MFS account as its own asset in the chart.
 * Nothing here is seeded. Cash in Hand stays the only system cash account.
 */
final class CashAccountWriter
{
    public function __construct(
        private AccountWriter $accounts,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{code: string, bank_name: string, branch: string, account_title: string, account_number: string, account_type: string, opened_on: string, parent_id?: int|null}  $input
     */
    public function createBank(array $input, User $actor): BankAccount
    {
        $write = function () use ($input, $actor): BankAccount {
            $type = $this->bankType($input['account_type']);
            $title = $this->text($input['account_title'], 'Enter the account title.');
            $account = $this->accounts->create([
                'code' => $input['code'],
                'name' => $title,
                'type' => AccountType::Asset->value,
                'parent_id' => $input['parent_id'] ?? null,
            ], $actor);
            $bank = new BankAccount([
                'account_id' => $account->id,
                'bank_name' => $this->text($input['bank_name'], 'Enter the bank name.'),
                'branch' => $this->text($input['branch'], 'Enter the branch.'),
                'account_title' => $title,
                'account_number' => $this->accountNumber($input['account_number']),
                'account_type' => $type,
                'opened_on' => $this->openedOn($input['opened_on']),
            ]);
            $this->saveBank($bank);
            $this->audit->record('bank_account_created', $bank, newValues: $this->bankSnapshot($bank), actor: $actor);

            return $bank;
        };

        return $this->within($write);
    }

    /**
     * @param  array{bank_name: string, branch: string, account_title: string, account_number: string, account_type: string, opened_on: string, parent_id?: int|null, is_active?: bool}  $input
     */
    public function updateBank(BankAccount $bank, array $input, User $actor): BankAccount
    {
        $write = function () use ($bank, $input, $actor): BankAccount {
            $account = $this->ledgerAccount($bank->account);
            $before = $this->bankSnapshot($bank);
            $title = $this->text($input['account_title'], 'Enter the account title.');
            $this->rename($account, $title, $input['parent_id'] ?? null, (bool) ($input['is_active'] ?? false), $actor);
            $bank->bank_name = $this->text($input['bank_name'], 'Enter the bank name.');
            $bank->branch = $this->text($input['branch'], 'Enter the branch.');
            $bank->account_title = $title;
            $bank->account_number = $this->accountNumber($input['account_number']);
            $bank->account_type = $this->bankType($input['account_type']);
            $bank->opened_on = $this->openedOn($input['opened_on']);
            $this->saveBank($bank);
            $this->audit->record('bank_account_updated', $bank, oldValues: $before, newValues: $this->bankSnapshot($bank), actor: $actor);

            return $bank;
        };

        return $this->within($write);
    }

    public function deleteBank(BankAccount $bank, User $actor): void
    {
        $write = function () use ($bank, $actor): void {
            $account = $this->ledgerAccount($bank->account);
            $this->guardDeletion($account);
            $before = $this->bankSnapshot($bank);
            $bank->delete();
            $this->audit->record('bank_account_deleted', $bank, oldValues: $before, actor: $actor);
            $this->accounts->delete($account, $actor);
        };

        $this->within($write);
    }

    /**
     * @param  array{code: string, provider: string, mobile_number: string, account_title: string, opened_on: string, parent_id?: int|null}  $input
     */
    public function createMfs(array $input, User $actor): MfsAccount
    {
        $write = function () use ($input, $actor): MfsAccount {
            $title = $this->text($input['account_title'], 'Enter the account title.');
            $account = $this->accounts->create([
                'code' => $input['code'],
                'name' => $title,
                'type' => AccountType::Asset->value,
                'parent_id' => $input['parent_id'] ?? null,
            ], $actor);
            $mfs = new MfsAccount([
                'account_id' => $account->id,
                'provider' => $this->provider($input['provider']),
                'mobile_number' => $this->mobile($input['mobile_number']),
                'account_title' => $title,
                'opened_on' => $this->openedOn($input['opened_on']),
            ]);
            $this->saveMfs($mfs);
            $this->audit->record('mfs_account_created', $mfs, newValues: $this->mfsSnapshot($mfs), actor: $actor);

            return $mfs;
        };

        return $this->within($write);
    }

    /**
     * @param  array{provider: string, mobile_number: string, account_title: string, opened_on: string, parent_id?: int|null, is_active?: bool}  $input
     */
    public function updateMfs(MfsAccount $mfs, array $input, User $actor): MfsAccount
    {
        $write = function () use ($mfs, $input, $actor): MfsAccount {
            $account = $this->ledgerAccount($mfs->account);
            $before = $this->mfsSnapshot($mfs);
            $title = $this->text($input['account_title'], 'Enter the account title.');
            $this->rename($account, $title, $input['parent_id'] ?? null, (bool) ($input['is_active'] ?? false), $actor);
            $mfs->provider = $this->provider($input['provider']);
            $mfs->mobile_number = $this->mobile($input['mobile_number']);
            $mfs->account_title = $title;
            $mfs->opened_on = $this->openedOn($input['opened_on']);
            $this->saveMfs($mfs);
            $this->audit->record('mfs_account_updated', $mfs, oldValues: $before, newValues: $this->mfsSnapshot($mfs), actor: $actor);

            return $mfs;
        };

        return $this->within($write);
    }

    public function deleteMfs(MfsAccount $mfs, User $actor): void
    {
        $write = function () use ($mfs, $actor): void {
            $account = $this->ledgerAccount($mfs->account);
            $this->guardDeletion($account);
            $before = $this->mfsSnapshot($mfs);
            $mfs->delete();
            $this->audit->record('mfs_account_deleted', $mfs, oldValues: $before, actor: $actor);
            $this->accounts->delete($account, $actor);
        };

        $this->within($write);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function within(Closure $callback): mixed
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction($callback, 5);
        }

        return $callback();
    }

    private function rename(Account $account, string $title, mixed $parentId, bool $active, User $actor): void
    {
        $before = [
            'name' => $account->name,
            'parent_id' => $account->parent_id,
            'is_active' => $account->is_active,
        ];
        $account->name = $title;
        $account->parent_id = $this->accounts->parentKey($parentId, AccountType::Asset, $account);
        $account->is_active = $active;
        $account->save();
        $this->audit->record('account_updated', $account, oldValues: $before, newValues: [
            'name' => $account->name,
            'parent_id' => $account->parent_id,
            'is_active' => $account->is_active,
        ], actor: $actor);
    }

    private function guardDeletion(Account $account): void
    {
        if ($account->lines()->exists()) {
            throw new AccountingException('An account with a posting cannot be deleted. Deactivate it instead.');
        }

        if ($account->children()->exists()) {
            throw new AccountingException('Move the child accounts before deleting this one.');
        }
    }

    private function ledgerAccount(mixed $account): Account
    {
        if (! $account instanceof Account) {
            throw new AccountingException('That account is no longer on the chart.');
        }

        return $account;
    }

    private function saveBank(BankAccount $bank): void
    {
        try {
            $bank->save();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'account_number')) {
                throw new AccountingException('That account number is already used at this bank.');
            }

            throw $exception;
        }
    }

    private function saveMfs(MfsAccount $mfs): void
    {
        try {
            $mfs->save();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'mobile_number')) {
                throw new AccountingException('That mobile number is already used.');
            }

            throw $exception;
        }
    }

    private function bankType(string $value): BankAccountType
    {
        $type = BankAccountType::tryFrom($value);

        if ($type === null) {
            throw new AccountingException('Choose savings or current.');
        }

        return $type;
    }

    private function provider(string $value): MfsProvider
    {
        $provider = MfsProvider::tryFrom($value);

        if ($provider === null) {
            throw new AccountingException('Choose bKash, Nagad, or Rocket.');
        }

        return $provider;
    }

    private function text(string $value, string $message): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 160) {
            throw new AccountingException($message);
        }

        return $value;
    }

    private function accountNumber(string $value): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 40) {
            throw new AccountingException('Enter the account number.');
        }

        return $value;
    }

    private function openedOn(string $value): Carbon
    {
        $value = trim($value);

        if ($value === '') {
            throw new AccountingException('Enter the date the account was opened.');
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            throw new AccountingException('Enter the date the account was opened.');
        }
    }

    private function mobile(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^01\d{9}$/', $value) !== 1) {
            throw new AccountingException('Enter an 11-digit mobile number starting with 01.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function bankSnapshot(BankAccount $bank): array
    {
        return [
            'account_id' => $bank->account_id,
            'bank_name' => $bank->bank_name,
            'branch' => $bank->branch,
            'account_title' => $bank->account_title,
            'account_number' => $bank->account_number,
            'account_type' => $bank->account_type->value,
            'opened_on' => $bank->openedOn(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mfsSnapshot(MfsAccount $mfs): array
    {
        return [
            'account_id' => $mfs->account_id,
            'provider' => $mfs->provider->value,
            'mobile_number' => $mfs->mobile_number,
            'account_title' => $mfs->account_title,
            'opened_on' => $mfs->openedOn(),
        ];
    }
}
