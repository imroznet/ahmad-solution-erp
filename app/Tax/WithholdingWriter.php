<?php

namespace App\Tax;

use App\Documents\DocumentStatus;
use App\Models\AitRecord;
use App\Models\CommercialInvoice;
use App\Models\MushakDocument;
use App\Models\Party;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\VdsRecord;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Records VDS or AIT as its own row. The deducted amount is typed.
 * It is never added to a Mushak VAT total.
 */
final class WithholdingWriter
{
    public function __construct(
        private readonly TaxText $text,
        private readonly TaxRuleCalendar $calendar,
        private readonly MushakAuthorizer $mushak,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(string $kind, User $actor, array $input): VdsRecord|AitRecord
    {
        $taxType = $this->taxType($kind);

        return DB::transaction(function () use ($kind, $taxType, $actor, $input): VdsRecord|AitRecord {
            $direction = $input['direction'] ?? null;

            if (! is_string($direction) || ! WithholdingDirection::isKnown($direction)) {
                throw new TaxRuleException('direction', 'Choose whether the tax was withheld from us or deducted by us.');
            }

            $source = $input['counterparty_source'] ?? null;

            if (! is_string($source) || ! isset(BuyerSource::options()[$source])) {
                throw new TaxRuleException('counterparty_source', 'Choose a party or type the counterparty for this record only.');
            }

            $counterparty = $source === BuyerSource::PARTY
                ? $this->party($input, $direction)
                : $this->manual($input);
            $amount = TaxAmount::money($input['deducted_amount'] ?? null, 'deducted_amount');

            if ($amount === null) {
                throw new TaxRuleException('deducted_amount', 'Enter the deducted amount. It is not calculated from a rate.');
            }

            $date = TaxAmount::date($input['record_date'] ?? null, 'record_date', true);
            $rule = $this->rule($input['tax_rule_id'] ?? null, $taxType, (string) $date);
            $confirmed = ($input['confirmed'] ?? null) === '1';
            $mushak = $this->mushakDocument($actor, $input['mushak_document_id'] ?? null);
            $invoice = $this->invoice($input['commercial_invoice_id'] ?? null);
            $attributes = [
                'direction' => $direction,
                'status' => 'recorded',
                'counterparty_source' => $source,
                ...$counterparty,
                'mushak_document_id' => $mushak?->id,
                'commercial_invoice_id' => $invoice?->id,
                'underlying_reference' => $this->text->required($input['underlying_reference'] ?? null, 'underlying_reference', 200),
                'basis_amount' => TaxAmount::money($input['basis_amount'] ?? null, 'basis_amount'),
                'deducted_amount' => $amount,
                'record_date' => $date,
                'certificate_number' => $this->text->nullable($input, 'certificate_number', 80),
                'treasury_reference' => $this->text->nullable($input, 'treasury_reference', 160),
                'tax_rule_id' => $rule?->id,
                'rule_snapshot' => $rule?->snapshot(),
                'tax_review_status' => $confirmed && $rule instanceof TaxRule
                    ? TaxReviewStatus::TREATMENT_RECORDED
                    : TaxReviewStatus::REVIEW_REQUIRED,
                'notes' => $this->text->nullable($input, 'notes', 2000),
                'internal_notes' => $this->text->nullable($input, 'internal_notes', 2000),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ];
            $record = $kind === 'vds'
                ? VdsRecord::query()->create($attributes)
                : AitRecord::query()->create($attributes);
            $this->audit->record($kind.'_record_created', $record, [], [
                'direction' => $direction,
                'deducted_amount' => $amount,
                'tax_review_status' => $attributes['tax_review_status'],
                'added_to_output_vat' => false,
            ], $actor);

            return $record;
        });
    }

    public function void(string $kind, Model $record, User $actor, string $reason): Model
    {
        if (! $record instanceof VdsRecord && ! $record instanceof AitRecord) {
            throw new TaxRuleException('record', 'Choose a VDS or AIT record.');
        }

        return DB::transaction(function () use ($kind, $record, $actor, $reason): Model {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Model) {
                throw new TaxRuleException('record', 'This record could not be locked.');
            }

            if ($locked->getAttribute('status') === 'voided') {
                throw new TaxRuleException('record', 'This record is already voided.');
            }

            $note = $this->text->required($reason, 'void_reason', 1000);
            $locked->setAttribute('status', 'voided');
            $locked->setAttribute('void_reason', $note);
            $locked->setAttribute('voided_at', now());
            $locked->setAttribute('voided_by', $actor->id);
            $locked->setAttribute('updated_by', $actor->id);
            $locked->save();
            $this->audit->record($kind.'_record_voided', $locked, [], [
                'status' => 'voided',
            ], $actor);

            return $locked;
        });
    }

