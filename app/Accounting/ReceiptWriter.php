<?php

namespace App\Accounting;

use App\Documents\DocumentStatus;
use App\Models\Account;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\Party;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a customer receipt and its journal in one transaction.
 * A recorded receipt is not edited. Void reverses the journal.
 */
final class ReceiptWriter
{
    /**
     * @var list<string>
     */
    public const MODES = ['cash', 'bank_transfer', 'cheque', 'bkash', 'nagad'];

    public function __construct(
        private ReceiptNumberAllocator $numbers,
        private JournalWriter $journals,
        private DocumentPosting $posting,
        private InvoiceBalance $balances,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function record(array $input, User $actor): Receipt
    {
        return DB::transaction(function () use ($input, $actor): Receipt {
            $prepared = $this->prepare($input);
            $receipt = new Receipt([
                'number' => $this->numbers->allocate(),
                'party_id' => $prepared['party_id'],
                'account_id' => $prepared['account_id'],
                'receipt_date' => $prepared['date'],
                'amount_received' => $prepared['received']->amount(),
                'vds_deducted' => $prepared['vds']->amount(),
                'ait_deducted' => $prepared['ait']->amount(),
                'mode' => $prepared['mode'],
                'reference' => $prepared['reference'],
                'status' => ReceiptStatus::Recorded,
                'created_by' => $actor->id,
            ]);
            $receipt->save();

            foreach ($prepared['allocations'] as $allocation) {
                $receipt->allocations()->create([
                    'commercial_invoice_id' => $allocation['invoice_id'],
                    'amount' => $allocation['amount']->amount(),
                ]);
            }

            $this->journals->postDocument([
                'journal_date' => $prepared['date'],
                'memo' => 'Customer receipt '.$receipt->number,
                'lines' => $this->lines($receipt, $prepared),
            ], JournalSource::Receipt, 'receipt', (int) $receipt->id, $actor);
            $receipt->load('allocations');
            $this->audit->record('receipt_recorded', $receipt, newValues: $this->snapshot($receipt), actor: $actor);

            return $receipt;
        });
    }

    public function void(Receipt $receipt, User $actor, string $reason): Receipt
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
            throw new AccountingException('Enter a void reason of 3 to 1000 characters.');
        }

