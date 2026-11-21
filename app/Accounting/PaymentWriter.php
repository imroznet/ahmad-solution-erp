<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records a supplier payment and its journal in one transaction.
 * A recorded payment is not edited. Void reverses the journal.
 */
final class PaymentWriter
{
    /**
     * @var list<string>
     */
    public const MODES = ['cash', 'bank_transfer', 'cheque', 'bkash', 'nagad'];

    public function __construct(
        private PaymentNumberAllocator $numbers,
        private JournalWriter $journals,
        private DocumentPosting $posting,
        private PaymentBalance $balances,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function record(array $input, User $actor): Payment
    {
        return DB::transaction(function () use ($input, $actor): Payment {
            $prepared = $this->prepare($input);
            $payment = new Payment([
                'number' => $this->numbers->allocate(),
                'party_id' => $prepared['party_id'],
                'account_id' => $prepared['account_id'],
                'payment_date' => $prepared['date'],
                'amount' => $prepared['amount']->amount(),
                'mode' => $prepared['mode'],
                'reference' => $prepared['reference'],
                'cash_flow_category' => $prepared['category']->value,
                'status' => PaymentStatus::Recorded,
                'created_by' => $actor->id,
            ]);
            $payment->save();

            foreach ($prepared['allocations'] as $allocation) {
                $payment->allocations()->create([
                    'journal_id' => $allocation['journal_id'],
                    'amount' => $allocation['amount']->amount(),
                ]);
            }

            $this->journals->postDocument([
                'journal_date' => $prepared['date'],
                'memo' => 'Supplier payment '.$payment->number,
                'lines' => $this->lines($payment, $prepared),
            ], JournalSource::Payment, 'payment', (int) $payment->id, $actor);
            $payment->load('allocations');
            $this->audit->record('payment_recorded', $payment, newValues: $this->snapshot($payment), actor: $actor);

            return $payment;
        });
    }

    public function void(Payment $payment, User $actor, string $reason): Payment
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
            throw new AccountingException('Enter a void reason of 3 to 1000 characters.');
        }

        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked instanceof Payment) {
                throw new AccountingException('That payment is no longer available.');
            }

            if ($locked->status !== PaymentStatus::Recorded) {
                throw new AccountingException('This payment is already void.');
            }

            $journal = $this->journals->findPostedFor(JournalSource::Payment, 'payment', (int) $locked->id);
            $locked->status = PaymentStatus::Void;
            $locked->void_reason = $reason;
            $locked->voided_at = now();
            $locked->voided_by = $actor->id;
            $locked->save();

            if ($journal !== null) {
                $this->journals->reverse($journal, $actor);
            }

            $this->audit->record('payment_voided', $locked, newValues: [
                ...$this->snapshot($locked),
                'void_reason' => $reason,
            ], actor: $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{party_id: int, account_id: int, date: string, amount: Money, mode: string, reference: string|null, category: CashFlowCategory, allocations: list<array{journal_id: int, amount: Money}>}
     */
    private function prepare(array $input): array
    {
        $party = $this->party($input['party_id'] ?? null);
        $account = $this->account($input['account_id'] ?? null);
        $date = $this->date($input['payment_date'] ?? null);
        $amount = $this->money($input['amount'] ?? null, 'Amount paid');

        if ($this->isZero($amount)) {
            throw new AccountingException('Enter an amount paid.');
        }

        $mode = $input['mode'] ?? null;

        if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
            throw new AccountingException('Choose how the payment was made.');
        }

        return [
            'party_id' => (int) $party->id,
            'account_id' => (int) $account->id,
            'date' => $date,
            'amount' => $amount,
            'mode' => $mode,
            'reference' => $this->reference($input['reference'] ?? null),
            'category' => CashFlowCategory::fromInput($input['cash_flow_category'] ?? null),
            'allocations' => $this->allocations($input['allocations'] ?? [], $party, $date, $amount),
        ];
    }

    /**
     * @param  array{amount: Money, party_id: int, account_id: int}  $prepared
     * @return list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string}>
     */
    private function lines(Payment $payment, array $prepared): array
    {
        return [
            $this->posting->line('accounts_payable', $prepared['amount']->amount(), '0', (int) $payment->party_id),
            [
                'account_id' => (int) $payment->account_id,
                'debit' => '0',
                'credit' => $prepared['amount']->amount(),
                'party_id' => null,
                'memo' => '',
            ],
        ];
    }

    private function party(mixed $id): Party
    {
        if (! is_numeric($id)) {
            throw new AccountingException('Choose a supplier.');
        }

        $party = Party::query()->whereKey((int) $id)->first();

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole('supplier')) {
            throw new AccountingException('Choose an active supplier.');
        }

        return $party;
    }

    private function account(mixed $id): Account
    {
        if (! is_numeric($id)) {
            throw new AccountingException('Choose the account the money was paid from.');
        }

        $account = Account::query()->forSettlement()->whereKey((int) $id)->first();

        if (! $account instanceof Account) {
            throw new AccountingException('Choose Cash in Hand or an active bank or MFS account.');
        }

        return $account;
    }

    /**
     * @return list<array{journal_id: int, amount: Money}>
     */
    private function allocations(mixed $rows, Party $party, string $date, Money $amount): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        if (! is_array($rows)) {
            throw new AccountingException('Allocations have to be a list.');
        }

        $pending = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new AccountingException('Each allocation has to be a row.');
            }

            $journalId = $row['journal_id'] ?? null;
            $amountRaw = $row['amount'] ?? null;
            $blank = ($journalId === null || $journalId === '') && ($amountRaw === null || $amountRaw === '');

            if ($blank) {
                continue;
            }

            if (! is_numeric($journalId)) {
                throw new AccountingException('Choose a journal for each allocation.');
            }

            $allocation = $this->money($amountRaw, 'Allocation');

            if ($this->isZero($allocation)) {
                throw new AccountingException('Each allocation has to be more than zero.');
            }

            $pending[] = ['journal_id' => (int) $journalId, 'amount' => $allocation];
        }

        $ids = array_column($pending, 'journal_id');

        if (count($ids) !== count(array_unique($ids))) {
            throw new AccountingException('Allocate a journal only once on the same payment.');
        }

        sort($ids);
        $allocated = Money::zero();
        $checked = [];

        foreach ($ids as $journalId) {
            $rowAmount = null;

            foreach ($pending as $row) {
                if ($row['journal_id'] === $journalId) {
                    $rowAmount = $row['amount'];
                }
            }

            if (! $rowAmount instanceof Money) {
                continue;
            }

            $this->lockJournal($journalId);
            $this->pauseForAllocationRace();
            $journal = Journal::query()->whereKey($journalId)->first();

            if (! $journal instanceof Journal || $journal->status !== JournalStatus::Posted || $journal->source !== JournalSource::Manual) {
                throw new AccountingException('Choose a posted journal that credits Accounts Payable for this supplier.');
            }

            if ($journal->reversal()->exists()) {
                throw new AccountingException('A reversed journal cannot be allocated.');
            }

            if ($date < $journal->journalDateString()) {
                throw new AccountingException('A payment cannot be allocated to a journal dated after the payment.');
            }

            $obligation = $this->balances->obligation($journal, (int) $party->id);

            if (Decimal::of($obligation->amount())->compare(Decimal::of('0.00')) <= 0) {
                throw new AccountingException('Choose a posted journal that credits Accounts Payable for this supplier.');
            }

            $outstanding = $this->balances->outstanding($journal, (int) $party->id, true);

            if (Decimal::of($rowAmount->amount())->compare(Decimal::of($outstanding->amount())) > 0) {
                throw new AccountingException('The allocation is more than the obligation outstanding.');
            }

            $allocated = $allocated->plus($rowAmount);
            $checked[] = ['journal_id' => $journalId, 'amount' => $rowAmount];
        }

        if (Decimal::of($allocated->amount())->compare(Decimal::of($amount->amount())) > 0) {
            throw new AccountingException('Allocations cannot exceed the payment.');
        }

        return $checked;
    }

    private function pauseForAllocationRace(): void
    {
        // The disposable MySQL rehearsal holds the journal lock so the other
        // worker is waiting with an older snapshot. Production leaves this unset.
        if (getenv('ASL_PAYMENT_ALLOCATION_DELAY') === '1') {
            sleep(1);
        }
    }

    private function lockJournal(int $journalId): void
    {
        Journal::query()->whereKey($journalId)->lockForUpdate()->first(['id']);
    }

    private function money(mixed $value, string $label): Money
    {
        if ($value === null || $value === '') {
            return Money::zero();
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new AccountingException($label.' must be a number.');
        }

        try {
            $amount = Money::of(is_int($value) ? $value : $value);
        } catch (\InvalidArgumentException) {
            throw new AccountingException($label.' must be a number.');
        }

        if (Decimal::of($amount->amount())->isNegative()) {
            throw new AccountingException($label.' must be zero or more.');
        }

        return $amount;
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new AccountingException('Enter the payment date.');
        }

        return $value;
    }

    private function reference(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new AccountingException('The reference must be text.');
        }

        $reference = trim($value);

        if ($reference === '') {
            return null;
        }

        if (mb_strlen($reference) > 80) {
            throw new AccountingException('The reference must be 80 characters or fewer.');
        }

        return $reference;
    }

    private function isZero(Money $money): bool
    {
        return Decimal::of($money->amount())->isZero();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payment $payment): array
    {
        $payment->loadMissing('allocations');

        return [
            'number' => $payment->number,
            'party_id' => $payment->party_id,
            'account_id' => $payment->account_id,
            'payment_date' => $payment->paymentDateString(),
            'amount' => $payment->decimalAttribute('amount'),
            'mode' => $payment->mode,
            'reference' => $payment->reference,
            'cash_flow_category' => $payment->cash_flow_category,
            'status' => $payment->status->value,
            'allocations' => $payment->allocations->map(fn ($allocation): array => [
                'journal_id' => $allocation->journal_id,
                'amount' => $allocation->decimalAttribute('amount'),
            ])->all(),
        ];
    }
}
