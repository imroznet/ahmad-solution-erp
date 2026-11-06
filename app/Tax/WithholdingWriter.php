<?php

namespace App\Tax;

use App\Models\AitRecord;
use App\Models\MushakInvoice;
use App\Models\Party;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Models\VdsRecord;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Stores a VDS or AIT fact. The amount is typed. It is not calculated from
 * output VAT and it is not added to a Mushak total. No statutory certificate
 * is issued, because the current certificate form was not verified.
 */
final class WithholdingWriter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createVds(User $actor, array $input): VdsRecord
    {
        /** @var VdsRecord $record */
        $record = $this->persist($actor, new VdsRecord, $input, TaxType::VDS, 'vds_recorded');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateVds(User $actor, VdsRecord $record, array $input): VdsRecord
    {
        /** @var VdsRecord $saved */
        $saved = $this->persist($actor, $record, $input, TaxType::VDS, 'vds_updated');

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createAit(User $actor, array $input): AitRecord
    {
        /** @var AitRecord $record */
        $record = $this->persist($actor, new AitRecord, $input, TaxType::AIT, 'ait_recorded');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateAit(User $actor, AitRecord $record, array $input): AitRecord
    {
        /** @var AitRecord $saved */
        $saved = $this->persist($actor, $record, $input, TaxType::AIT, 'ait_updated');

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function persist(User $actor, Model $record, array $input, string $taxType, string $event): Model
    {
        return DB::transaction(function () use ($actor, $record, $input, $taxType, $event): Model {
            $before = $record->exists ? $this->snapshot($record) : [];
            $attributes = $this->attributes($input, $taxType);
            $attributes['certificate_status'] = 'not_issued_form_unverified';
            $attributes['updated_by'] = $actor->id;

            if (! $record->exists) {
                $attributes['created_by'] = $actor->id;
            }

            $record->fill($attributes);
            $record->save();
            $this->audit->record($event, $record, $before, $this->snapshot($record), $actor);

            return $record->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(array $input, string $taxType): array
    {
        $direction = $input['direction'] ?? null;

        if (! is_string($direction) || ! in_array($direction, WithholdingDirection::all(), true)) {
            throw new TaxRuleException('direction', 'Choose whether this was withheld by a customer or deducted from a supplier.');
        }

        $party = $this->party($input);
        $name = $party instanceof Party ? $party->stringAttribute('legal_name') : $this->required($input, 'party_name', 200, 'Enter the other party name.');

        return [
            'direction' => $direction,
            'party_id' => $party?->getKey(),
            'party_name' => $name,
            'party_bin' => $party instanceof Party ? $this->emptyToNull($party->stringAttribute('bin')) : $this->optional($input, 'party_bin', 40),
            'party_tin' => $party instanceof Party ? $this->emptyToNull($party->stringAttribute('tin')) : $this->optional($input, 'party_tin', 40),
            'commercial_invoice_id' => $this->optionalId($input, 'commercial_invoice_id'),
            'mushak_invoice_id' => $this->mushakId($input),
            'tax_rule_version_id' => $this->ruleId($input, $taxType),
            'supply_description' => $this->required($input, 'supply_description', 500, 'Describe the underlying supply or payment.'),
            'taxable_basis' => $this->optionalAmount($input, 'taxable_basis'),
            'amount' => $this->amount($input),
            'record_date' => $this->date($input),
            'certificate_reference' => $this->optional($input, 'certificate_reference', 80),
            'payment_reference' => $this->optional($input, 'payment_reference', 80),
            'source_note' => $this->optional($input, 'source_note', 2000),
            'internal_notes' => $this->optional($input, 'internal_notes', 2000),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function party(array $input): ?Party
    {
        $id = $input['party_id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        $party = Party::query()->find(is_numeric($id) ? (int) $id : 0);

        if (! $party instanceof Party) {
            throw new TaxRuleException('party_id', 'Choose a party that already exists, or leave it blank and type the name.');
        }

        return $party;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function mushakId(array $input): ?int
    {
        $id = $this->optionalId($input, 'mushak_invoice_id');

        if ($id === null) {
            return null;
        }

        if (! MushakInvoice::query()->whereKey($id)->exists()) {
            throw new TaxRuleException('mushak_invoice_id', 'That Mushak document does not exist.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function ruleId(array $input, string $taxType): ?int
    {
        $id = $this->optionalId($input, 'tax_rule_version_id');

        if ($id === null) {
            return null;
        }

        $version = TaxRuleVersion::query()->with('rule')->find($id);

        if (! $version instanceof TaxRuleVersion || $version->stringAttribute('status') !== TaxRuleStatus::APPROVED) {
            throw new TaxRuleException('tax_rule_version_id', 'Choose an approved rule version, or leave the rule blank.');
        }

        if ($version->rule?->stringAttribute('tax_type') !== $taxType) {
            throw new TaxRuleException('tax_rule_version_id', 'That rule is a different tax type. VDS and AIT stay separate from output VAT.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function amount(array $input): string
    {
        $value = $input['amount'] ?? null;
        $value = is_string($value) ? trim($value) : '';

        if (! preg_match('/^\d{1,16}(\.\d{1,4})?$/', $value)) {
            throw new TaxRuleException('amount', 'Enter the withheld amount as a plain decimal. It is not calculated from VAT.');
        }

        $amount = Decimal::of($value)->toScale(4);

        if ($amount->isZero()) {
            throw new TaxRuleException('amount', 'Enter an amount greater than zero, or do not record a withholding.');
        }

        return (string) $amount;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalAmount(array $input, string $field): ?string
    {
        $value = $input[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (! preg_match('/^\d{1,16}(\.\d{1,4})?$/', trim($value))) {
            throw new TaxRuleException($field, 'Enter a plain decimal, or leave the basis blank.');
        }

        return (string) Decimal::of(trim($value))->toScale(4);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function date(array $input): string
    {
        $value = $input['record_date'] ?? null;
        $value = is_string($value) ? trim($value) : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException('record_date', 'Enter the record date.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalId(array $input, string $field): ?int
    {
        $value = $input[$field] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 1) {
            throw new TaxRuleException($field, 'Choose a record, or leave this blank.');
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function required(array $input, string $field, int $max, string $message): string
    {
        $value = $this->optional($input, $field, $max) ?? '';

        if ($value === '') {
            throw new TaxRuleException($field, $message);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optional(array $input, string $field, int $max): ?string
    {
        $value = $input[$field] ?? '';
        $value = is_string($value) ? trim($value) : '';

        if (mb_strlen($value) > $max) {
            throw new TaxRuleException($field, 'Use at most '.$max.' characters.');
        }

        return $value === '' ? null : $value;
    }

    private function emptyToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $record): array
    {
        return [
            'direction' => $record->getAttribute('direction'),
            'party_id' => $record->getAttribute('party_id'),
            'party_name' => $record->getAttribute('party_name'),
            'amount' => $record->getAttribute('amount'),
            'taxable_basis' => $record->getAttribute('taxable_basis'),
            'record_date' => $record->getAttribute('record_date'),
            'mushak_invoice_id' => $record->getAttribute('mushak_invoice_id'),
            'tax_rule_version_id' => $record->getAttribute('tax_rule_version_id'),
            'certificate_status' => $record->getAttribute('certificate_status'),
        ];
    }
}
