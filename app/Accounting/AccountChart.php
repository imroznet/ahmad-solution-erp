<?php

namespace App\Accounting;

use App\Models\Account;

/**
 * Inserts the locked system accounts once.
 * A later run does not rename or reactivate a row that is already there.
 */
final class AccountChart
{
    public function install(): void
    {
        foreach (SystemAccounts::definitions() as $definition) {
            $existing = Account::query()->where('system_key', $definition['key'])->first();

            if ($existing !== null) {
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
}
