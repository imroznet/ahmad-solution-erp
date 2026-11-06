<?php

namespace App\Tax;

use App\Models\TaxRule;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Tax-rule versions are immutable once approved.
 * A later amendment is a new version. Nothing in this class copies a rate onto a document.
 */
final class TaxRuleWriter
{
    public function __construct(
        private readonly TaxRuleWindow $windows,
        private readonly VatPriceCalculator $amounts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): TaxRule
    {
        return DB::transaction(function () use ($actor, $input): TaxRule {
            $rule = TaxRule::query()->create([
                'code' => $this->code($input['code'] ?? null),
                'tax_type' => $this->type($input['tax_type'] ?? null),
                'name' => $this->requiredText($input, 'name', 160, 'name', 'Enter a name for this rule.'),
                'supply_classification' => $this->optionalText($input, 'supply_classification', 160),
                'status' => 'active',
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $version = $this->storeVersion($rule, $actor, $input, 1);
            $this->audit->record('tax_rule_created', $rule, newValues: [
                'rule' => $rule->auditSnapshot(),
                'version' => $version->auditSnapshot(),
            ], actor: $actor);

            return $rule;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function addVersion(TaxRule $rule, User $actor, array $input): TaxRuleVersion
    {
        return DB::transaction(function () use ($rule, $actor, $input): TaxRuleVersion {
            $locked = $this->lockRule($rule);
            $this->assertActive($locked);
            $next = (int) $locked->versions()->max('version_number') + 1;
            $version = $this->storeVersion($locked, $actor, $input, $next);
            $this->audit->record('tax_rule_version_created', $version, newValues: $version->auditSnapshot(), actor: $actor);

            return $version;
        });
    }

    public function approve(TaxRuleVersion $version, User $actor): TaxRuleVersion
    {
        return DB::transaction(function () use ($version, $actor): TaxRuleVersion {
            $version = $this->lockVersion($version);
            $rule = $this->lockRule($version->rule()->firstOrFail());

            if ($version->stringAttribute('approval_status') !== 'draft') {
                throw new TaxRuleException('approval', 'Only a draft version can be approved.');
            }

            if ((int) $version->created_by === (int) $actor->getKey()) {
                throw new TaxRuleException('approval', 'The person who prepared this version cannot approve it.');
            }

            $from = $version->dateString('effective_from') ?? '';
            $this->assertNoOverlap($rule, $version, $from, $version->dateString('effective_to'));
            $version->approval_status = 'approved';
            $version->approved_by = $actor->getKey();
            $version->approved_at = now();
            $version->save();
            $this->audit->record('tax_rule_version_approved', $version, newValues: $version->fresh()?->auditSnapshot() ?? [], actor: $actor);

            return $version->fresh() ?? $version;
        });
    }

    public function retire(TaxRuleVersion $version, User $actor): TaxRuleVersion
    {
        return DB::transaction(function () use ($version, $actor): TaxRuleVersion {
            $version = $this->lockVersion($version);

            if (! $version->isApproved()) {
                throw new TaxRuleException('approval', 'Only an approved version can be retired.');
            }

            $version->approval_status = 'retired';
            $version->retired_at = now();
            $version->retired_by = $actor->getKey();
            $version->save();
            $this->audit->record('tax_rule_version_retired', $version, newValues: $version->fresh()?->auditSnapshot() ?? [], actor: $actor);

            return $version->fresh() ?? $version;
        });
    }

    public function close(TaxRuleVersion $version, User $actor, string $effectiveTo): TaxRuleVersion
    {
        return DB::transaction(function () use ($version, $actor, $effectiveTo): TaxRuleVersion {
            $version = $this->lockVersion($version);
            $rule = $this->lockRule($version->rule()->firstOrFail());

            if (! $version->isApproved()) {
                throw new TaxRuleException('effective_to', 'Close an approved version. A draft has no active window.');
            }

            if ($version->effective_to !== null) {
                throw new TaxRuleException('effective_to', 'This version already has an end date. A closed window is not moved.');
            }

            $end = $this->date($effectiveTo, 'effective_to');
            $start = $version->dateString('effective_from') ?? '';

            if ($end < $start) {
                throw new TaxRuleException('effective_to', 'The end date cannot be before the start date. The end date is inclusive.');
            }

            $this->assertNoOverlap($rule, $version, $start, $end);
            $version->effective_to = $end;
            $version->save();
            $this->audit->record('tax_rule_version_closed', $version, newValues: [
                'effective_to' => $end,
                'version' => $version->fresh()?->auditSnapshot(),
            ], actor: $actor);

            return $version->fresh() ?? $version;
        });
    }

    public function retireRule(TaxRule $rule, User $actor): TaxRule
    {
        return DB::transaction(function () use ($rule, $actor): TaxRule {
            $rule = $this->lockRule($rule);
            $before = $rule->auditSnapshot();
            $rule->status = 'retired';
            $rule->updated_by = $actor->getKey();
            $rule->save();
            $this->audit->record('tax_rule_retired', $rule, oldValues: $before, newValues: $rule->auditSnapshot(), actor: $actor);

            return $rule;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function storeVersion(TaxRule $rule, User $actor, array $input, int $number): TaxRuleVersion
    {
        $from = $this->date($input['effective_from'] ?? null, 'effective_from');
        $to = $this->optionalDate($input['effective_to'] ?? null, 'effective_to');

        if ($to !== null && $to < $from) {
            throw new TaxRuleException('effective_to', 'The end date cannot be before the start date. The end date is inclusive.');
        }

        $rate = $this->amounts->rate($this->stringOrNull($input['rate_percent'] ?? null), 'rate_percent');
        $fixed = $this->amounts->optionalMoney($this->stringOrNull($input['fixed_amount'] ?? null), 'fixed_amount', 'Fixed amount');
        $threshold = $this->amounts->optionalMoney($this->stringOrNull($input['threshold_amount'] ?? null), 'threshold_amount', 'Threshold');

        return $rule->versions()->create([
            'version_number' => $number,
            'effective_from' => $from,
            'effective_to' => $to,
            'rate_percent' => $rate,
            'fixed_amount' => $fixed,
            'calculation_basis' => $this->choice($input['calculation_basis'] ?? null, ['manual', 'percent_of_base', 'fixed_amount'], 'calculation_basis', 'manual'),
            'rounding_rule' => 'half_up_2dp',
            'price_treatment' => $this->choice($input['price_treatment'] ?? null, ['unspecified', 'exclusive', 'inclusive'], 'price_treatment', 'unspecified'),
            'threshold_amount' => $threshold,
            'exemption_note' => $this->optionalText($input, 'exemption_note', 2000),
            'legal_instrument' => $this->requiredText($input, 'legal_instrument', 200, 'legal_instrument', 'Enter the legal instrument. A rate without a source is not approved from this screen.'),
            'section_reference' => $this->optionalText($input, 'section_reference', 200),
            'source_url' => $this->optionalText($input, 'source_url', 500),
            'publication_date' => $this->optionalDate($input['publication_date'] ?? null, 'publication_date'),
            'legally_effective_on' => $this->optionalDate($input['legally_effective_on'] ?? null, 'legally_effective_on'),
            'notes' => $this->optionalText($input, 'notes', 5000),
            'approval_status' => 'draft',
            'created_by' => $actor->getKey(),
        ]);
    }

    private function assertNoOverlap(TaxRule $rule, TaxRuleVersion $candidate, string $from, ?string $to): void
    {
        $others = TaxRuleVersion::query()
            ->where('tax_rule_id', $rule->getKey())
            ->where('approval_status', 'approved')
            ->whereKeyNot($candidate->getKey())
            ->lockForUpdate()
            ->get();

        foreach ($others as $other) {
            $otherFrom = $other->dateString('effective_from');

            if ($otherFrom === null) {
                continue;
            }

            if ($this->windows->overlaps($from, $to, $otherFrom, $other->dateString('effective_to'))) {
                throw new TaxRuleException('effective_from', 'This window overlaps approved version '.$other->version_number.' of the same rule. The end date is inclusive, so the next version starts on the following day.');
            }
        }
    }

    private function lockRule(TaxRule $rule): TaxRule
    {
        $locked = TaxRule::query()->whereKey($rule->getKey())->lockForUpdate()->first();

        if (! $locked instanceof TaxRule) {
            throw new TaxRuleException('tax_rule', 'The tax rule was not found.');
        }

        return $locked;
    }

    private function lockVersion(TaxRuleVersion $version): TaxRuleVersion
    {
        $locked = TaxRuleVersion::query()->whereKey($version->getKey())->lockForUpdate()->first();

        if (! $locked instanceof TaxRuleVersion) {
            throw new TaxRuleException('tax_rule', 'The tax-rule version was not found.');
        }

        return $locked;
    }

    private function assertActive(TaxRule $rule): void
    {
        if ($rule->stringAttribute('status') !== 'active') {
            throw new TaxRuleException('tax_rule', 'This rule is retired. Add a new rule instead of changing the retired one.');
        }
    }

    private function code(mixed $value): string
    {
        $code = strtoupper(trim(is_string($value) ? $value : ''));

        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{1,38}$/', $code)) {
            throw new TaxRuleException('code', 'Use 2 to 39 letters, numbers, or hyphens for the rule code.');
        }

        if (TaxRule::query()->where('code', $code)->exists()) {
            throw new TaxRuleException('code', 'That rule code is already in use.');
        }

        return $code;
    }

    private function type(mixed $value): string
    {
        $type = is_string($value) ? $value : '';

        if (! in_array($type, TaxType::all(), true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT. They are stored as different types.');
        }

        return $type;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function choice(mixed $value, array $allowed, string $field, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new TaxRuleException($field, 'Choose a supported value.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredText(array $input, string $key, int $limit, string $field, string $message): string
    {
        $value = trim(is_string($input[$key] ?? null) ? $input[$key] : '');

        if ($value === '') {
            throw new TaxRuleException($field, $message);
        }

        if (mb_strlen($value) > $limit) {
            throw new TaxRuleException($field, 'Use at most '.$limit.' characters.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalText(array $input, string $key, int $limit): ?string
    {
        $value = trim(is_string($input[$key] ?? null) ? $input[$key] : '');

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $limit) {
            throw new TaxRuleException($key, 'Use at most '.$limit.' characters.');
        }

        return $value;
    }

    private function date(mixed $value, string $field): string
    {
        $text = $this->optionalDate($value, $field);

        if ($text === null) {
            throw new TaxRuleException($field, 'Enter a date.');
        }

        return $text;
    }

    private function optionalDate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException($field, 'Enter the date as YYYY-MM-DD.');
        }

        $parsed = date_create_immutable($value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new TaxRuleException($field, 'Enter a real date.');
        }

        return $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
