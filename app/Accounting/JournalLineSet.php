<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\Party;
use App\Support\Decimal;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Turns form rows into debit and credit strings at poisha scale.
 * Balance uses JournalBalance. A draft may be saved unbalanced.
 */
final class JournalLineSet
{
    /**
     * @param  list<mixed>  $rows
     * @return list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string|null}>
     */
    public function parse(array $rows, bool $requireBalance, bool $skipIdleRows = false): array
    {
        $pending = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new AccountingException('Each journal line has to be a row.');
            }

            $accountRaw = $row['account_id'] ?? null;
            $hasAccount = $accountRaw !== null && $accountRaw !== '';
            $debit = $this->money($row['debit'] ?? null);
            $credit = $this->money($row['credit'] ?? null);
            $partyRaw = $row['party_id'] ?? null;
            $hasParty = $partyRaw !== null && $partyRaw !== '';
            $memo = $this->memo($row['memo'] ?? null, 200, 'A line note must be 200 characters or fewer.');
            $hasAmount = ! Decimal::of($debit->amount())->isZero() || ! Decimal::of($credit->amount())->isZero();

            if (! $hasAccount && ! $hasAmount && ! $hasParty && $memo === null) {
                continue;
            }

            if ($skipIdleRows && ! $hasAmount && ! $hasParty && $memo === null) {
                continue;
            }

            if (! $hasAccount || ! is_numeric($accountRaw)) {
                throw new AccountingException('Choose an account for each line.');
            }

            $pending[] = [
                'account_id' => (int) $accountRaw,
                'debit' => $debit,
                'credit' => $credit,
                'party_id' => $hasParty && is_numeric($partyRaw) ? (int) $partyRaw : null,
                'party_was_set' => $hasParty,
                'memo' => $memo,
            ];
        }

        if (count($pending) < 2) {
            throw new AccountingException('Add at least two lines.');
        }

        $accounts = Account::query()
            ->whereIn('id', array_column($pending, 'account_id'))
            ->get()
            ->keyBy('id');
        $partyIds = array_values(array_filter(array_map(
            fn (array $line): ?int => $line['party_id'],
            $pending,
        )));
        $parties = $partyIds === []
            ? collect()
            : Party::query()->whereIn('id', $partyIds)->get()->keyBy('id');

        $lines = [];
        $debits = [];
        $credits = [];

        foreach ($pending as $line) {
            $account = $accounts->get($line['account_id']);

            if (! $account instanceof Account) {
                throw new AccountingException('Choose an account from the list.');
            }

            if (! $account->is_active) {
                throw new AccountingException($account->name.' is deactivated and cannot be used for a new posting.');
            }

            if ($line['party_was_set'] && ($line['party_id'] === null || ! $parties->has($line['party_id']))) {
                throw new AccountingException('Choose a party from the list.');
            }

            $debitZero = Decimal::of($line['debit']->amount())->isZero();
            $creditZero = Decimal::of($line['credit']->amount())->isZero();

            if ($debitZero && $creditZero) {
                throw new AccountingException('Enter a debit or a credit on each line.');
            }

            if (! $debitZero && ! $creditZero) {
                throw new AccountingException('A line has a debit or a credit, not both.');
            }

            $lines[] = [
                'account_id' => $account->id,
                'debit' => $line['debit']->amount(),
                'credit' => $line['credit']->amount(),
                'party_id' => $line['party_id'],
                'memo' => $line['memo'],
            ];
            $debits[] = $line['debit']->amount();
            $credits[] = $line['credit']->amount();
        }

        if ($requireBalance) {
            $this->assertBalanced($debits, $credits);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $debits
     * @param  list<string>  $credits
     */
    public function assertBalanced(array $debits, array $credits): void
    {
        try {
            $balanced = JournalBalance::isBalanced($debits, $credits);
        } catch (InvalidArgumentException) {
            throw new AccountingException('Amounts must be zero or positive.');
        }

        if (! $balanced) {
            throw new AccountingException('The journal does not balance. Debits must equal credits.');
        }
    }

    private function money(mixed $value): Money
    {
        if ($value === null || $value === '') {
            return Money::zero();
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new AccountingException('Enter each amount as a number.');
        }

        try {
            $amount = Money::of($value);
        } catch (InvalidArgumentException) {
            throw new AccountingException('Enter each amount as a number.');
        }

        if (Decimal::of($amount->amount())->isNegative()) {
            throw new AccountingException('Amounts must be zero or positive.');
        }

        return $amount;
    }

    private function memo(mixed $value, int $limit, string $message): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new AccountingException($message);
        }

        $memo = trim((string) $value);

        if ($memo === '') {
            return null;
        }

        if (mb_strlen($memo) > $limit) {
            throw new AccountingException($message);
        }

        return $memo;
    }
}
