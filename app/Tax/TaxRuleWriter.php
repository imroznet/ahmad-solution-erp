<?php

namespace App\Tax;

use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Draft tax rules can be edited. Approving freezes the legal text.
 * An amendment is a new row. It does not rewrite the row an issued
 * document may already have snapshotted. No rule is seeded with a rate,
 * and nothing in this writer copies a rate onto a Mushak line.
 */
final class TaxRuleWriter
{
    public function create(User $actor, array $input): TaxRule
    {
        return DB::transaction(function () use ($actor, $input): TaxRule {
            $rule = new TaxRule($this->attributes($input, null));
            $rule->version = $this->nextVersion($rule->stringAttribute('tax_type'), $rule->stringAttribute('rule_code'));
            $rule->approval_status = 'draft';
            $rule->activity_status = 'active';
            $rule->created_by = $actor->id;
            $rule->updated_by = $actor->id;
            $rule->save();

            return $rule;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $actor, TaxRule $rule, array $input): TaxRule
    {
        if ($rule->stringAttribute('approval_status') !== 'draft') {
            throw new TaxRuleException('rule', 'An approved tax rule cannot be edited. Record an amendment instead.');
        }

        return DB::transaction(function () use ($actor, $rule, $input): TaxRule {
            $rule->fill($this->attributes($input, $rule));
            $rule->updated_by = $actor->id;
            $rule->save();

            return $rule->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function amend(User $actor, TaxRule $rule, array $input): TaxRule
    {
        if ($rule->stringAttribute('approval_status') !== 'approved') {
            throw new TaxRuleException('rule', 'Only an approved tax rule can be amended.');
        }

        return DB::transaction(function () use ($actor, $rule, $input): TaxRule {
            $amendment = new TaxRule($this->attributes($input, null));
            $amendment->tax_type = $rule->stringAttribute('tax_type');
            $amendment->version = $this->nextVersion($amendment->stringAttribute('tax_type'), $amendment->stringAttribute('rule_code'));
            $amendment->supersedes_rule_id = $rule->id;
            $amendment->approval_status = 'draft';
            $amendment->activity_status = 'active';
            $amendment->created_by = $actor->id;
            $amendment->updated_by = $actor->id;
            $amendment->save();

            return $amendment;
        });
    }

    public function approve(User $actor, TaxRule $rule): TaxRule
    {
        if ($rule->stringAttribute('approval_status') !== 'draft') {
            throw new TaxRuleException('rule', 'This tax rule is already approved.');
        }

        if ($rule->stringAttribute('legal_instrument') === '' || $rule->stringAttribute('rule_code') === '') {
            throw new TaxRuleException('legal_instrument', 'An approved rule needs a code and the legal instrument it cites.');
        }

        return DB::transaction(function () use ($actor, $rule): TaxRule {
            $locked = TaxRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $predecessorId = $locked->getAttribute('supersedes_rule_id');

            if (is_numeric($predecessorId)) {
                $predecessor = TaxRule::query()->whereKey((int) $predecessorId)->lockForUpdate()->first();

                if ($predecessor instanceof TaxRule
                    && $predecessor->stringAttribute('activity_status') === 'active'
                    && $predecessor->dateString('effective_to') === null
                    && ($locked->dateString('effective_from') ?? '') > ($predecessor->dateString('effective_from') ?? '')) {
                    $predecessor->effective_to = $this->dayBefore($locked->dateString('effective_from') ?? '');
                    $predecessor->updated_by = $actor->id;
                    $predecessor->save();
                }
            }

            $this->assertNoOverlap($locked);
            $locked->approval_status = 'approved';
            $locked->approved_at = now();
            $locked->approved_by = $actor->id;
            $locked->updated_by = $actor->id;
            $locked->save();

            return $locked->refresh();
        });
    }

    public function retire(User $actor, TaxRule $rule): TaxRule
    {
        if ($rule->stringAttribute('approval_status') !== 'approved' || $rule->stringAttribute('activity_status') !== 'active') {
            throw new TaxRuleException('rule', 'Only an active approved tax rule can be retired.');
        }

        $rule->activity_status = 'retired';
        $rule->retired_at = now();
        $rule->retired_by = $actor->id;
        $rule->updated_by = $actor->id;
        $rule->save();

        return $rule->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(array $input, ?TaxRule $existing): array
    {
        $type = $existing instanceof TaxRule ? $existing->stringAttribute('tax_type') : $this->type($input['tax_type'] ?? null);
        $from = $this->date($input['effective_from'] ?? null, 'effective_from');
        $to = $this->optionalDate($input['effective_to'] ?? null, 'effective_to');

        if ($to !== null && $to < $from) {
            throw new TaxRuleException('effective_to', 'The end date cannot be before the start date.');
        }

        return [
            'tax_type' => $type,
            'rule_code' => $this->requiredText($input['rule_code'] ?? null, 'rule_code', 'Enter a rule code.', 40),
            'supply_classification' => TaxRuleWindow::normalizeClassification(is_string($input['supply_classification'] ?? null) ? $input['supply_classification'] : null),
            'documented_rate' => $this->optionalRate($input['documented_rate'] ?? null),
            'rate_kind' => $this->rateKind($input['rate_kind'] ?? 'not_a_rate'),
            'calculation_basis' => $this->optionalText($input['calculation_basis'] ?? null),
            'rounding_note' => $this->optionalText($input['rounding_note'] ?? null),
            'price_treatment' => $this->treatment($input['price_treatment'] ?? 'unspecified'),
            'threshold_note' => $this->optionalText($input['threshold_note'] ?? null),
            'exemption_note' => $this->optionalText($input['exemption_note'] ?? null),
            'supporting_data_note' => $this->optionalText($input['supporting_data_note'] ?? null),
            'effective_from' => $from,
            'effective_to' => $to,
            'legal_instrument' => $this->requiredText($input['legal_instrument'] ?? null, 'legal_instrument', 'Enter the legal instrument.', 200),
            'section_reference' => $this->optionalLimited($input['section_reference'] ?? null, 200),
            'source_url' => $this->optionalLimited($input['source_url'] ?? null, 500),
            'publication_date' => $this->optionalDate($input['publication_date'] ?? null, 'publication_date'),
            'instrument_effective_date' => $this->optionalDate($input['instrument_effective_date'] ?? null, 'instrument_effective_date'),
            'notes' => $this->optionalText($input['notes'] ?? null),
        ];
    }

    private function assertNoOverlap(TaxRule $rule): void
    {
        $from = $rule->dateString('effective_from') ?? '';
        $end = $rule->dateString('effective_to');

        $others = TaxRule::query()
            ->where('tax_type', $rule->stringAttribute('tax_type'))
            ->where('supply_classification', $rule->stringAttribute('supply_classification'))
            ->where('approval_status', 'approved')
            ->where('activity_status', 'active')
            ->whereKeyNot($rule->id)
            ->lockForUpdate()
            ->get();

        foreach ($others as $other) {
            if (TaxRuleWindow::overlaps($from, $end, $other->dateString('effective_from') ?? '', $other->dateString('effective_to'))) {
                throw new TaxRuleException('effective_from', 'Another active approved rule already covers this tax type, classification, and date range.');
            }
        }
    }

    private function dayBefore(string $date): string
    {
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function nextVersion(string $type, string $code): int
    {
        $max = TaxRule::query()
            ->where('tax_type', $type)
            ->where('rule_code', $code)
            ->max('version');

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    private function type(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, TaxType::ALL, true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        return $value;
    }

    private function rateKind(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, ['not_a_rate', 'percentage', 'fixed_amount'], true)) {
            throw new TaxRuleException('rate_kind', 'Choose how the explanatory figure should be read.');
        }

        return $value;
    }

    private function treatment(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, ['unspecified', 'exclusive', 'inclusive'], true)) {
            throw new TaxRuleException('price_treatment', 'Choose a price treatment or leave it unspecified.');
        }

        return $value;
    }

    private function optionalRate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new TaxRuleException('documented_rate', 'Enter the explanatory figure as a decimal, or leave it blank.');
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $text) !== 1) {
            throw new TaxRuleException('documented_rate', 'Enter the explanatory figure with at most 4 decimal places, or leave it blank.');
        }

        return $text;
    }

    private function date(mixed $value, string $field): string
    {
        $parsed = $this->optionalDate($value, $field);

        if ($parsed === null) {
            throw new TaxRuleException($field, 'Enter the effective start date.');
        }

        return $parsed;
    }

    private function optionalDate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || date('Y-m-d', strtotime($value)) !== $value) {
            throw new TaxRuleException($field, 'Enter a real date.');
        }

        return $value;
    }

    private function requiredText(mixed $value, string $field, string $message, int $limit): string
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
            throw new TaxRuleException('rule', 'One of the text fields is too long.');
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
