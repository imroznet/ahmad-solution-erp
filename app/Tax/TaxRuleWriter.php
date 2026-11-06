<?php

namespace App\Tax;

use App\Models\TaxRule;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Creates immutable tax-rule versions.
 * An amendment is a new row. Approving a version does not rewrite an older one.
 */
final class TaxRuleWriter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $input): TaxRuleVersion {
            $type = $this->type($input);
            $code = $this->code($input);
            $classification = $this->text($input, 'supply_classification', 80);
            $key = TaxRuleMatcher::classificationKey($classification);
            $this->lockScope($type, $key);

            if (TaxRule::query()->where('tax_type', $type)->where('rule_code', $code)->exists()) {
                throw new TaxRuleException('rule_code', 'A rule with this code already exists for that tax type. Amend that rule instead.');
            }

            $rule = TaxRule::query()->create([
                'tax_type' => $type,
                'rule_code' => $code,
                'supply_classification' => $classification,
                'classification_key' => $key,
                'created_by' => $actor->id,
            ]);

            $version = $this->insertVersion($actor, $rule, 1, $input);
            $this->audit->record('tax_rule_created', $version, newValues: $this->snapshot($version), actor: $actor);

            return $version;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $actor, TaxRuleVersion $version, array $input): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $version, $input): TaxRuleVersion {
            $version->loadMissing('rule');
            $rule = $this->rule($version);

            if ($version->stringAttribute('status') !== TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft version can be edited. An approved version is amended by adding a new version.');
            }

            $before = $this->snapshot($version);
            $this->lockScope($rule->stringAttribute('tax_type'), $rule->stringAttribute('classification_key'));
            $attributes = $this->attributes($input, $rule, (int) $version->version_number, $actor, (int) $version->getKey());
            $version->fill($attributes);
            $version->save();
            $this->audit->record('tax_rule_updated', $version, $before, $this->snapshot($version), $actor);

            return $version->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function amend(User $actor, TaxRuleVersion $source, array $input): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $source, $input): TaxRuleVersion {
            $source->loadMissing('rule');
            $rule = $this->rule($source);

            if ($source->stringAttribute('status') === TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('status', 'Edit the draft version instead of amending it.');
            }

            $this->lockScope($rule->stringAttribute('tax_type'), $rule->stringAttribute('classification_key'));
            $next = (int) $rule->versions()->max('version_number') + 1;
            $version = $this->insertVersion($actor, $rule, $next, $input);
            $this->audit->record('tax_rule_amended', $version, newValues: $this->snapshot($version), actor: $actor);

            return $version;
        }, 3);
    }

    public function approve(User $actor, TaxRuleVersion $version): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $version): TaxRuleVersion {
            $version->loadMissing('rule');
            $rule = $this->rule($version);

            if ($version->stringAttribute('status') !== TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft version can be approved.');
            }

            if ((int) $version->created_by === (int) $actor->getKey()) {
                throw new TaxRuleException('approved_by', 'The person who prepared this version cannot approve it.');
            }

            $this->lockScope($rule->stringAttribute('tax_type'), $rule->stringAttribute('classification_key'));
            $this->assertNoOverlap(
                $rule,
                $version->dateAttribute('effective_from') ?? '',
                $version->dateAttribute('effective_to'),
                (int) $version->getKey(),
            );

            $before = $this->snapshot($version);
            $version->forceFill([
                'status' => TaxRuleStatus::APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $this->audit->record('tax_rule_approved', $version, $before, $this->snapshot($version), $actor);

            return $version->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function retire(User $actor, TaxRuleVersion $version, array $input): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $version, $input): TaxRuleVersion {
            if ($version->stringAttribute('status') !== TaxRuleStatus::APPROVED) {
                throw new TaxRuleException('status', 'Only an approved version can be retired.');
            }

            $reason = $this->required($input, 'retirement_reason', 500, 'Enter a retirement reason.');
            $closing = $this->date($input, 'effective_to');
            $from = $version->dateAttribute('effective_from') ?? '';

            if ($closing < $from) {
                throw new TaxRuleException('effective_to', 'The closing date cannot be before the effective date.');
            }

            $currentEnd = $version->dateAttribute('effective_to');

            if ($currentEnd !== null && $closing > $currentEnd) {
                throw new TaxRuleException('effective_to', 'Retirement cannot extend the period. Choose the current end date or an earlier one.');
            }

            $before = $this->snapshot($version);
            $version->forceFill([
                'status' => TaxRuleStatus::RETIRED,
                'effective_to' => $closing,
                'retired_by' => $actor->id,
                'retired_at' => now(),
                'retirement_reason' => $reason,
            ])->save();
            $this->audit->record('tax_rule_retired', $version, $before, $this->snapshot($version), $actor);

            return $version->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function insertVersion(User $actor, TaxRule $rule, int $number, array $input): TaxRuleVersion
    {
        $version = new TaxRuleVersion($this->attributes($input, $rule, $number, $actor, null));
        $version->tax_rule_id = $rule->id;
        $version->version_number = $number;
        $version->status = TaxRuleStatus::DRAFT;
        $version->created_by = $actor->id;
        $version->save();

        return $version->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(array $input, TaxRule $rule, int $number, User $actor, ?int $exceptVersionId): array
    {
        $from = $this->date($input, 'effective_from');
        $to = $this->optionalDate($input, 'effective_to');

        if ($to !== null && $to < $from) {
            throw new TaxRuleException('effective_to', 'The end date cannot be before the effective date.');
        }

        $this->assertNoOverlap($rule, $from, $to, $exceptVersionId);
        $rates = $this->rates($input);

        return [
            'rate_kind' => $rates['kind'],
            'rate_value' => $rates['percentage'],
            'fixed_amount' => $rates['fixed'],
            'calculation_basis' => $this->nullable($input, 'calculation_basis', 80),
            'threshold_note' => $this->nullable($input, 'threshold_note', 2000),
            'exemption_note' => $this->nullable($input, 'exemption_note', 2000),
            'legal_instrument' => $this->required($input, 'legal_instrument', 200, 'Enter the legal instrument.'),
            'section_reference' => $this->required($input, 'section_reference', 200, 'Enter the section, rule, or paragraph.'),
            'source_url' => $this->url($input),
            'publication_date' => $this->optionalDate($input, 'publication_date'),
            'effective_from' => $from,
            'effective_to' => $to,
            'notes' => $this->nullable($input, 'notes', 5000),
        ];
    }

    private function assertNoOverlap(TaxRule $rule, string $from, ?string $to, ?int $exceptVersionId): void
    {
        $versions = TaxRuleVersion::query()
            ->whereHas('rule', function ($query) use ($rule): void {
                $query->where('tax_type', $rule->stringAttribute('tax_type'))
                    ->where('classification_key', $rule->stringAttribute('classification_key'));
            })
            ->when($exceptVersionId !== null, fn ($query) => $query->whereKeyNot($exceptVersionId))
            ->lockForUpdate()
            ->get();

        foreach ($versions as $version) {
            $otherFrom = $version->dateAttribute('effective_from') ?? '0001-01-01';
            $otherTo = $version->dateAttribute('effective_to');

            if ($this->overlaps($from, $to, $otherFrom, $otherTo)) {
                throw new TaxRuleException('effective_from', 'That period overlaps another version for the same tax type and classification. Close the other period first. A date is included on both its start and end.');
            }
        }
    }

    private function overlaps(string $from, ?string $to, string $otherFrom, ?string $otherTo): bool
    {
        $end = $to ?? '9999-12-31';
        $otherEnd = $otherTo ?? '9999-12-31';

        return $from <= $otherEnd && $otherFrom <= $end;
    }

    private function lockScope(string $type, string $key): void
    {
        TaxRule::query()
            ->where('tax_type', $type)
            ->where('classification_key', $key)
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{kind: string, percentage: ?string, fixed: ?string}
     */
    private function rates(array $input): array
    {
        $kind = $input['rate_kind'] ?? 'none';
        $kind = is_string($kind) ? $kind : 'none';

        if (! in_array($kind, ['none', 'percentage', 'fixed'], true)) {
            throw new TaxRuleException('rate_kind', 'Choose no stored amount, a percentage, or a fixed amount.');
        }

        if ($kind === 'none') {
            return ['kind' => 'none', 'percentage' => null, 'fixed' => null];
        }

        if ($kind === 'percentage') {
            $rate = $input['rate_value'] ?? null;
            $rate = is_string($rate) ? trim($rate) : '';

            if (! preg_match('/^\d{1,3}(\.\d{1,4})?$/', $rate)) {
                throw new TaxRuleException('rate_value', 'Enter the stored percentage with at most 4 decimal places.');
            }

            $value = Decimal::of($rate)->toScale(4);

            if ($value->compare(Decimal::of(100)) > 0) {
                throw new TaxRuleException('rate_value', 'Enter a percentage from 0 to 100.');
            }

            return ['kind' => 'percentage', 'percentage' => (string) $value, 'fixed' => null];
        }

        $amount = $input['fixed_amount'] ?? null;
        $amount = is_string($amount) ? trim($amount) : '';

        if (! preg_match('/^\d{1,16}(\.\d{1,4})?$/', $amount)) {
            throw new TaxRuleException('fixed_amount', 'Enter the fixed amount as a plain decimal.');
        }

        return ['kind' => 'fixed', 'percentage' => null, 'fixed' => (string) Decimal::of($amount)->toScale(4)];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function type(array $input): string
    {
        $type = $input['tax_type'] ?? null;

        if (! is_string($type) || ! in_array($type, TaxType::all(), true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function code(array $input): string
    {
        $code = $input['rule_code'] ?? null;
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,39}$/', $code)) {
            throw new TaxRuleException('rule_code', 'Use 2 to 40 letters, numbers, hyphens, or underscores.');
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function url(array $input): string
    {
        $url = $this->required($input, 'source_url', 500, 'Enter the source URL.');

        if (! preg_match('#^https://#', $url)) {
            throw new TaxRuleException('source_url', 'The source URL must start with https://.');
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function date(array $input, string $field): string
    {
        $value = $input[$field] ?? null;
        $value = is_string($value) ? trim($value) : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException($field, 'Enter a date.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalDate(array $input, string $field): ?string
    {
        $value = $input[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->date($input, $field);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function required(array $input, string $field, int $max, string $message): string
    {
        $value = $this->text($input, $field, $max);

        if ($value === '') {
            throw new TaxRuleException($field, $message);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function nullable(array $input, string $field, int $max): ?string
    {
        $value = $this->text($input, $field, $max);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function text(array $input, string $field, int $max): string
    {
        $value = $input[$field] ?? '';
        $value = is_string($value) ? trim($value) : '';

        if (mb_strlen($value) > $max) {
            throw new TaxRuleException($field, 'Use at most '.$max.' characters.');
        }

        return $value;
    }

    private function rule(TaxRuleVersion $version): TaxRule
    {
        $rule = $version->rule;

        if (! $rule instanceof TaxRule) {
            throw new TaxRuleException('tax_rule_id', 'This version has no rule.');
        }

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TaxRuleVersion $version): array
    {
        $version->loadMissing('rule');
        $rule = $version->rule;

        return [
            'tax_type' => $rule instanceof TaxRule ? $rule->stringAttribute('tax_type') : null,
            'rule_code' => $rule instanceof TaxRule ? $rule->stringAttribute('rule_code') : null,
            'supply_classification' => $rule instanceof TaxRule ? $rule->stringAttribute('supply_classification') : null,
            'version_number' => $version->version_number,
            'status' => $version->stringAttribute('status'),
            'rate_kind' => $version->stringAttribute('rate_kind'),
            'rate_value' => $version->rate_value,
            'fixed_amount' => $version->fixed_amount,
            'legal_instrument' => $version->stringAttribute('legal_instrument'),
            'section_reference' => $version->stringAttribute('section_reference'),
            'source_url' => $version->stringAttribute('source_url'),
            'publication_date' => $version->dateAttribute('publication_date'),
            'effective_from' => $version->dateAttribute('effective_from'),
            'effective_to' => $version->dateAttribute('effective_to'),
            'not_applied_to_documents' => true,
        ];
    }
}
