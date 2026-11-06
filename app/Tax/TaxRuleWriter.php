<?php

namespace App\Tax;

use App\Models\TaxRule;
use App\Models\TaxRuleEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Draft rules can be edited. Approving a later version closes the previous
 * version's end date and does not change the rate that version stored.
 */
final class TaxRuleWriter
{
    public function __construct(
        private readonly TaxText $text,
        private readonly TaxRuleCalendar $calendar,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): TaxRule
    {
        return DB::transaction(function () use ($actor, $input): TaxRule {
            $attributes = $this->attributes($input, null);
            $version = (int) TaxRule::query()->where('rule_key', $attributes['rule_key'])->max('version');
            $rule = TaxRule::query()->create([
                ...$attributes,
                'version' => $version + 1,
                'status' => TaxRuleStatus::DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($rule, 'created', $actor, null);
            $this->audit->record('tax_rule_created', $rule, [], $this->auditPayload($rule), $actor);

            return $rule;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(TaxRule $rule, User $actor, array $input): TaxRule
    {
        return DB::transaction(function () use ($rule, $actor, $input): TaxRule {
            $locked = $this->lock($rule);

            if ($locked->stringAttribute('status') !== TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('tax_rule', 'Only a draft tax rule can be edited.');
            }

            $attributes = $this->attributes($input, $locked);
            $locked->fill($attributes);
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->event($locked, 'updated', $actor, null);
            $this->audit->record('tax_rule_updated', $locked, [], $this->auditPayload($locked), $actor);

            return $locked;
        });
    }

    public function approve(TaxRule $rule, User $actor): TaxRule
    {
        return DB::transaction(function () use ($rule, $actor): TaxRule {
            $locked = $this->lock($rule);

            if ($locked->stringAttribute('status') !== TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('tax_rule', 'Only a draft tax rule can be approved.');
            }

            $predecessor = $this->closePredecessor($locked, $actor);
            $this->rejectOverlap($locked, $predecessor);
            $locked->status = TaxRuleStatus::APPROVED;
            $locked->approved_by = $actor->id;
            $locked->approved_at = now();
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->event($locked, 'approved', $actor, null);
            $this->audit->record('tax_rule_approved', $locked, [], $this->auditPayload($locked), $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function amend(TaxRule $rule, User $actor, array $input): TaxRule
    {
        return DB::transaction(function () use ($rule, $actor, $input): TaxRule {
            $locked = $this->lock($rule);

            if ($locked->stringAttribute('status') !== TaxRuleStatus::APPROVED) {
                throw new TaxRuleException('tax_rule', 'Amend an approved rule. The new version stays a draft until it is approved.');
            }

            $openDraft = TaxRule::query()
                ->where('supersedes_rule_id', $locked->id)
                ->where('status', TaxRuleStatus::DRAFT)
                ->exists();

            if ($openDraft) {
                throw new TaxRuleException('tax_rule', 'A draft amendment of this rule already exists.');
            }

            $start = TaxAmount::date($input['effective_from'] ?? null, 'effective_from', true);
            $end = TaxAmount::date($input['effective_to'] ?? null, 'effective_to');
            $this->assertRange($start, $end);
            $previousStart = $this->dateOf($locked, 'effective_from');

            if ($previousStart !== null && $start <= $previousStart) {
                throw new TaxRuleException('effective_from', 'The new version must start after the previous version starts.');
            }

            $nextVersion = (int) TaxRule::query()->where('rule_key', $locked->stringAttribute('rule_key'))->max('version') + 1;
            $draft = TaxRule::query()->create([
                'tax_type' => $locked->stringAttribute('tax_type'),
                'rule_key' => $locked->stringAttribute('rule_key'),
                'version' => $nextVersion,
                'supersedes_rule_id' => $locked->id,
                'supply_classification' => $locked->nullableAttribute('supply_classification'),
                'name' => $locked->stringAttribute('name'),
                'rate' => $locked->nullableDecimal('rate'),
                'rate_kind' => $locked->nullableAttribute('rate_kind'),
                'calculation_basis' => $locked->nullableAttribute('calculation_basis'),
                'rounding_rule' => $locked->nullableAttribute('rounding_rule'),
                'price_treatment' => $locked->nullableAttribute('price_treatment'),
                'threshold_amount' => $locked->nullableDecimal('threshold_amount'),
                'exemption_note' => $locked->getAttribute('exemption_note'),
                'required_supporting_data' => $locked->getAttribute('required_supporting_data'),
                'legal_instrument' => $locked->stringAttribute('legal_instrument'),
                'section_reference' => $locked->nullableAttribute('section_reference'),
                'source_url' => $locked->nullableAttribute('source_url'),
                'notes' => $locked->getAttribute('notes'),
                'publication_date' => $this->dateOf($locked, 'publication_date'),
                'effective_from' => $start,
                'effective_to' => $end,
                'status' => TaxRuleStatus::DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($locked, 'amended', $actor, 'Version '.$draft->getAttribute('version').' was opened as a draft.');
            $this->event($draft, 'created', $actor, 'Copied from version '.$locked->getAttribute('version').'. The copied rate is not applied to a document.');
            $this->audit->record('tax_rule_amended', $locked, [], [
                'rule_key' => $locked->stringAttribute('rule_key'),
                'from_version' => (int) $locked->getAttribute('version'),
                'draft_id' => $draft->id,
            ], $actor);

            return $draft;
        });
    }

    public function retire(TaxRule $rule, User $actor, string $reason): TaxRule
    {
        return DB::transaction(function () use ($rule, $actor, $reason): TaxRule {
            $locked = $this->lock($rule);

            if ($locked->stringAttribute('status') !== TaxRuleStatus::APPROVED) {
                throw new TaxRuleException('tax_rule', 'Only an approved tax rule can be retired.');
            }

            $note = $this->text->required($reason, 'retire_reason', 1000);
            $locked->status = TaxRuleStatus::RETIRED;
            $locked->retired_by = $actor->id;
            $locked->retired_at = now();
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->event($locked, 'retired', $actor, $note);
            $this->audit->record('tax_rule_retired', $locked, [], [
                'rule_key' => $locked->stringAttribute('rule_key'),
                'version' => (int) $locked->getAttribute('version'),
            ], $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(array $input, ?TaxRule $existing): array
    {
        $type = $input['tax_type'] ?? null;

        if (! is_string($type) || ! TaxType::isKnown($type)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        $key = $input['rule_key'] ?? null;

        if (! is_string($key) || preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{1,78}$/', trim($key)) !== 1) {
            throw new TaxRuleException('rule_key', 'Use a rule key of letters, numbers, and hyphens.');
        }

        $key = strtoupper(trim($key));

        if ($existing instanceof TaxRule && $existing->supersedes_rule_id !== null) {
            if ($type !== $existing->stringAttribute('tax_type') || $key !== $existing->stringAttribute('rule_key')) {
                throw new TaxRuleException('rule_key', 'An amendment keeps the tax type and rule key of the previous version.');
            }
        }

        $start = TaxAmount::date($input['effective_from'] ?? null, 'effective_from', true);
        $end = TaxAmount::date($input['effective_to'] ?? null, 'effective_to');
        $this->assertRange($start, $end);
        $rateKind = $this->text->nullable($input, 'rate_kind', 32);
        $rate = $this->rate($input['rate'] ?? null, $rateKind);

        return [
            'tax_type' => $type,
            'rule_key' => $key,
            'supply_classification' => $this->text->nullable($input, 'supply_classification', 120),
            'name' => $this->text->required($input['name'] ?? null, 'name', 200),
            'rate' => $rate,
            'rate_kind' => $rate === null ? null : $rateKind,
            'calculation_basis' => $this->text->nullable($input, 'calculation_basis', 80),
            'rounding_rule' => $this->text->nullable($input, 'rounding_rule', 80),
            'price_treatment' => $this->priceTreatment($input['price_treatment'] ?? null),
            'threshold_amount' => TaxAmount::money($input['threshold_amount'] ?? null, 'threshold_amount'),
            'exemption_note' => $this->text->nullable($input, 'exemption_note', 2000),
            'required_supporting_data' => $this->text->nullable($input, 'required_supporting_data', 2000),
            'legal_instrument' => $this->text->required($input['legal_instrument'] ?? null, 'legal_instrument', 200),
            'section_reference' => $this->text->nullable($input, 'section_reference', 200),
            'source_url' => $this->url($input['source_url'] ?? null),
            'notes' => $this->text->nullable($input, 'notes', 5000),
            'publication_date' => TaxAmount::date($input['publication_date'] ?? null, 'publication_date'),
            'effective_from' => $start,
            'effective_to' => $end,
        ];
    }

    private function rate(mixed $value, ?string $kind): ?string
    {
        if ($kind !== null && ! in_array($kind, ['percentage', 'fixed_amount', 'none'], true)) {
            throw new TaxRuleException('rate_kind', 'Choose a percentage, a fixed amount, or leave the rate blank.');
        }

        if ($kind === null || $kind === 'none') {
            if (is_string($value) && trim($value) !== '') {
                throw new TaxRuleException('rate_kind', 'Choose whether the typed rate is a percentage or a fixed amount.');
            }

            return null;
        }

        if ($kind === 'percentage') {
            $rate = TaxAmount::percentage($value, 'rate');

            if ($rate === null) {
                throw new TaxRuleException('rate', 'Enter the percentage, or clear the rate kind.');
            }

            return $rate;
        }

        $amount = TaxAmount::money($value, 'rate');

        if ($amount === null) {
            throw new TaxRuleException('rate', 'Enter the fixed amount, or clear the rate kind.');
        }

        return $amount;
    }

    private function priceTreatment(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! in_array($value, ['exclusive', 'inclusive', 'unspecified'], true)) {
            throw new TaxRuleException('price_treatment', 'Choose exclusive, inclusive, or unspecified.');
        }

        return $value;
    }

    private function url(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! str_starts_with($value, 'https://') || mb_strlen($value) > 500) {
            throw new TaxRuleException('source_url', 'Enter an https source URL, or leave it blank.');
        }

        return trim($value);
    }

    private function assertRange(?string $start, ?string $end): void
    {
        if ($start !== null && $end !== null && $end < $start) {
            throw new TaxRuleException('effective_to', 'The end date cannot be before the start date. Both dates are inclusive.');
        }
    }

    private function closePredecessor(TaxRule $draft, User $actor): ?TaxRule
    {
        if ($draft->supersedes_rule_id === null) {
            return null;
        }

        $predecessor = TaxRule::query()->whereKey($draft->supersedes_rule_id)->lockForUpdate()->first();

        if (! $predecessor instanceof TaxRule || $predecessor->stringAttribute('status') !== TaxRuleStatus::APPROVED) {
            throw new TaxRuleException('tax_rule', 'The previous version must still be approved.');
        }

        if ($predecessor->stringAttribute('rule_key') !== $draft->stringAttribute('rule_key')
            || $predecessor->stringAttribute('tax_type') !== $draft->stringAttribute('tax_type')
            || (int) $draft->getAttribute('version') <= (int) $predecessor->getAttribute('version')) {
            throw new TaxRuleException('tax_rule', 'This draft is not a later version of the approved rule.');
        }

        $start = $this->dateOf($draft, 'effective_from');
        $previousStart = $this->dateOf($predecessor, 'effective_from');

        if ($start === null || $previousStart === null || $start <= $previousStart) {
            throw new TaxRuleException('effective_from', 'The new version must start after the previous version starts.');
        }

        $previousEnd = $this->dateOf($predecessor, 'effective_to');

        if ($previousEnd === null || $previousEnd >= $start) {
            $predecessor->effective_to = $this->calendar->dayBefore($start);
            $predecessor->updated_by = $actor->id;
            $predecessor->save();
        }

        return $predecessor->fresh() ?? $predecessor;
    }

    private function rejectOverlap(TaxRule $draft, ?TaxRule $predecessor): void
    {
        $start = $this->dateOf($draft, 'effective_from');
        $end = $this->dateOf($draft, 'effective_to');

        if ($start === null) {
            throw new TaxRuleException('effective_from', 'Enter the date this rule starts.');
        }

        $others = TaxRule::query()
            ->where('tax_type', $draft->stringAttribute('tax_type'))
            ->where('rule_key', $draft->stringAttribute('rule_key'))
            ->where('status', TaxRuleStatus::APPROVED)
            ->whereKeyNot($draft->id)
            ->lockForUpdate()
            ->get();

        foreach ($others as $other) {
            if ($predecessor instanceof TaxRule && (int) $other->id === (int) $predecessor->id) {
                $other = $predecessor;
            }

            $otherStart = $this->dateOf($other, 'effective_from');

            if ($otherStart === null) {
                continue;
            }

            if ($this->calendar->overlaps($start, $end, $otherStart, $this->dateOf($other, 'effective_to'))) {
                throw new TaxRuleException('effective_from', 'This version overlaps an approved version of the same rule. The start date is inclusive and the end date is inclusive.');
            }
        }
    }

    private function lock(TaxRule $rule): TaxRule
    {
        $locked = TaxRule::query()->whereKey($rule->id)->lockForUpdate()->first();

        if (! $locked instanceof TaxRule) {
            throw new TaxRuleException('tax_rule', 'This tax rule could not be locked.');
        }

        return $locked;
    }

    private function dateOf(TaxRule $rule, string $key): ?string
    {
        $value = $rule->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function event(TaxRule $rule, string $event, User $actor, ?string $remarks): void
    {
        TaxRuleEvent::query()->create([
            'tax_rule_id' => $rule->id,
            'event' => $event,
            'actor_id' => $actor->id,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(TaxRule $rule): array
    {
        return [
            'tax_type' => $rule->stringAttribute('tax_type'),
            'rule_key' => $rule->stringAttribute('rule_key'),
            'version' => (int) $rule->getAttribute('version'),
            'status' => $rule->stringAttribute('status'),
            'effective_from' => $this->dateOf($rule, 'effective_from'),
            'effective_to' => $this->dateOf($rule, 'effective_to'),
            'rate' => $rule->nullableDecimal('rate'),
            'legal_instrument' => $rule->stringAttribute('legal_instrument'),
        ];
    }
}
