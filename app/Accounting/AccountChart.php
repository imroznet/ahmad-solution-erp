<?php

namespace App\Accounting;

use App\Models\Account;

/**
 * Inserts the locked system accounts once.
 * A later run does not rename or reactivate a system account that is already there.
 * Retained Earnings prefers code 3000. An equity account that already uses that code
 * and has no system key is claimed, and reactivated if it was off, because the
 * carry-forward has to post to it. Any other account keeps code 3000, and Retained
 * Earnings takes the next free code from 3001 through 3999.
 */
final class AccountChart
{
    public function install(): void
    {
        foreach (SystemAccounts::definitions() as $definition) {
            if (Account::query()->where('system_key', $definition['key'])->exists()) {
                continue;
            }

            if ($definition['key'] === 'retained_earnings') {
                $this->installRetainedEarnings($definition);

                continue;
            }

            Account::query()->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'type' => $definition['type'],
                'system_key' => $definition['key'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array{code: string, name: string, type: AccountType, key: string}  $definition
     */
    private function installRetainedEarnings(array $definition): void
    {
        $occupant = Account::query()->where('code', $definition['code'])->first();

        if ($occupant instanceof Account && $this->canClaim($occupant)) {
            $occupant->system_key = $definition['key'];
            $occupant->is_active = true;
            $occupant->save();

            return;
        }

        Account::query()->create([
            'code' => $occupant instanceof Account ? $this->freeEquityCode() : $definition['code'],
            'name' => $definition['name'],
            'type' => $definition['type'],
            'system_key' => $definition['key'],
            'is_active' => true,
        ]);
    }

    private function canClaim(Account $account): bool
    {
        return $account->type === AccountType::Equity && ! $account->isSystem();
    }

    private function freeEquityCode(): string
    {
        $number = 3001;

        while ($number <= 3999) {
            $code = (string) $number;

            if (! Account::query()->where('code', $code)->exists()) {
                return $code;
            }

            $number++;
        }

        throw new AccountingException('The equity code range is full, so Retained Earnings could not be added.');
    }
}