        return DB::transaction(function () use ($receipt, $actor, $reason): Receipt {
            $locked = Receipt::query()->whereKey($receipt->id)->lockForUpdate()->first();

            if (! $locked instanceof Receipt) {
                throw new AccountingException('That receipt is no longer available.');
            }

            if ($locked->status !== ReceiptStatus::Recorded) {
                throw new AccountingException('This receipt is already void.');
            }

            $journal = $this->journals->findPostedFor(JournalSource::Receipt, 'receipt', (int) $locked->id);
            $locked->status = ReceiptStatus::Void;
            $locked->void_reason = $reason;
            $locked->voided_at = now();
            $locked->voided_by = $actor->id;
            $locked->save();

            if ($journal !== null) {
                $this->journals->reverse($journal, $actor);
            }

            $this->audit->record('receipt_voided', $locked, newValues: [
                ...$this->snapshot($locked),
                'void_reason' => $reason,
            ], actor: $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{party_id: int, account_id: int, date: string, received: Money, vds: Money, ait: Money, credit: Money, mode: string, reference: string|null, allocations: list<array{invoice_id: int, amount: Money}>}
     */
    private function prepare(array $input): array
    {
        $party = $this->party($input['party_id'] ?? null);
        $account = $this->account($input['account_id'] ?? null);
        $date = $this->date($input['receipt_date'] ?? null);
        $received = $this->money($input['amount_received'] ?? null, 'Amount received');
        $vds = $this->money($input['vds_deducted'] ?? null, 'VDS deducted');
        $ait = $this->money($input['ait_deducted'] ?? null, 'AIT deducted');
        $credit = $received->plus($vds)->plus($ait);

        if ($this->isZero($credit)) {
            throw new AccountingException('Enter an amount received, VDS deducted, or AIT deducted.');
        }

        $mode = $input['mode'] ?? null;

        if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
            throw new AccountingException('Choose how the receipt was collected.');
        }

        $reference = $this->reference($input['reference'] ?? null);
        $allocations = $this->allocations($input['allocations'] ?? [], $party, $date, $credit);

        return [
            'party_id' => (int) $party->id,
            'account_id' => (int) $account->id,
            'date' => $date,
            'received' => $received,
            'vds' => $vds,
            'ait' => $ait,
            'credit' => $credit,
            'mode' => $mode,
            'reference' => $reference,
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  array{received: Money, vds: Money, ait: Money, credit: Money, party_id: int, account_id: int}  $prepared
     * @return list<array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string}>
     */
    private function lines(Receipt $receipt, array $prepared): array
    {
        $lines = [];
        $partyId = (int) $receipt->party_id;

        if (! $this->isZero($prepared['received'])) {
            $lines[] = [
                'account_id' => (int) $receipt->account_id,
                'debit' => $prepared['received']->amount(),
                'credit' => '0',
                'party_id' => null,
                'memo' => '',
            ];
        }

        if (! $this->isZero($prepared['vds'])) {
            $lines[] = $this->posting->line('vds_receivable', $prepared['vds']->amount(), '0', $partyId);
        }

        if (! $this->isZero($prepared['ait'])) {
            $lines[] = $this->posting->line('ait_receivable', $prepared['ait']->amount(), '0', $partyId);
        }

        $lines[] = $this->posting->line('accounts_receivable', '0', $prepared['credit']->amount(), $partyId);

        return $lines;
    }

    private function party(mixed $id): Party
    {
        if (! is_numeric($id)) {
            throw new AccountingException('Choose a customer.');
        }

        $party = Party::query()->whereKey((int) $id)->first();

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole('customer')) {
            throw new AccountingException('Choose an active customer.');
        }

        return $party;
    }

    private function account(mixed $id): Account
    {
        if (! is_numeric($id)) {
            throw new AccountingException('Choose the account the money was received into.');
        }

        $account = Account::query()->forSettlement()->whereKey((int) $id)->first();

        if (! $account instanceof Account) {
            throw new AccountingException('Choose Cash in Hand or an active bank or MFS account.');
        }

        return $account;
    }

    /**
     * @return list<array{invoice_id: int, amount: Money}>
     */
    private function allocations(mixed $rows, Party $party, string $date, Money $credit): array
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

            $invoiceId = $row['commercial_invoice_id'] ?? null;
            $amountRaw = $row['amount'] ?? null;
            $blank = ($invoiceId === null || $invoiceId === '') && ($amountRaw === null || $amountRaw === '');

            if ($blank) {
                continue;
            }

            if (! is_numeric($invoiceId)) {
                throw new AccountingException('Choose an invoice for each allocation.');
            }

            $amount = $this->money($amountRaw, 'Allocation');

            if ($this->isZero($amount)) {
                throw new AccountingException('Each allocation has to be more than zero.');
            }

            $pending[] = ['invoice_id' => (int) $invoiceId, 'amount' => $amount];
        }

        $ids = array_column($pending, 'invoice_id');

        if (count($ids) !== count(array_unique($ids))) {
            throw new AccountingException('Allocate an invoice only once on the same receipt.');
        }

        sort($ids);
        $allocated = Money::zero();
        $checked = [];

        foreach ($ids as $invoiceId) {
            $amount = null;

            foreach ($pending as $row) {
                if ($row['invoice_id'] === $invoiceId) {
                    $amount = $row['amount'];
                }
            }

            if (! $amount instanceof Money) {
                continue;
            }

            $this->lockInvoice($invoiceId);
            $invoice = CommercialInvoice::query()->whereKey($invoiceId)->first();

            if (! $invoice instanceof CommercialInvoice) {
                throw new AccountingException('Choose an invoice from the list.');
            }

            if ($invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
                throw new AccountingException('Only an issued invoice can be allocated.');
            }

            $owner = $invoice->quotation()->value('party_id');

            if (! is_numeric($owner) || (int) $owner !== (int) $party->id) {
                throw new AccountingException('That invoice belongs to another customer.');
            }

            $invoiceDate = $invoice->getAttribute('invoice_date');
            $invoiceDay = $invoiceDate instanceof Carbon
                ? $invoiceDate->toDateString()
                : (is_string($invoiceDate) ? $invoiceDate : '');

            if ($invoiceDay === '' || $date < $invoiceDay) {
                throw new AccountingException('A receipt cannot be allocated to an invoice dated after the receipt.');
            }

            $outstanding = $this->balances->outstanding($invoice);

            if (Decimal::of($amount->amount())->compare(Decimal::of($outstanding->amount())) > 0) {
                throw new AccountingException('The allocation is more than the invoice outstanding.');
            }

            $allocated = $allocated->plus($amount);
            $checked[] = ['invoice_id' => $invoiceId, 'amount' => $amount];
        }

        if (Decimal::of($allocated->amount())->compare(Decimal::of($credit->amount())) > 0) {
            throw new AccountingException('Allocations cannot exceed the receipt.');
        }

        return $checked;
    }

    private function lockInvoice(int $invoiceId): void
    {
        MushakInvoice::query()
            ->where('commercial_invoice_id', $invoiceId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        CommercialInvoice::query()->whereKey($invoiceId)->lockForUpdate()->first(['id']);
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
            throw new AccountingException('Enter the receipt date.');
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
    private function snapshot(Receipt $receipt): array
    {
        $receipt->loadMissing('allocations');

        return [
            'number' => $receipt->number,
            'party_id' => $receipt->party_id,
            'account_id' => $receipt->account_id,
            'receipt_date' => $receipt->receiptDateString(),
            'amount_received' => $receipt->decimalAttribute('amount_received'),
            'vds_deducted' => $receipt->decimalAttribute('vds_deducted'),
            'ait_deducted' => $receipt->decimalAttribute('ait_deducted'),
            'mode' => $receipt->mode,
            'reference' => $receipt->reference,
            'status' => $receipt->status->value,
            'allocations' => $receipt->allocations->map(fn ($allocation): array => [
                'commercial_invoice_id' => $allocation->commercial_invoice_id,
                'amount' => $allocation->decimalAttribute('amount'),
            ])->all(),
        ];
    }
}
