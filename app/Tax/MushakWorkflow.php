<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\MushakDocument;
use App\Models\MushakDocumentEvent;
use App\Models\Party;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Issue freezes the company, the buyer, and the typed figures.
 * Void and a treatment record do not change those figures.
 */
final class MushakWorkflow
{
    public function __construct(
        private readonly TaxText $text,
        private readonly MushakFigures $figures,
        private readonly BuyerSnapshot $buyers,
        private readonly CompanySnapshot $companies,
        private readonly MushakNumberAllocator $numbers,
        private readonly TaxRuleCalendar $calendar,
        private readonly AuditLogger $audit,
    ) {}

    public function issue(MushakDocument $document, User $actor): MushakDocument
    {
        return DB::transaction(function () use ($document, $actor): MushakDocument {
            $locked = $this->lock($document);

            if ($locked->stringAttribute('status') !== DocumentStatus::DRAFT) {
                throw new TaxRuleException('mushak', 'Only a draft Mushak 6.3 can be issued.');
            }

            $company = $this->companies->capture();

            if ($company['bin'] === null || trim($company['bin']) === '') {
                throw new TaxRuleException('seller_bin', 'The company profile has no BIN. Mushak 6.3 cannot be issued until the registered BIN is saved on the company profile.');
            }

            if ($company['address_lines'] === []) {
                throw new TaxRuleException('seller_issue_address', 'The company profile has no registered or corporate address. Mushak 6.3 cannot be issued until that address is saved on the company profile.');
            }

            $this->refreshBuyer($locked);
            $this->requireText($locked, 'buyer_name', 'buyer_name', 'Enter the buyer name. A buyer BIN is not invented.');
            $this->requireText($locked, 'destination', 'destination', 'Enter the place where the supply is delivered.');
            $this->requireText($locked, 'responsible_name', 'responsible_name', 'Enter the name of the responsible person.');
            $this->requireText($locked, 'responsible_designation', 'responsible_designation', 'Enter the designation of the responsible person.');
            $invoice = $this->issuedInvoice($locked);
            $lines = $this->figureLines($locked);
            $totals = [
                'total_value' => $locked->nullableDecimal('total_value'),
                'total_supplementary_duty' => $locked->nullableDecimal('total_supplementary_duty'),
                'total_vat' => $locked->nullableDecimal('total_vat'),
                'total_inclusive' => $locked->nullableDecimal('total_inclusive'),
            ];
            $this->figures->assertIssue($lines, $totals);
            $now = now()->timezone('Asia/Dhaka');
            $locked->fill([
                'issue_date' => $this->calendarDate($locked, 'issue_date') ?? $now->toDateString(),
                'issue_time' => $locked->stringAttribute('issue_time') !== '' ? $locked->stringAttribute('issue_time') : $now->format('H:i'),
                'number' => $this->numbers->allocate(),
                'company_snapshot' => $company,
                'customer_snapshot' => $this->customerSnapshot($invoice),
                'source_references' => $this->references($invoice),
                'manual_vat_notice' => MushakFieldMap::MANUAL_NOTICE,
                'tax_review_status' => TaxReviewStatus::REVIEW_REQUIRED,
                'status' => DocumentStatus::ISSUED,
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $locked->save();
            $this->event($locked, MushakDocumentEvent::ISSUED, $actor, null);
            $this->audit->record('mushak_issued', $locked, [], [
                'number' => $locked->stringAttribute('number'),
                'tax_review_status' => TaxReviewStatus::REVIEW_REQUIRED,
                'rate_determined_by_erp' => false,
            ], $actor);

            return $locked;
        });
    }

    public function void(MushakDocument $document, User $actor, string $reason): MushakDocument
    {
        return DB::transaction(function () use ($document, $actor, $reason): MushakDocument {
            $locked = $this->lock($document);
            $status = $locked->stringAttribute('status');

            if ($status === DocumentStatus::VOIDED) {
                throw new TaxRuleException('mushak', 'This Mushak 6.3 is already voided.');
            }

            if (! in_array($status, [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true)) {
                throw new TaxRuleException('mushak', 'This Mushak 6.3 cannot be voided.');
            }

            $note = $this->text->required($reason, 'void_reason', 1000);
            $locked->status = DocumentStatus::VOIDED;
            $locked->void_reason = $note;
            $locked->voided_at = now();
            $locked->voided_by = $actor->id;
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->event($locked, MushakDocumentEvent::VOIDED, $actor, $note);
            $this->audit->record('mushak_voided', $locked, [], [
                'number' => $locked->nullableAttribute('number'),
                'status' => DocumentStatus::VOIDED,
            ], $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function recordTreatment(MushakDocument $document, User $actor, array $input): MushakDocument
    {
        return DB::transaction(function () use ($document, $actor, $input): MushakDocument {
            $locked = $this->lock($document);

            if ($locked->stringAttribute('status') !== DocumentStatus::ISSUED) {
                throw new TaxRuleException('mushak', 'Record a treatment on an issued Mushak 6.3.');
            }

            if ($locked->stringAttribute('tax_review_status') !== TaxReviewStatus::REVIEW_REQUIRED) {
                throw new TaxRuleException('mushak', 'A treatment is already recorded. The typed VAT figures stay as they were.');
            }

            if (($input['confirmed'] ?? null) !== '1') {
                throw new TaxRuleException('confirmed', 'Confirm that you are recording a treatment. This does not change the typed VAT figures.');
            }

            $note = $this->text->required($input['treatment_note'] ?? null, 'treatment_note', 2000);
            $rule = $this->treatmentRule($locked, $input['tax_rule_id'] ?? null);
            $beforeVat = $locked->nullableDecimal('total_vat');
            $locked->fill([
                'tax_review_status' => TaxReviewStatus::TREATMENT_RECORDED,
                'treatment_rule_id' => $rule?->id,
                'treatment_rule_snapshot' => $rule?->snapshot(),
                'treatment_note' => $note,
                'treatment_recorded_by' => $actor->id,
                'treatment_recorded_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $locked->save();

            if ($locked->nullableDecimal('total_vat') !== $beforeVat) {
                throw new TaxRuleException('total_vat', 'Recording a treatment must not change the typed VAT amount.');
            }

            $this->event($locked, MushakDocumentEvent::TREATMENT_RECORDED, $actor, $note);
            $this->audit->record('mushak_treatment_recorded', $locked, [], [
                'number' => $locked->stringAttribute('number'),
                'tax_rule_id' => $rule?->id,
                'rate_determined_by_erp' => false,
            ], $actor);

            return $locked;
        });
    }

    private function refreshBuyer(MushakDocument $document): void
    {
        if ($document->stringAttribute('buyer_source') !== BuyerSource::PARTY) {
            return;
        }

        $party = Party::query()->find($document->party_id);

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole('customer')) {
            throw new TaxRuleException('party_id', 'The selected customer is no longer an active customer.');
        }

        $document->fill($this->buyers->fromParty($party));
    }

    private function requireText(MushakDocument $document, string $attribute, string $field, string $message): void
    {
        $value = $document->getAttribute($attribute);

        if (! is_string($value) || trim($value) === '') {
            throw new TaxRuleException($field, $message);
        }
    }

    private function issuedInvoice(MushakDocument $document): ?CommercialInvoice
    {
        if ($document->commercial_invoice_id === null) {
            return null;
        }

        $invoice = CommercialInvoice::query()->find($document->commercial_invoice_id);

        if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
            throw new TaxRuleException('commercial_invoice_id', 'The linked commercial invoice must still be issued.');
        }

        return $invoice;
    }

    /**
     * @return list<array<string, ?string>>
     */
    private function figureLines(MushakDocument $document): array
    {
        $lines = [];

        foreach ($document->lines()->orderBy('position')->get() as $line) {
            $lines[] = [
                'description' => $line->text('description'),
                'unit' => $line->text('unit') !== '' ? $line->text('unit') : null,
                'quantity' => $line->nullableDecimal('quantity'),
                'unit_price' => $line->nullableDecimal('unit_price'),
                'line_value' => $line->nullableDecimal('line_value'),
                'supplementary_duty' => $line->nullableDecimal('supplementary_duty'),
                'vat_amount' => $line->nullableDecimal('vat_amount'),
                'inclusive_value' => $line->nullableDecimal('inclusive_value'),
            ];
        }

        return $lines;
    }

    private function treatmentRule(MushakDocument $document, mixed $id): ?TaxRule
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! is_numeric($id)) {
            throw new TaxRuleException('tax_rule_id', 'Choose an approved output VAT rule, or leave it blank.');
        }

        $rule = TaxRule::query()->find((int) $id);

        if (! $rule instanceof TaxRule
            || $rule->stringAttribute('status') !== TaxRuleStatus::APPROVED
            || $rule->stringAttribute('tax_type') !== TaxType::OUTPUT_VAT) {
            throw new TaxRuleException('tax_rule_id', 'Choose an approved output VAT rule. Its rate is not copied onto this document.');
        }

        $issueDate = $this->calendarDate($document, 'issue_date');

        if ($issueDate === null) {
            throw new TaxRuleException('issue_date', 'The issue date is missing.');
        }

        $start = $this->ruleDate($rule, 'effective_from');

        if ($start === null || ! $this->calendar->contains($start, $this->ruleDate($rule, 'effective_to'), $issueDate)) {
            throw new TaxRuleException('tax_rule_id', 'That rule does not cover the issue date. Both the start and the end are inclusive.');
        }

        return $rule;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function customerSnapshot(?CommercialInvoice $invoice): ?array
    {
        if (! $invoice instanceof CommercialInvoice) {
            return null;
        }

        return [
            'party_legal_name' => $invoice->stringAttribute('party_legal_name'),
            'party_trading_name' => $this->optionalText($invoice, 'party_trading_name'),
            'party_tin' => $this->optionalText($invoice, 'party_tin'),
            'party_bin' => $this->optionalText($invoice, 'party_bin'),
            'party_email' => $this->optionalText($invoice, 'party_email'),
            'party_phone' => $this->optionalText($invoice, 'party_phone'),
            'billing_address' => $invoice->getAttribute('billing_address'),
            'shipping_address' => $invoice->getAttribute('shipping_address'),
        ];
    }

    private function optionalText(CommercialInvoice $invoice, string $key): ?string
    {
        $value = $invoice->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function references(?CommercialInvoice $invoice): array
    {
        if (! $invoice instanceof CommercialInvoice) {
            return ['commercial_invoice_number' => null, 'quotation_number' => null, 'challan_numbers' => []];
        }

        $invoice->loadMissing(['quotation', 'lines.challanLine.challan']);
        $challans = [];

        foreach ($invoice->lines as $line) {
            $number = $line->challanLine?->challan?->number;

            if (is_string($number) && $number !== '') {
                $challans[$number] = $number;
            }
        }

        return [
            'commercial_invoice_id' => $invoice->id,
            'commercial_invoice_number' => $this->optionalText($invoice, 'number'),
            'quotation_id' => $invoice->quotation_id,
            'quotation_number' => $invoice->quotation?->number,
            'challan_numbers' => array_values($challans),
        ];
    }

    private function calendarDate(MushakDocument $document, string $key): ?string
    {
        return $this->dateText($document->getAttribute($key));
    }

    private function ruleDate(TaxRule $rule, string $key): ?string
    {
        return $this->dateText($rule->getAttribute($key));
    }

    private function dateText(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value)) {
            return null;
        }

        $value = substr($value, 0, 10);

        return $value === '' ? null : $value;
    }

    private function lock(MushakDocument $document): MushakDocument
    {
        $locked = MushakDocument::query()->whereKey($document->id)->lockForUpdate()->first();

        if (! $locked instanceof MushakDocument) {
            throw new TaxRuleException('mushak', 'This Mushak 6.3 could not be locked.');
        }

        return $locked;
    }

    private function event(MushakDocument $document, string $event, User $actor, ?string $remarks): void
    {
        MushakDocumentEvent::query()->create([
            'mushak_document_id' => $document->id,
            'event' => $event,
            'actor_id' => $actor->id,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }
}
