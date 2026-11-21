<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Creates and changes user accounts. System accounts stay as the module created them.
 */
final class AccountWriter
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{code: string, name: string, type: string, parent_id?: int|null}  $input
     */
    public function create(array $input, User $actor): Account
    {
        $type = $this->type($input['type']);
        $code = $this->code($input['code'], $type);
        $name = $this->name($input['name']);
        $parentId = $this->parentKey($input['parent_id'] ?? null, $type, null);

        if (Account::query()->where('code', $code)->exists()) {
            throw new AccountingException('That account code is already used.');
        }

        $account = Account::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'parent_id' => $parentId,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('account_created', $account, newValues: $this->snapshot($account), actor: $actor);

        return $account;
    }

    /**
     * @param  array{name: string, parent_id?: int|null, is_active?: bool}  $input
     */
    public function update(Account $account, array $input, User $actor): Account
    {
        $this->guardSystem($account);
        $this->guardCashDetail($account);
        $before = $this->snapshot($account);
        $account->name = $this->name($input['name']);
        $account->parent_id = $this->parentKey($input['parent_id'] ?? null, $account->type, $account);
        $account->is_active = (bool) ($input['is_active'] ?? false);
        $account->save();

        $this->audit->record('account_updated', $account, oldValues: $before, newValues: $this->snapshot($account), actor: $actor);

        return $account;
    }

    public function delete(Account $account, User $actor): void
    {
        $this->guardSystem($account);
        $this->guardCashDetail($account);

        if ($account->lines()->exists()) {
            throw new AccountingException('An account with a posting cannot be deleted. Deactivate it instead.');
        }

        if ($account->children()->exists()) {
            throw new AccountingException('Move the child accounts before deleting this one.');
        }

        $before = $this->snapshot($account);
        $account->delete();
        $this->audit->record('account_deleted', $account, oldValues: $before, actor: $actor);
    }

    private function guardSystem(Account $account): void
    {
        if ($account->isSystem()) {
            throw new AccountingException('A system account cannot be renamed, retyped, or deleted.');
        }
    }

    private function guardCashDetail(Account $account): void
    {
        if ($account->bankAccount()->exists() || $account->mfsAccount()->exists()) {
            throw new AccountingException('Edit a bank or MFS account on the Bank and MFS screen.');
        }
    }

    private function type(string $value): AccountType
    {
        $type = AccountType::tryFrom($value);

        if ($type === null) {
            throw new AccountingException('Choose an account type.');
        }

        return $type;
    }

    private function code(string $code, AccountType $type): string
    {
        $code = trim($code);

        if (preg_match('/^[1-5]\d{3}$/', $code) !== 1 || ! str_starts_with($code, $type->codePrefix())) {
            throw new AccountingException($type->label().' accounts use a '.$type->codePrefix().'xxx code.');
        }

        return $code;
    }

    private function name(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 160) {
            throw new AccountingException('Enter an account name of 160 characters or fewer.');
        }

        return $name;
    }

    public function parentKey(mixed $parentId, AccountType $type, ?Account $account): ?int
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        if (! is_numeric($parentId)) {
            throw new AccountingException('Choose a parent account of the same type.');
        }

        $parent = Account::query()->find((int) $parentId);

        if (! $parent instanceof Account || $parent->type !== $type) {
            throw new AccountingException('Choose a parent account of the same type.');
        }

        if ($account !== null) {
            if ($parent->id === $account->id) {
                throw new AccountingException('An account cannot be its own parent.');
            }

            $cursor = $parent;
            $guard = 0;

            while ($cursor->parent_id !== null && $guard < 12) {
                if ($cursor->parent_id === $account->id) {
                    throw new AccountingException('An account cannot be its own parent.');
                }

                $next = Account::query()->find($cursor->parent_id);

                if (! $next instanceof Account) {
                    break;
                }

                $cursor = $next;
                $guard++;
            }
        }

        return $parent->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Account $account): array
    {
        return [
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type->value,
            'parent_id' => $account->parent_id,
            'is_active' => $account->is_active,
            'system_key' => $account->system_key,
        ];
    }
}
