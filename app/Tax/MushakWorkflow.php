<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Documents\DocumentTransaction;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\Mushak63Event;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Issue freezes the draft. Void keeps the row and does not edit the amounts.
 * A statutory credit or debit note is not created here.
 */
final class MushakWorkflow
{
    public function __construct(
        private readonly VatPriceCalculator $calculator,
        private readonly MushakNumberAllocator $numbers,
        private readonly CompanySnapshot $companies,
        private readonly DocumentTransaction $transactions,
        private readonly AuditLogger $audit,
    ) {}

    public function issue(Mushak63Document $document, User $actor): Mushak63Document
    {
        /** @var Mushak63Document $issued */
        $issued = $this->transactions->run(function () use ($document, $actor): Mushak63Document {
            $document = $this->lock($document);

            if ($document->stringAttribute('status') !== DocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft Mushak 6.3 can be issued.');
            }

            $invoice = CommercialInvoice::query()->whereKey($document->commercial_invoice_id)->lockForUpdate()->first();

            if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
                throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice must still be issued. Voiding it does not change a Mushak that is already issued, and a draft invoice cannot be issued as Mushak 6.3.');
            }

            $document->load('lines');

            if ($document->lines->isEmpty()) {
                throw new TaxRuleException('lines', 'Add the invoice lines before issuing.');
            }

            foreach ($document->lines as $line) {
                $this->assertCalculated($line->stringAttribute('calculation_driver'), $line->decimalAttribute('base_amount'), $line->nullableDecimal('vat_rate_percent'), $line->nullableDecimal('vat_amount'), $line->nullableDecimal('inclusive_amount'));
            }

            $company = $this->companies->capture();

            if (! is_string($company['bin']) || $company['bin'] === '') {
                throw new TaxRuleException('company', 'Add the company BIN to the company profile before issuing Mushak 6.3. A BIN is not invented.');
            }

            $document->fill([
                'company_snapshot' => $company,
                'invoice_number_snapshot' => $invoice->stringAttribute('number'),
                'invoice_date_snapshot' => $invoice->dateString('invoice_date'),
            ]);
            $document->number = $this->numbers->allocate();
            $document->status = DocumentStatus::ISSUED;
            $document->issued_at = now();
            $document->issued_by = $actor->getKey();
            $document->save();
            $document->events()->create([
                'event' => Mushak63Event::ISSUED,
                'actor_id' => $actor->getKey(),
                'created_at' => now(),
            ]);
            $this->audit->record('mushak_63_issued', $document, newValues: [
                'number' => $document->stringAttribute('number'),
                'review_status' => $document->stringAttribute('review_status'),
                'base_total' => $document->decimalAttribute('base_total'),
                'vat_total' => $document->nullableDecimal('vat_total'),
                'inclusive_total' => $document->nullableDecimal('inclusive_total'),
                'company_bin' => $company['bin'],
            ], actor: $actor);

            return $document;
        }, 'mushak_63_documents_number_unique');

        return $issued;
    }

    public function void(Mushak63Document $document, User $actor, string $reason): Mushak63Document
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new TaxRuleException('void_reason', 'Enter a void reason of at most 1000 characters. The issued amounts stay on the document.');
        }

        return $this->performVoid($document, $actor, $reason);
    }

    private function performVoid(Mushak63Document $document, User $actor, string $reason): Mushak63Document
    {
        /** @var Mushak63Document $voided */
        $voided = $this->transactions->run(function () use ($document, $actor, $reason): Mushak63Document {
            $document = $this->lock($document);

            if (! in_array($document->stringAttribute('status'), [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true)) {
                throw new TaxRuleException('status', 'This Mushak 6.3 is already voided.');
            }

            $document->status = DocumentStatus::VOIDED;
            $document->voided_at = now();
            $document->voided_by = $actor->getKey();
            $document->void_reason = $reason;
            $document->save();
            $document->events()->create([
                'event' => Mushak63Event::VOIDED,
                'actor_id' => $actor->getKey(),
                'remarks' => $reason,
                'created_at' => now(),
            ]);
            $this->audit->record('mushak_63_voided', $document, newValues: [
                'number' => $document->stringAttribute('number') !== '' ? $document->stringAttribute('number') : null,
                'void_reason' => $reason,
            ], actor: $actor);

            return $document;
        }, 'mushak_63_documents_number_unique');

        return $voided;
    }

    private function assertCalculated(string $driver, string $base, ?string $rate, ?string $vat, ?string $inclusive): void
    {
        if ($rate === null || $vat === null || $inclusive === null) {
            throw new TaxRuleException('lines', 'Enter a VAT percentage on every line before issuing. A blank percentage is not calculated. Type 0 if the VAT amount is zero.');
        }

        $again = $driver === 'inclusive'
            ? $this->calculator->reverse($inclusive, $rate)
            : $this->calculator->forward($base, $rate);

        if ($again->base !== $base || $again->vat !== $vat || $again->inclusive !== $inclusive || $again->rate !== $rate) {
            throw new TaxRuleException('lines', 'Save the draft again so the VAT amounts match the entered percentage.');
        }
    }

    private function lock(Mushak63Document $document): Mushak63Document
    {
        $locked = Mushak63Document::query()->whereKey($document->getKey())->lockForUpdate()->first();

        if (! $locked instanceof Mushak63Document) {
            throw new TaxRuleException('mushak', 'The Mushak 6.3 was not found.');
        }

        return $locked;
    }
}
