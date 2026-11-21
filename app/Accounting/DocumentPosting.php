<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\CommercialInvoice;
use App\Models\Journal;
use App\Models\MushakInvoice;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Posts the sales journals for an issued commercial invoice and an issued Mushak 6.3.
 * A zero amount posts nothing. Void reverses the posted journal when one exists.
 */
final class DocumentPosting
{
    public function __construct(private JournalWriter $journals) {}

    public function postInvoice(CommercialInvoice $invoice, User $actor): ?Journal
    {
        $total = Money::of($invoice->decimalAttribute('total'));

        if ($this->isZero($total)) {
            return null;
        }

        $partyId = $invoice->quotation()->value('party_id');

        if (! is_numeric($partyId)) {
            throw new AccountingException('The commercial invoice has no customer.');
        }

        return $this->journals->postDocument([
            'journal_date' => $this->date($invoice->getAttribute('invoice_date')),
            'memo' => 'Commercial invoice '.$invoice->displayNumber(),
            'lines' => [
                $this->line('accounts_receivable', $total->amount(), '0', (int) $partyId),
                $this->line('sales_revenue', '0', $total->amount(), null),
            ],
        ], JournalSource::Invoice, 'commercial_invoice', (int) $invoice->getKey(), $actor);
    }

    public function postMushak(MushakInvoice $mushak, User $actor): ?Journal
    {
        $stored = $mushak->getAttribute('total_vat');
        $vat = Money::of(is_string($stored) || is_int($stored) ? $stored : 0);

        if ($this->isZero($vat)) {
            return null;
        }

        $partyId = $mushak->getAttribute('party_id');

        if (! is_numeric($partyId)) {
            throw new AccountingException('The Mushak 6.3 has no customer.');
        }

        return $this->journals->postDocument([
            'journal_date' => $this->date($mushak->getAttribute('document_date')),
            'memo' => 'Mushak 6.3 '.$mushak->displayNumber(),
            'lines' => [
                $this->line('accounts_receivable', $vat->amount(), '0', (int) $partyId),
                $this->line('output_vat_payable', '0', $vat->amount(), null),
            ],
        ], JournalSource::Mushak, 'mushak_invoice', (int) $mushak->getKey(), $actor);
    }

    public function reverseInvoice(CommercialInvoice $invoice, User $actor): void
    {
        $this->reverse(JournalSource::Invoice, 'commercial_invoice', (int) $invoice->getKey(), $actor);
    }

    public function reverseMushak(MushakInvoice $mushak, User $actor): void
    {
        $this->reverse(JournalSource::Mushak, 'mushak_invoice', (int) $mushak->getKey(), $actor);
    }

    /**
     * @return array{account_id: int, debit: string, credit: string, party_id: int|null, memo: string}
     */
    public function line(string $systemKey, string $debit, string $credit, ?int $partyId): array
    {
        return [
            'account_id' => $this->account($systemKey)->id,
            'debit' => $debit,
            'credit' => $credit,
            'party_id' => $partyId,
            'memo' => '',
        ];
    }

    public function account(string $systemKey): Account
    {
        $account = Account::query()->where('system_key', $systemKey)->first();

        if (! $account instanceof Account) {
            throw new AccountingException('The ledger is missing the '.$systemKey.' account.');
        }

        return $account;
    }

    private function reverse(JournalSource $source, string $sourceType, int $sourceId, User $actor): void
    {
        $journal = $this->journals->findPostedFor($source, $sourceType, $sourceId);

        if ($journal instanceof Journal) {
            $this->journals->reverse($journal, $actor);
        }
    }

    private function isZero(Money $money): bool
    {
        return Decimal::of($money->amount())->isZero();
    }

    private function date(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->toDateString();
        }

        return now()->timezone('Asia/Dhaka')->toDateString();
    }
}
