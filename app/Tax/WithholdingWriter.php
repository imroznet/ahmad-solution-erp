<?php

namespace App\Tax;

use App\Documents\DocumentStatus;
use App\Models\AitRecord;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\Party;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Models\VdsRecord;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Records VDS and AIT as their own rows.
 * Amounts are entered by the user. They are not calculated from a VAT percentage
 * and they are not added to output VAT.
 */
final class WithholdingWriter
{
    public function __construct(
        private readonly VatPriceCalculator $amounts,
        private readonly TaxRuleMatcher $matcher,
        private readonly TaxAuthorizer $authorizer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createVds(User $actor, array $input): VdsRecord
    {
        /** @var VdsRecord $record */
        $record = $this->create(VdsRecord::class, TaxType::VDS, 'vds_record_created', $actor, $input);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createAit(User $actor, array $input): AitRecord
    {
        /** @var AitRecord $record */
        $record = $this->create(AitRecord::class, TaxType::AIT, 'ait_record_created', $actor, $input);

        return $record;
    }

    public function voidVds(VdsRecord $record, User $actor, string $reason): VdsRecord
    {
        /** @var VdsRecord $voided */
        $voided = $this->void($record, 'vds_record_voided', $actor, $reason);

        return $voided;
    }

    public function voidAit(AitRecord $record, User $actor, string $reason): AitRecord
    {
        /** @var AitRecord $voided */
        $voided = $this->void($record, 'ait_record_voided', $actor, $reason);

        return $voided;
    }

    /**
     * @param  class-string<VdsRecord|AitRecord>  $class
     * @param  array<string, mixed>  $input
     */
    private function create(string $class, string $taxType, string $event, User $actor, array $input): Model
    {
        return DB::transaction(function () use ($class, $taxType, $event, $actor, $input): Model {
            $direction = is_string($input['direction'] ?? null) ? $input['direction'] : '';

            if (! in_array($direction, WithholdingDirection::all(), true)) {
                throw new TaxRuleException('direction', 'Choose whether the amount was withheld from the company or deducted by the company.');
            }

            $party = $this->party($input, $direction);
            $name = $party instanceof Party
                ? $party->stringAttribute('legal_name')
                : trim(is_string($input['counterparty_name'] ?? null) ? $input['counterparty_name'] : '');

            if ($name === '' || mb_strlen($name) > 200) {
                throw new TaxRuleException('counterparty_name', 'Enter the other party. A manual name does not create a customer or supplier.');
            }

            $date = $this->date($input);
            $version = $this->version($input, $taxType, $date);
            $invoiceId = $this->optionalIssuedInvoice($actor, $input);
            $mushakId = $this->optionalMushak($actor, $input);

            $record = $class::query()->create([
                'direction' => $direction,
                'status' => 'recorded',
                'record_date' => $date,
                'party_id' => $party?->getKey(),
                'counterparty_name' => $name,
                'counterparty_bin' => $party instanceof Party ? $party->nullableAttribute('bin') : $this->limited($input, 'counterparty_bin', 30),
                'counterparty_tin' => $party instanceof Party ? $party->nullableAttribute('tin') : $this->limited($input, 'counterparty_tin', 30),
                'commercial_invoice_id' => $invoiceId,
                'mushak_63_document_id' => $mushakId,
                'taxable_basis' => $this->amounts->money($this->string($input, 'taxable_basis'), 'taxable_basis', 'Taxable basis'),
                'tax_amount' => $this->amounts->money($this->string($input, 'tax_amount'), 'tax_amount', 'Tax amount'),
                'certificate_reference' => $this->limited($input, 'certificate_reference', 120),
                'payment_reference' => $this->limited($input, 'payment_reference', 120),
                'tax_rule_version_id' => $version?->getKey(),
                'tax_rule_snapshot' => $version instanceof TaxRuleVersion ? [
                    'version' => $version->auditSnapshot(),
                    'applied' => false,
                    'statement' => 'The amount was entered by the user. The rule was not used to calculate it, and it is not output VAT.',
                ] : null,
                'notes' => $this->limited($input, 'notes', 5000),
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record($event, $record, newValues: $record->auditSnapshot(), actor: $actor);

            return $record;
        });
    }

    private function void(Model $record, string $event, User $actor, string $reason): Model
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new TaxRuleException('void_reason', 'Enter a void reason of at most 1000 characters.');
        }

        return DB::transaction(function () use ($record, $event, $actor, $reason): Model {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->first();

            if (! $locked instanceof VdsRecord && ! $locked instanceof AitRecord) {
                throw new TaxRuleException('record', 'The record was not found.');
            }

            if ($locked->stringAttribute('status') !== 'recorded') {
                throw new TaxRuleException('status', 'This record is already voided.');
            }

            $locked->status = 'voided';
            $locked->voided_at = now();
            $locked->voided_by = $actor->getKey();
            $locked->void_reason = $reason;
            $locked->save();
            $this->audit->record($event, $locked, newValues: [
                'status' => 'voided',
                'void_reason' => $reason,
            ], actor: $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function party(array $input, string $direction): ?Party
    {
        $partyId = is_numeric($input['party_id'] ?? null) ? (int) $input['party_id'] : 0;

        if ($partyId === 0) {
            return null;
        }

        $party = Party::query()->find($partyId);
        $role = $direction === WithholdingDirection::DEDUCTED_BY_US ? 'supplier' : 'customer';

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole($role)) {
            throw new TaxRuleException('party_id', $role === 'supplier'
                ? 'Choose an active supplier when the company deducted the amount. A manual name does not create a supplier.'
                : 'Choose an active customer when the amount was withheld from the company. A manual name does not create a customer.');
        }

        return $party;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function version(array $input, string $taxType, string $date): ?TaxRuleVersion
    {
        $versionId = is_numeric($input['tax_rule_version_id'] ?? null) ? (int) $input['tax_rule_version_id'] : 0;

        if ($versionId === 0) {
            return null;
        }

        return $this->matcher->approvedFor($taxType, $date, $versionId);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalIssuedInvoice(User $actor, array $input): ?int
    {
        $id = is_numeric($input['commercial_invoice_id'] ?? null) ? (int) $input['commercial_invoice_id'] : 0;

        if ($id === 0) {
            return null;
        }

        $invoice = CommercialInvoice::query()->find($id);

        if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED || ! $this->authorizer->canUseInvoice($actor, $invoice)) {
            throw new TaxRuleException('commercial_invoice_id', 'Link an issued commercial invoice you can view, or leave the link blank.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalMushak(User $actor, array $input): ?int
    {
        $id = is_numeric($input['mushak_63_document_id'] ?? null) ? (int) $input['mushak_63_document_id'] : 0;

        if ($id === 0) {
            return null;
        }

        $document = Mushak63Document::query()->find($id);

        if (! $document instanceof Mushak63Document || ! $this->authorizer->canUseMushak($actor, $document)) {
            throw new TaxRuleException('mushak_63_document_id', 'Link a Mushak 6.3 you can view, or leave the link blank. The link does not add this amount to VAT.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function date(array $input): string
    {
        $value = is_string($input['record_date'] ?? null) ? $input['record_date'] : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException('record_date', 'Enter the record date.');
        }

        $parsed = date_create_immutable($value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new TaxRuleException('record_date', 'Enter a real date.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function string(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? $input[$key] : '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function limited(array $input, string $key, int $limit): ?string
    {
        $value = trim($this->string($input, $key));

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $limit) {
            throw new TaxRuleException($key, 'Use at most '.$limit.' characters.');
        }

        return $value;
    }
}
