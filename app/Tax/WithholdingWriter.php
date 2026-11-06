<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\AitRecord;
use App\Models\AitRecordEvent;
use App\Models\CommercialInvoice;
use App\Models\Party;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Models\VdsRecord;
use App\Models\VdsRecordEvent;
use App\Models\WithholdingRecord;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VDS and AIT are stored in separate tables. Neither amount is added to output VAT.
 * A certificate is not issued.
 */
final class WithholdingWriter
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TaxRuleSelector $selector,
        private readonly TaxCalculator $calculator,
        private readonly CompanySnapshot $companies,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(string $taxType, User $actor, array $input): WithholdingRecord
    {
        $attributes = $this->attributes($taxType, $input);

        return DB::transaction(function () use ($taxType, $actor, $attributes): WithholdingRecord {
            $record = $this->newRecord($taxType);
            $record->fill([
                ...$attributes,
                'status' => TaxDocumentStatus::DRAFT,
                'company_snapshot' => $this->companies->capture(),
                'created_by' => $actor->getKey(),
            ])->save();
            $this->event($record, 'prepared', $actor, TaxType::label($taxType).' record prepared. This is not a certificate.');
            $this->audit->record($taxType.'_recorded', $record, newValues: $this->auditValues($record), actor: $actor);

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(WithholdingRecord $record, User $actor, array $input): WithholdingRecord
    {
        $attributes = $this->attributes($record->taxType(), $input);

        return DB::transaction(function () use ($record, $actor, $attributes): WithholdingRecord {
            $locked = $this->lock($record);

            if ($locked->stringAttribute('status') !== TaxDocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft record can be edited.');
            }

            $locked->fill($attributes)->save();
            $this->event($locked, 'updated', $actor, 'Record updated.');
            $this->audit->record($locked->taxType().'_updated', $locked, newValues: $this->auditValues($locked), actor: $actor);

            return $locked;
        });
    }

    public function void(WithholdingRecord $record, User $actor, string $reason): WithholdingRecord
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new TaxRuleException('void_reason', 'A void needs a reason.');
        }

        return DB::transaction(function () use ($record, $actor, $reason): WithholdingRecord {
            $locked = $this->lock($record);

            if ($locked->stringAttribute('status') !== TaxDocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft record can be voided.');
            }

            $locked->forceFill([
                'status' => TaxDocumentStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->getKey(),
                'void_reason' => $reason,
            ])->save();
            $this->event($locked, 'voided', $actor, $reason);
            $this->audit->record($locked->taxType().'_voided', $locked, newValues: ['reason' => $reason], actor: $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(string $taxType, array $input): array
    {
        if (! in_array($taxType, [TaxType::VDS, TaxType::AIT], true)) {
            throw new TaxRuleException('tax_type', 'Withholding records are VDS or AIT, not output VAT.');
        }

        $direction = is_string($input['direction'] ?? null) ? $input['direction'] : '';

        if (! in_array($direction, TaxDirection::all(), true)) {
            throw new TaxRuleException('direction', 'Choose whether the tax was withheld from this company or deducted by this company.');
        }

        $partyId = $input['party_id'] ?? null;

        if (! is_numeric($partyId)) {
            throw new TaxRuleException('party_id', 'Choose the other party.');
        }

        $party = Party::query()->find((int) $partyId);

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active') {
            throw new TaxRuleException('party_id', 'Choose an active party.');
        }

        $requiredRole = $direction === TaxDirection::WITHHELD_FROM_US ? 'customer' : 'supplier';

        if (! $party->hasRole($requiredRole)) {
            throw new TaxRuleException('party_id', $direction === TaxDirection::WITHHELD_FROM_US
                ? 'A customer withholds from this company. Choose a party with the customer role.'
                : 'This company deducts from a supplier. Choose a party with the supplier role.');
        }

        $classification = TaxText::classification(is_string($input['supply_classification'] ?? null) ? $input['supply_classification'] : '');
        $ruleCode = TaxText::ruleCode(is_string($input['rule_code'] ?? null) ? $input['rule_code'] : null);

        if ($classification === '' || strlen($classification) > 160 || TaxText::rejectsClassification($classification)) {
            throw new TaxRuleException('supply_classification', 'Enter the classification from the legal source. Do not use a general label such as "standard" or "15%".');
        }

        $basisInput = $input['basis_amount'] ?? null;
        $basisText = is_string($basisInput) || is_int($basisInput) ? trim((string) $basisInput) : '';

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $basisText)) {
            throw new TaxRuleException('basis_amount', 'Enter the base as taka and poisha.');
        }

        $basis = Decimal::of($basisText)->toScale(2)->__toString();

        if (Decimal::of($basis)->compare(Decimal::of('0')) <= 0) {
            throw new TaxRuleException('basis_amount', 'The base must be greater than zero. A record is not created for every receipt or payment.');
        }

        $date = is_string($input['record_date'] ?? null) ? $input['record_date'] : '';
        $parsed = Carbon::createFromFormat('!Y-m-d', $date, 'Asia/Dhaka');

        if (! $parsed instanceof Carbon || $parsed->toDateString() !== $date) {
            throw new TaxRuleException('record_date', 'Enter the record date as YYYY-MM-DD.');
        }

        $invoiceId = $input['commercial_invoice_id'] ?? null;
        $invoiceKey = null;

        if ($invoiceId !== null && $invoiceId !== '') {
            if (! is_numeric($invoiceId)) {
                throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice is not valid.');
            }

            $invoice = CommercialInvoice::query()->find((int) $invoiceId);

            if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
                throw new TaxRuleException('commercial_invoice_id', 'Link an issued commercial invoice, or leave the link empty.');
            }

            $invoiceKey = (int) $invoice->getKey();
        }

        $pinned = $this->pin($taxType, $input['pinned_tax_rule_version_id'] ?? null, $classification, $ruleCode, $date, $basis);

        return [
            'direction' => $direction,
            'party_id' => (int) $party->getKey(),
            'commercial_invoice_id' => $invoiceKey,
            'underlying_reference' => $this->shortText($input['underlying_reference'] ?? null, 'underlying_reference', 200),
            'supply_classification' => $classification,
            'rule_code' => $ruleCode,
            'basis_amount' => $basis,
            'currency_code' => 'BDT',
            'record_date' => $date,
            'pinned_tax_rule_version_id' => $pinned['id'],
            'pinned_rule_snapshot' => $pinned['snapshot'],
            'certificate_reference' => $this->shortText($input['certificate_reference'] ?? null, 'certificate_reference', 120),
            'treasury_reference' => $this->shortText($input['treasury_reference'] ?? null, 'treasury_reference', 120),
            'party_legal_name' => $party->stringAttribute('legal_name'),
            'party_tin' => $party->getAttribute('tin'),
            'party_bin' => $party->getAttribute('bin'),
            'internal_notes' => $this->shortText($input['internal_notes'] ?? null, 'internal_notes', 2000),
        ];
    }

    /**
     * @return array{id: ?int, snapshot: ?array<string, mixed>}
     */
    private function pin(string $taxType, mixed $versionId, string $classification, string $ruleCode, string $onDate, string $base): array
    {
        if ($versionId === null || $versionId === '') {
            return ['id' => null, 'snapshot' => null];
        }

        if (! is_numeric($versionId)) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'Choose an approved rule of this tax type.');
        }

        $version = TaxRuleVersion::query()->find((int) $versionId);

        if (! $version instanceof TaxRuleVersion || $version->stringAttribute('tax_type') !== $taxType || $version->stringAttribute('approval_status') !== TaxRuleStatus::APPROVED) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'Pin an approved '.TaxType::label($taxType).' rule. Output VAT is a different tax.');
        }

        if ($version->stringAttribute('supply_classification') !== $classification || $version->stringAttribute('rule_code') !== $ruleCode) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'The pinned rule must use the same classification and rule code.');
        }

        $selection = $this->selector->select($taxType, $classification, $ruleCode, $onDate);

        if (! $selection->matched() || (int) $selection->version?->getKey() !== (int) $version->getKey()) {
            throw new TaxRuleException('pinned_tax_rule_version_id', 'That rule is not the approved rule for this date.');
        }

        $assessment = $this->calculator->fromSnapshot($taxType, $base, $version->calculationSnapshot());

        if (! $assessment->matched() || $assessment->snapshot === null) {
            throw new TaxRuleException('pinned_tax_rule_version_id', $assessment->reasons[0] ?? 'The pinned rule cannot be calculated.');
        }

        $snapshot = $assessment->snapshot;
        $snapshot['reasons'] = array_merge($selection->reasons, $assessment->reasons);
        $snapshot['not_a_certificate'] = true;

        return ['id' => (int) $version->getKey(), 'snapshot' => $snapshot];
    }

    private function newRecord(string $taxType): WithholdingRecord
    {
        return $taxType === TaxType::VDS ? new VdsRecord : new AitRecord;
    }

    private function lock(WithholdingRecord $record): WithholdingRecord
    {
        $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->first();

        if (! $locked instanceof WithholdingRecord) {
            throw new TaxRuleException('status', 'This record could not be locked.');
        }

        return $locked;
    }

    private function event(WithholdingRecord $record, string $event, User $actor, string $remarks): void
    {
        $payload = [
            'event' => $event,
            'actor_id' => $actor->getKey(),
            'remarks' => $remarks,
            'created_at' => now(),
        ];

        if ($record instanceof VdsRecord) {
            VdsRecordEvent::query()->create(['vds_record_id' => $record->getKey(), ...$payload]);

            return;
        }

        AitRecordEvent::query()->create(['ait_record_id' => $record->getKey(), ...$payload]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(WithholdingRecord $record): array
    {
        return [
            'tax_type' => $record->taxType(),
            'direction' => $record->stringAttribute('direction'),
            'party_id' => $record->getAttribute('party_id'),
            'basis_amount' => $record->decimalAttribute('basis_amount'),
            'preview_amount' => $this->previewAmount($record),
            'record_date' => $record->dateString('record_date'),
        ];
    }

    private function previewAmount(WithholdingRecord $record): mixed
    {
        $snapshot = $record->getAttribute('pinned_rule_snapshot');

        if (! is_array($snapshot)) {
            return null;
        }

        return $snapshot['amount'] ?? null;
    }

    private function shortText(mixed $value, string $field, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new TaxRuleException($field, 'Enter text.');
        }

        $text = trim($value);

        if ($text === '') {
            return null;
        }

        if (strlen($text) > $max) {
            throw new TaxRuleException($field, 'Use '.$max.' characters or fewer.');
        }

        return $text;
    }
}
