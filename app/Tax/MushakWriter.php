<?php

namespace App\Tax;

use App\Documents\DocumentStatus;
use App\Documents\DocumentTransaction;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\Mushak63Line;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Saves a Mushak 6.3 draft linked to an issued commercial invoice.
 * Amounts are calculated from the user's percentage. The commercial invoice is not modified.
 */
final class MushakWriter
{
    public function __construct(
        private readonly MushakBuyer $buyers,
        private readonly MushakLines $lines,
        private readonly TaxRuleMatcher $matcher,
        private readonly DocumentTransaction $transactions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): Mushak63Document
    {
        try {
            return $this->transactions->run(function () use ($actor, $input): Mushak63Document {
                $invoice = $this->invoice($input);
                $prepared = $this->lines->prepare($invoice, $input, [], $actor);
                $buyer = $this->buyers->capture($input);
                $treatment = $this->treatment($actor, $input, $this->date($input));
                $document = Mushak63Document::query()->create([
                    'number' => null,
                    'commercial_invoice_id' => $invoice->getKey(),
                    'status' => DocumentStatus::DRAFT,
                    'document_date' => $this->date($input),
                    ...$buyer,
                    'invoice_number_snapshot' => $invoice->stringAttribute('number') !== '' ? $invoice->stringAttribute('number') : null,
                    'invoice_date_snapshot' => $invoice->dateString('invoice_date'),
                    ...$treatment,
                    'vat_notice' => $this->notice(),
                    'form_source_url' => $this->formUrl(),
                    'form_sha256' => $this->formHash(),
                    'currency_code' => Money::DEFAULT_CURRENCY,
                    'customer_notes' => $this->note($input, 'customer_notes'),
                    'internal_notes' => $this->note($input, 'internal_notes'),
                    'created_by' => $actor->getKey(),
                    ...$this->totals($prepared),
                ]);
                $current = $this->insertLines($document, $prepared['lines']);
                $this->audit->record('mushak_63_created', $document, newValues: $this->payload($document, $current), actor: $actor);

                return $document;
            }, 'mushak_63_documents_number_unique');
        } catch (UniqueConstraintViolationException $exception) {
            $this->reraiseOpenInvoice($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(Mushak63Document $document, User $actor, array $input): Mushak63Document
    {
        try {
            return $this->transactions->run(function () use ($document, $actor, $input): Mushak63Document {
                $document = $this->lock($document);
                $this->assertDraft($document);
                $invoice = $document->invoice()->first();

                if (! $invoice instanceof CommercialInvoice || (int) $invoice->getKey() !== $this->invoiceId($input)) {
                    throw new TaxRuleException('commercial_invoice_id', 'The Mushak stays linked to the commercial invoice it was prepared from.');
                }

                $this->assertIssuedInvoice($invoice);
                $previous = [];

                foreach ($document->lines as $line) {
                    $id = $line->getAttribute('commercial_invoice_line_id');

                    if (is_numeric($id)) {
                        $previous[(int) $id] = $line;
                    }
                }

                $prepared = $this->lines->prepare($invoice, $input, $previous, $actor);
                $oldLines = $this->snapshots($document->lines()->get());
                $this->supersede($document, $actor);
                $document->fill([
                    'document_date' => $this->date($input),
                    ...$this->buyers->capture($input),
                    'invoice_number_snapshot' => $invoice->stringAttribute('number') !== '' ? $invoice->stringAttribute('number') : null,
                    'invoice_date_snapshot' => $invoice->dateString('invoice_date'),
                    ...$this->treatment($actor, $input, $this->date($input)),
                    'customer_notes' => $this->note($input, 'customer_notes'),
                    'internal_notes' => $this->note($input, 'internal_notes'),
                    ...$this->totals($prepared),
                ]);
                $document->save();
                $current = $this->insertLines($document, $prepared['lines']);
                $this->audit->record('mushak_63_updated', $document, oldValues: ['lines' => $oldLines], newValues: $this->payload($document, $current), actor: $actor);

                return $document;
            }, 'mushak_63_documents_number_unique');
        } catch (UniqueConstraintViolationException $exception) {
            $this->reraiseOpenInvoice($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(User $actor, array $input): array
    {
        $invoice = $this->invoice($input);
        $prepared = $this->lines->prepare($invoice, $input, $this->previousFor($invoice, $input), $actor);

        $lines = [];

        foreach ($prepared['lines'] as $line) {
            $lines[] = [
                'commercial_invoice_line_id' => $line['commercial_invoice_line_id'],
                'base' => $line['base_amount'],
                'rate' => $line['vat_rate_percent'],
                'vat' => $line['vat_amount'],
                'inclusive' => $line['inclusive_amount'],
                'supplementary_duty' => $line['supplementary_duty_amount'],
                'duties' => $line['duties_amount'],
                'calculated' => $line['vat_amount'] !== null,
                'driver' => $line['calculation_driver'],
            ];
        }

        return [
            'lines' => $lines,
            'base_total' => $prepared['base_total'],
            'vat_total' => $prepared['vat_total'],
            'inclusive_total' => $prepared['inclusive_total'],
            'supplementary_duty_total' => $prepared['supplementary_duty_total'],
            'duties_total' => $prepared['duties_total'],
            'notice' => 'Calculated from the percentage you entered. This does not decide whether that percentage is legally applicable.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, Mushak63Line>
     */
    private function previousFor(CommercialInvoice $invoice, array $input): array
    {
        $documentId = is_numeric($input['document_id'] ?? null) ? (int) $input['document_id'] : 0;

        if ($documentId === 0) {
            return [];
        }

        $document = Mushak63Document::query()->find($documentId);

        if (! $document instanceof Mushak63Document || (int) $document->commercial_invoice_id !== (int) $invoice->getKey()) {
            return [];
        }

        $previous = [];

        foreach ($document->lines as $line) {
            $id = $line->getAttribute('commercial_invoice_line_id');

            if (is_numeric($id)) {
                $previous[(int) $id] = $line;
            }
        }

        return $previous;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function invoice(array $input): CommercialInvoice
    {
        $invoice = CommercialInvoice::query()->find($this->invoiceId($input));

        if (! $invoice instanceof CommercialInvoice) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued commercial invoice. A Mushak 6.3 does not replace it.');
        }

        $this->assertIssuedInvoice($invoice);

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function invoiceId(array $input): int
    {
        $id = is_numeric($input['commercial_invoice_id'] ?? null) ? (int) $input['commercial_invoice_id'] : 0;

        if ($id < 1) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued commercial invoice.');
        }

        return $id;
    }

    private function assertIssuedInvoice(CommercialInvoice $invoice): void
    {
        if ($invoice->stringAttribute('status') !== DocumentStatus::ISSUED || $invoice->stringAttribute('number') === '') {
            throw new TaxRuleException('commercial_invoice_id', 'Prepare Mushak 6.3 from an issued commercial invoice. A draft or voided invoice cannot be used.');
        }

        if ($invoice->stringAttribute('tax_treatment') !== 'excluded') {
            throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice must remain tax-excluded. Mushak 6.3 is a separate document.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function treatment(User $actor, array $input, string $on): array
    {
        $confirmed = filter_var($input['confirm_treatment'] ?? false, FILTER_VALIDATE_BOOL);
        $versionId = is_numeric($input['tax_rule_version_id'] ?? null) ? (int) $input['tax_rule_version_id'] : 0;

        if (! $confirmed || $versionId === 0) {
            return [
                'tax_rule_version_id' => null,
                'tax_rule_snapshot' => null,
                'review_status' => TaxReviewStatus::REVIEW_REQUIRED,
                'treatment_confirmed_at' => null,
                'treatment_confirmed_by' => null,
            ];
        }

        $version = $this->matcher->approvedFor(TaxType::OUTPUT_VAT, $on, $versionId);
        $version->loadMissing('rule');

        return [
            'tax_rule_version_id' => $version->getKey(),
            'tax_rule_snapshot' => [
                'rule' => $version->rule?->auditSnapshot(),
                'version' => $version->auditSnapshot(),
                'applied_rate' => false,
                'statement' => 'The user recorded this rule as the legal treatment. Its stored percentage, if any, was not copied onto the lines.',
            ],
            'review_status' => TaxReviewStatus::TREATMENT_RECORDED,
            'treatment_confirmed_at' => now(),
            'treatment_confirmed_by' => $actor->getKey(),
        ];
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, base_total: string, vat_total: ?string, inclusive_total: ?string, supplementary_duty_total: ?string, duties_total: ?string}  $prepared
     * @return array<string, mixed>
     */
    private function totals(array $prepared): array
    {
        return [
            'base_total' => $prepared['base_total'],
            'vat_total' => $prepared['vat_total'],
            'inclusive_total' => $prepared['inclusive_total'],
            'supplementary_duty_total' => $prepared['supplementary_duty_total'],
            'duties_total' => $prepared['duties_total'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<Mushak63Line>
     */
    private function insertLines(Mushak63Document $document, array $lines): array
    {
        $stored = [];

        foreach ($lines as $line) {
            $stored[] = $document->lines()->create($line);
        }

        return $stored;
    }

    private function supersede(Mushak63Document $document, User $actor): void
    {
        Mushak63Line::query()
            ->where('mushak_63_document_id', $document->getKey())
            ->update([
                'superseded_at' => now(),
                'superseded_by' => $actor->getKey(),
            ]);
    }

    /**
     * @param  iterable<Mushak63Line>  $lines
     * @return list<array<string, mixed>>
     */
    private function snapshots(iterable $lines): array
    {
        $snapshots = [];

        foreach ($lines as $line) {
            $snapshots[] = $line->auditSnapshot();
        }

        return $snapshots;
    }

    /**
     * @param  list<Mushak63Line>  $lines
     * @return array<string, mixed>
     */
    private function payload(Mushak63Document $document, array $lines): array
    {
        return [
            'number' => $document->stringAttribute('number') !== '' ? $document->stringAttribute('number') : null,
            'status' => $document->stringAttribute('status'),
            'review_status' => $document->stringAttribute('review_status'),
            'buyer_source' => $document->stringAttribute('buyer_source'),
            'buyer_legal_name' => $document->stringAttribute('buyer_legal_name'),
            'buyer_bin' => $this->text($document, 'buyer_bin'),
            'base_total' => $document->decimalAttribute('base_total'),
            'vat_total' => $document->nullableDecimal('vat_total'),
            'inclusive_total' => $document->nullableDecimal('inclusive_total'),
            'supplementary_duty_total' => $document->nullableDecimal('supplementary_duty_total'),
            'duties_total' => $document->nullableDecimal('duties_total'),
            'tax_rule_version_id' => $document->getAttribute('tax_rule_version_id'),
            'lines' => $this->snapshots($lines),
        ];
    }

    private function lock(Mushak63Document $document): Mushak63Document
    {
        $locked = Mushak63Document::query()->whereKey($document->getKey())->lockForUpdate()->first();

        if (! $locked instanceof Mushak63Document) {
            throw new TaxRuleException('mushak', 'The Mushak 6.3 was not found.');
        }

        return $locked;
    }

    private function assertDraft(Mushak63Document $document): void
    {
        if ($document->stringAttribute('status') !== DocumentStatus::DRAFT) {
            throw new TaxRuleException('status', 'Only a draft Mushak 6.3 can be edited. An issued document stays as it was.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function date(array $input): string
    {
        $value = is_string($input['document_date'] ?? null) ? $input['document_date'] : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException('document_date', 'Enter the document date.');
        }

        $parsed = date_create_immutable($value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new TaxRuleException('document_date', 'Enter a real document date.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function note(array $input, string $key): ?string
    {
        $value = trim(is_string($input[$key] ?? null) ? $input[$key] : '');

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 5000) {
            throw new TaxRuleException($key, 'Use at most 5000 characters.');
        }

        return $value;
    }

    private function notice(): string
    {
        $notice = config('asl.mushak.vat_notice');

        return is_string($notice) && $notice !== ''
            ? $notice
            : 'The VAT percentage on each line was entered by an authorised user. This system calculates amounts from that percentage and does not decide whether it is legally applicable.';
    }

    private function formUrl(): string
    {
        $url = config('asl.mushak.form_source_url');

        return is_string($url) ? $url : '';
    }

    private function formHash(): string
    {
        $hash = config('asl.mushak.form_sha256');

        return is_string($hash) ? $hash : '';
    }

    private function reraiseOpenInvoice(UniqueConstraintViolationException $exception): never
    {
        if (str_contains($exception->getMessage(), 'open_invoice')) {
            throw new TaxRuleException('commercial_invoice_id', 'This commercial invoice already has a draft or issued Mushak 6.3. Void that document before preparing another.');
        }

        throw $exception;
    }

    private function text(Mushak63Document $document, string $key): ?string
    {
        $value = $document->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
