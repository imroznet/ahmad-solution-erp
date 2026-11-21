<?php

namespace App\Accounting;

/**
 * Accounts the ledger module creates and then locks.
 * Cash in Hand is the only seeded cash account. Bank and MFS accounts are added later.
 * Output VAT Payable is the account Phase 2 will credit when a Mushak 6.3 is issued.
 * This module does not post that VAT, and it does not accrue interest.
 */
final class SystemAccounts
{
    /**
     * @return list<array{code: string, name: string, type: AccountType, key: string}>
     */
    public static function definitions(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash in Hand', 'type' => AccountType::Asset, 'key' => 'cash'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'key' => 'accounts_receivable'],
            ['code' => '1200', 'name' => 'VDS Receivable', 'type' => AccountType::Asset, 'key' => 'vds_receivable'],
            ['code' => '1300', 'name' => 'AIT Receivable', 'type' => AccountType::Asset, 'key' => 'ait_receivable'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'key' => 'accounts_payable'],
            ['code' => '2100', 'name' => 'Output VAT Payable', 'type' => AccountType::Liability, 'key' => 'output_vat_payable'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => AccountType::Income, 'key' => 'sales_revenue'],
        ];
    }
}