    private function taxType(string $kind): string
    {
        return match ($kind) {
            'vds' => TaxType::VDS,
            'ait' => TaxType::AIT,
            default => throw new TaxRuleException('record', 'Choose VDS or AIT.'),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{party_id: int, counterparty_name: string, counterparty_bin: ?string, counterparty_tin: ?string}
     */
    private function party(array $input, string $direction): array
    {
        $partyId = $input['party_id'] ?? null;

        if (! is_numeric($partyId)) {
            throw new TaxRuleException('party_id', 'Choose the other party, or type the counterparty for this record only.');
        }

        $party = Party::query()->find((int) $partyId);
        $role = $direction === WithholdingDirection::DEDUCTED_BY_US ? 'supplier' : 'customer';

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole($role)) {
            throw new TaxRuleException('party_id', $direction === WithholdingDirection::DEDUCTED_BY_US
                ? 'Choose an active supplier. Deducting from a supplier does not create a party.'
                : 'Choose an active customer. A customer withholding tax does not create a party.');
        }

        return [
            'party_id' => (int) $party->id,
            'counterparty_name' => $party->stringAttribute('legal_name'),
            'counterparty_bin' => $party->nullableAttribute('bin'),
            'counterparty_tin' => $party->nullableAttribute('tin'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{party_id: null, counterparty_name: string, counterparty_bin: ?string, counterparty_tin: ?string}
     */
    private function manual(array $input): array
    {
        if (is_numeric($input['party_id'] ?? null)) {
            throw new TaxRuleException('party_id', 'A typed counterparty is not saved as a party.');
        }

        return [
            'party_id' => null,
            'counterparty_name' => $this->text->required($input['counterparty_name'] ?? null, 'counterparty_name', 200),
            'counterparty_bin' => $this->text->nullable($input, 'counterparty_bin', 30),
            'counterparty_tin' => $this->text->nullable($input, 'counterparty_tin', 30),
        ];
    }

    private function rule(mixed $id, string $taxType, string $date): ?TaxRule
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! is_numeric($id)) {
            throw new TaxRuleException('tax_rule_id', 'Choose an approved rule of the same tax, or leave it blank.');
        }

        $rule = TaxRule::query()->find((int) $id);

        if (! $rule instanceof TaxRule
            || $rule->stringAttribute('status') !== TaxRuleStatus::APPROVED
            || $rule->stringAttribute('tax_type') !== $taxType) {
            throw new TaxRuleException('tax_rule_id', 'Choose an approved '.TaxType::label($taxType).' rule. Its rate is not used to calculate the amount.');
        }

        $start = $this->ruleDate($rule, 'effective_from');

        if ($start === null || ! $this->calendar->contains($start, $this->ruleDate($rule, 'effective_to'), $date)) {
            throw new TaxRuleException('tax_rule_id', 'That rule does not cover the record date.');
        }

        return $rule;
    }

    private function ruleDate(TaxRule $rule, string $key): ?string
    {
        $value = $rule->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value)) {
            return null;
        }

        $value = substr($value, 0, 10);

        return $value === '' ? null : $value;
    }

    private function mushakDocument(User $actor, mixed $id): ?MushakDocument
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! is_numeric($id)) {
            throw new TaxRuleException('mushak_document_id', 'Choose a Mushak 6.3, or leave it blank.');
        }

        $document = MushakDocument::query()->find((int) $id);

        if (! $document instanceof MushakDocument || ! $this->mushak->canView($actor, $document)) {
            throw new TaxRuleException('mushak_document_id', 'Choose a Mushak 6.3 you can view.');
        }

        return $document;
    }

    private function invoice(mixed $id): ?CommercialInvoice
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! is_numeric($id)) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose a commercial invoice, or leave it blank.');
        }

        $invoice = CommercialInvoice::query()->find((int) $id);

        if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') === DocumentStatus::DRAFT) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued or voided commercial invoice, or leave it blank.');
        }

        return $invoice;
    }
}
