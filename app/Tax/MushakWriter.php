<?php

namespace App\Tax;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\Mushak63Event;
use App\Models\Mushak63Line;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * A Mushak 6.3 working paper is a separate document. It copies the commercial
 * invoice and does not change that invoice. Issue stays closed.
 */
final class MushakWriter
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TaxRuleSelector $selector,
        private readonly TaxCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function prepare(CommercialInvoice $invoice, User $actor, array $input): Mushak63Document
    {
        return DB::transaction(function () use ($invoice, $actor, $input): Mushak63Document {
            $locked = CommercialInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();

            if (! $locked instanceof CommercialInvoice) {
                throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice could not be locked.');
            }

            if ($locked->stringAttribute('status') !== DocumentStatus::ISSUED) {
                throw new TaxRuleException('commercial_invoice_id', 'A Mushak working paper can only be prepared from an issued commercial invoice.');
            }

            $open = Mushak63Document::query()
                ->where('commercial_invoice_id', $locked->getKey())
                ->where('status', '!=', TaxDocumentStatus::VOIDED)
                ->lockForUpdate()
                ->exists();

            if ($open) {
                throw new TaxRuleException('commercial_invoice_id', 'This commercial invoice already has a Mushak working paper. Void that paper before preparing another.');
            }

            $classification = TaxText::classification(is_string($input['supply_classification'] ?? null) ? $input['supply_classification'] : '');
            $ruleCode = TaxText::ruleCode(is_string($input['rule_code'] ?? null) ? $input['rule_code'] : null);
            $this->assertClassification($classification, $ruleCode);
            $pinned = $this->pin($input['pinned_tax_rule_version_id'] ?? null, $classification, $ruleCode, $this->invoiceDate($locked), $locked->decimalAttribute('total'));
            $beforeTotal = $locked->decimalAttribute('total');
            $beforeTreatment = $locked->stringAttribute('tax_treatment');
            $document = Mushak63Document::query()->create([
                'commercial_invoice_id' => $locked->getKey(),
                'status' => TaxDocumentStatus::DRAFT,
                'supply_classification' => $classification,
                'rule_code' => $ruleCode,
                'pinned_tax_rule_version_id' => $pinned['id'],
                'pinned_rule_snapshot' => $pinned['snapshot'],
                'party_legal_name' => $locked->stringAttribute('party_legal_name'),
                'party_trading_name' => $locked->getAttribute('party_trading_name'),
                'party_tin' => $locked->getAttribute('party_tin'),
                'party_bin' => $locked->getAttribute('party_bin'),
                'party_vat_registration_number' => $locked->getAttribute('party_vat_registration_number'),
                'quotation_number' => $locked->quotation?->number,
                'invoice_number' => $locked->stringAttribute('number'),
                'challan_numbers' => $this->challanNumbers($locked),
                'invoice_total' => $beforeTotal,
                'currency_code' => $locked->stringAttribute('currency_code') ?: 'BDT',
                'company_snapshot' => $locked->getAttribute('company_snapshot'),
                'internal_notes' => $this->notes($input['internal_notes'] ?? null),
                'created_by' => $actor->getKey(),
            ]);
            $locked->load('lines');
            $position = 1;

            foreach ($locked->lines as $line) {
                Mushak63Line::query()->create([
                    'mushak63_document_id' => $document->getKey(),
                    'commercial_invoice_line_id' => $line->getKey(),
                    'position' => $position,
                    'description' => $line->stringAttribute('description'),
                    'unit' => $line->stringAttribute('unit'),
                    'quantity' => $line->decimalAttribute('quantity'),
                    'unit_price' => $line->decimalAttribute('unit_price'),
                    'line_total' => $line->decimalAttribute('line_total'),
                ]);
                $position++;
            }

            $locked->refresh();

            if ($locked->decimalAttribute('total') !== $beforeTotal || $locked->stringAttribute('tax_treatment') !== $beforeTreatment) {
                throw new TaxRuleException('commercial_invoice_id', 'Preparing a Mushak working paper must not change the commercial invoice.');
            }

            $this->event($document, Mushak63Event::PREPARED, $actor, 'Working paper prepared from '.$locked->displayNumber().'.');
            $this->audit->record('mushak_prepared', $document, newValues: [
                'commercial_invoice_id' => $locked->getKey(),
                'invoice_number' => $locked->stringAttribute('number'),
                'supply_classification' => $classification,
            ], actor: $actor);

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(Mushak63Document $document, User $actor, array $input): Mushak63Document
    {
        return DB::transaction(function () use ($document, $actor, $input): Mushak63Document {
            $locked = Mushak63Document::query()->whereKey($document->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Mushak63Document || $locked->stringAttribute('status') !== TaxDocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft Mushak working paper can be edited.');
            }

            $classification = TaxText::classification(is_string($input['supply_classification'] ?? null) ? $input['supply_classification'] : '');
            $ruleCode = TaxText::ruleCode(is_string($input['rule_code'] ?? null) ? $input['rule_code'] : null);
            $this->assertClassification($classification, $ruleCode);
            $invoice = $locked->invoice;

            if (! $invoice instanceof CommercialInvoice) {
                throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice is missing.');
            }

            $pinned = $this->pin($input['pinned_tax_rule_version_id'] ?? null, $classification, $ruleCode, $this->invoiceDate($invoice), $locked->decimalAttribute('invoice_total'));
            $locked->fill([
                'supply_classification' => $classification,
                'rule_code' => $ruleCode,
                'pinned_tax_rule_version_id' => $pinned['id'],
                'pinned_rule_snapshot' => $pinned['snapshot'],
                'internal_notes' => $this->notes($input['internal_notes'] ?? null),
            ])->save();
            $this->event($locked, Mushak63Event::UPDATED, $actor, 'Working paper updated.');
            $this->audit->record('mushak_updated', $locked, newValues: [
                'supply_classification' => $classification,
                'rule_code' => $ruleCode,
                'pinned_tax_rule_version_id' => $pinned['id'],
            ], actor: $actor);

            return $locked;
        });
    }

    public function issue(Mushak63Document $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $locked = Mushak63Document::query()->whereKey($document->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Mushak63Document) {
                throw new TaxRuleException('status', 'This Mushak working paper could not be locked.');
            }

            $this->event($locked, Mushak63Event::ISSUE_BLOCKED, $actor, 'Issue refused.');
            $this->audit->record('mushak_issue_blocked', $locked, newValues: [
                'blockers' => IssuanceGate::blockers(),
            ], actor: $actor);
        });

        throw new TaxRuleException('status', IssuanceGate::blockers()[0]);
    }

    public function void(Mushak63Document $document, User $actor, string $reason): Mushak63Document
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new TaxRuleException('void_reason', 'A void needs a reason.');
        }

        return DB::transaction(function () use ($document, $actor, $reason): Mushak63Document {
            $locked = Mushak63Document::query()->whereKey($document->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Mushak63Document || $locked->stringAttribute('status') !== TaxDocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft Mushak working paper can be voided.');
            }

            $locked->forceFill([
                'status' => TaxDocumentStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->getKey(),
                'void_reason' => $reason,
            ])->save();
            $this->event($locked, Mushak63Event::VOIDED, $actor, $reason);
            $this->audit->record('mushak_voided', $locked, newValues: ['reason' => $reason], actor: $actor);

            return $locked;
        });
    }

    /**
     * @return array{id: ?int, snapshot: ?array<string, mixed>}
     */
    private function pin(mixed $versionId, string $classification, string $ruleCode, ?string $onDate, string $base): array
    {
        if ($versionId === null || $versionId === '') {
            return ['id' => null, 'snapshot' => null];
        }

        if (! is_numeric($versionId)) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'Choose an approved output VAT rule.');
        }

        $version = TaxRuleVersion::query()->find((int) $versionId);

        if (! $version instanceof TaxRuleVersion || $version->stringAttribute('tax_type') !== TaxType::OUTPUT_VAT || $version->stringAttribute('approval_status') !== TaxRuleStatus::APPROVED) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'Pin an approved output VAT rule. A VDS or AIT rule cannot be pinned here.');
        }

        if ($version->stringAttribute('supply_classification') !== $classification || $version->stringAttribute('rule_code') !== $ruleCode) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'The pinned rule must use the same classification and rule code as this working paper.');
        }

        if ($onDate === null) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'The commercial invoice has no date, so a rule cannot be pinned.');
        }

        $selection = $this->selector->select(TaxType::OUTPUT_VAT, $classification, $ruleCode, $onDate);

        if (! $selection->matched() || (int) $selection->version?->getKey() !== (int) $version->getKey()) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'That rule is not the approved output VAT rule for the commercial invoice date.');
        }

        $assessment = $this->calculator->fromSnapshot(TaxType::OUTPUT_VAT, $base, $version->calculationSnapshot());

        if (! $assessment->matched() || $assessment->snapshot === null) {
            throw new TaxRuleException('pinned_tax_rule_version_id', $assessment->reasons[0] ?? 'The pinned rule cannot be calculated.');
        }

        $snapshot = $assessment->snapshot;
        $snapshot['reasons'] = array_merge($selection->reasons, $assessment->reasons);
        $snapshot['not_an_issued_assessment'] = true;

        return ['id' => (int) $version->getKey(), 'snapshot' => $snapshot];
    }

    private function assertClassification(string $classification, string $ruleCode): void
    {
        if ($classification === '' || strlen($classification) > 160 || TaxText::rejectsClassification($classification)) {
            throw new TaxRuleException('supply_classification', 'Enter the supply classification. A general label such as "standard" or "15%" is not accepted.');
        }

        if (strlen($ruleCode) > 80) {
            throw new TaxRuleException('rule_code', 'The rule code must be 80 characters or fewer.');
        }
    }

    /**
     * @return list<string>
     */
    private function challanNumbers(CommercialInvoice $invoice): array
    {
        $invoice->loadMissing('lines.challanLine.challan');
        $numbers = [];

        foreach ($invoice->lines as $line) {
            $number = $line->challanLine?->challan?->number;

            if (is_string($number) && $number !== '' && ! in_array($number, $numbers, true)) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    private function notes(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $notes = trim($value);

        if ($notes === '') {
            return null;
        }

        if (strlen($notes) > 2000) {
            throw new TaxRuleException('internal_notes', 'Internal notes must be 2000 characters or fewer.');
        }

        return $notes;
    }

    private function invoiceDate(CommercialInvoice $invoice): ?string
    {
        $value = $invoice->getAttribute('invoice_date');

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function event(Mushak63Document $document, string $event, User $actor, string $remarks): void
    {
        Mushak63Event::query()->create([
            'mushak63_document_id' => $document->getKey(),
            'event' => $event,
            'actor_id' => $actor->getKey(),
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }
}
