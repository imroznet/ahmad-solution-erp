<?php

namespace App\Tax;

use App\Models\AitRecord;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\VdsRecord;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * VDS and AIT records store amounts the user typed.
 * They are not calculated from a rate and are not added to output VAT.
 * certificate_form stays unverified: Mushak 6.6 and the withholding
 * certificate layout were not confirmed from a gazette.
 */
final class DeductionWriter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(string $kind, User $actor, array $input): Model
    {
        $record = $this->model($kind);
        $this->fill($kind, $record, $input);
        $record->setAttribute('status', 'draft');
        $record->setAttribute('certificate_form', 'unverified');
        $record->setAttribute('review_status', 'review_required');
        $record->setAttribute('created_by', $actor->id);
        $record->save();
        $this->audit->record($kind.'_prepared', $record, newValues: $this->payload($record), actor: $actor);

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(string $kind, User $actor, Model $record, array $input): Model
    {
        $this->assertDraft($record);
        $this->fill($kind, $record, $input);
        $record->save();
        $this->audit->record($kind.'_updated', $record, newValues: $this->payload($record), actor: $actor);

        return $record->refresh();
    }

    public function record(string $kind, User $actor, Model $record): Model
    {
        $this->assertDraft($record);

        if ($this->text($record, 'counterparty_name') === ''
            || $record->getAttribute('deducted_amount') === null
            || $record->getAttribute('taxable_basis') === null) {
            throw new TaxRuleException('deducted_amount', 'Enter the counterparty, the taxable basis, and the deducted amount before recording. Nothing is calculated.');
        }

        return DB::transaction(function () use ($kind, $actor, $record): Model {
            $record->setAttribute('status', 'recorded');
            $record->setAttribute('recorded_at', now());
            $record->setAttribute('recorded_by', $actor->id);
            $record->loadMissing('taxRule');
            $rule = $record->getRelation('taxRule');
            $record->setAttribute('rule_snapshot', $this->snapshot($rule));
            $record->save();
            $this->audit->record($kind.'_recorded', $record, newValues: $this->payload($record), actor: $actor);

            return $record->refresh();
        });
    }

    public function void(string $kind, User $actor, Model $record, string $reason): Model
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw new TaxRuleException('void_reason', 'Enter a void reason.');
        }

        if (! in_array($this->text($record, 'status'), ['draft', 'recorded'], true)) {
            throw new TaxRuleException('record', 'This record is already void.');
        }

        $record->setAttribute('status', 'void');
        $record->setAttribute('voided_at', now());
        $record->setAttribute('voided_by', $actor->id);
        $record->setAttribute('void_reason', $reason);
        $record->save();
        $this->audit->record($kind.'_voided', $record, newValues: $this->payload($record), actor: $actor);

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fill(string $kind, Model $record, array $input): void
    {
        $direction = $input['direction'] ?? null;

        if (! is_string($direction) || ! in_array($direction, ['withheld_from_us', 'deducted_by_us'], true)) {
            throw new TaxRuleException('direction', 'Choose whether the amount was withheld from the company or deducted by the company.');
        }

        $record->setAttribute('direction', $direction);
        $record->setAttribute('counterparty_name', $this->limited($input['counterparty_name'] ?? null, 'counterparty_name', 'Enter the other party.', 200));
        $record->setAttribute('counterparty_bin', $this->optionalLimited($input['counterparty_bin'] ?? null, 30));
        $record->setAttribute('counterparty_tin', $this->optionalLimited($input['counterparty_tin'] ?? null, 30));
        $record->setAttribute('source_reference', $this->optionalLimited($input['source_reference'] ?? null, 120));
        $record->setAttribute('taxable_basis', $this->money($input['taxable_basis'] ?? null, true));
        $record->setAttribute('deducted_amount', $this->money($input['deducted_amount'] ?? null, true));
        $recordDate = $this->date($input['record_date'] ?? null);
        $record->setAttribute('record_date', $recordDate);
        $record->setAttribute('certificate_reference', $this->optionalLimited($input['certificate_reference'] ?? null, 120));
        $record->setAttribute('treasury_reference', $this->optionalLimited($input['treasury_reference'] ?? null, 120));
        $record->setAttribute('notes', $this->optionalText($input['notes'] ?? null));
        $record->setAttribute('internal_notes', $this->optionalText($input['internal_notes'] ?? null));
        $record->setAttribute('certificate_form', 'unverified');
        $record->setAttribute('party_id', $this->optionalId($input['party_id'] ?? null));
        $record->setAttribute('commercial_invoice_id', $this->existingId(CommercialInvoice::class, $input['commercial_invoice_id'] ?? null, 'commercial_invoice_id'));
        $record->setAttribute('mushak_invoice_id', $this->existingId(MushakInvoice::class, $input['mushak_invoice_id'] ?? null, 'mushak_invoice_id'));
        $record->setAttribute('tax_rule_id', $this->ruleId($kind, $recordDate, $input['tax_rule_id'] ?? null));
    }

    private function model(string $kind): Model
    {
        return match ($kind) {
            'vds' => new VdsRecord,
            'ait' => new AitRecord,
            default => throw new TaxRuleException('kind', 'Choose VDS or AIT.'),
        };
    }

    private function assertDraft(Model $record): void
    {
        if ($this->text($record, 'status') !== 'draft') {
            throw new TaxRuleException('record', 'Only a draft deduction record can be changed.');
        }
    }

    private function ruleId(string $kind, string $onDate, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new TaxRuleException('tax_rule_id', 'Choose a matching rule or leave it blank.');
        }

        $rule = TaxRule::query()->find((int) $value);
        $type = $kind === 'vds' ? TaxType::VDS : TaxType::AIT;

        if (! $rule instanceof TaxRule
            || $rule->stringAttribute('tax_type') !== $type
            || $rule->stringAttribute('approval_status') !== 'approved'
            || $rule->stringAttribute('activity_status') !== 'active') {
            throw new TaxRuleException('tax_rule_id', 'The reference must be an active approved rule of the same tax type. It does not calculate the amount.');
        }

        $from = $rule->dateString('effective_from') ?? '';
        $to = $rule->dateString('effective_to');

        if ($onDate === '' || ! TaxRuleWindow::overlaps($from, $to, $onDate, $onDate)) {
            throw new TaxRuleException('tax_rule_id', 'That rule is not effective on the record date.');
        }

        return (int) $rule->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot(mixed $rule): ?array
    {
        if (! $rule instanceof TaxRule) {
            return null;
        }

        return [
            'tax_rule_id' => $rule->id,
            'version' => (int) $rule->getAttribute('version'),
            'tax_type' => $rule->stringAttribute('tax_type'),
            'rule_code' => $rule->stringAttribute('rule_code'),
            'rate_was_not_applied' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Model $record): array
    {
        return [
            'direction' => $this->text($record, 'direction'),
            'status' => $this->text($record, 'status'),
            'counterparty_name' => $this->text($record, 'counterparty_name'),
            'taxable_basis' => $record->getAttribute('taxable_basis'),
            'deducted_amount' => $record->getAttribute('deducted_amount'),
            'certificate_form' => $this->text($record, 'certificate_form'),
        ];
    }

    private function text(Model $record, string $key): string
    {
        $value = $record->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    private function money(mixed $value, bool $optional): ?string
    {
        if ($value === null || $value === '') {
            if ($optional) {
                return null;
            }

            throw new TaxRuleException('deducted_amount', 'Enter the amount.');
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new TaxRuleException('deducted_amount', 'Enter the amount as a decimal.');
        }

        try {
            $parsed = Decimal::of(trim((string) $value))->toScale(4, RoundingMode::HalfUp);
        } catch (\InvalidArgumentException) {
            throw new TaxRuleException('deducted_amount', 'Enter the amount as a decimal.');
        }

        if ($parsed->isNegative()) {
            throw new TaxRuleException('deducted_amount', 'The basis and the deducted amount cannot be negative.');
        }

        return (string) $parsed;
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || date('Y-m-d', strtotime($value)) !== $value) {
            throw new TaxRuleException('record_date', 'Enter the deduction date.');
        }

        return $value;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function existingId(string $class, mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || ! $class::query()->whereKey((int) $value)->exists()) {
            throw new TaxRuleException($field, 'That linked document was not found.');
        }

        return (int) $value;
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function limited(mixed $value, string $field, string $message, int $limit): string
    {
        $text = $this->optionalLimited($value, $limit);

        if ($text === null) {
            throw new TaxRuleException($field, $message);
        }

        return $text;
    }

    private function optionalLimited(mixed $value, int $limit): ?string
    {
        $text = $this->optionalText($value);

        if ($text !== null && mb_strlen($text) > $limit) {
            throw new TaxRuleException('record', 'One of the text fields is too long.');
        }

        return $text;
    }

    private function optionalText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
