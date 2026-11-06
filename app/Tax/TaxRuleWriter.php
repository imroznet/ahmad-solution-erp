<?php

namespace App\Tax;

use App\Models\TaxRuleEvent;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Drafts can be edited. Approval freezes the rate and the legal source.
 * A later amendment is a new version. The previous version is closed, not rewritten.
 */
final class TaxRuleWriter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): TaxRuleVersion
    {
        $attributes = $this->attributes($input);

        return DB::transaction(function () use ($actor, $attributes): TaxRuleVersion {
            $this->lockScope($attributes['tax_type'], $attributes['supply_classification'], $attributes['rule_code'], null);
            $this->assertNoOverlap($attributes, null, null);

            $version = TaxRuleVersion::query()->create([
                ...$attributes,
                'rule_group_id' => (string) Str::uuid(),
                'version_number' => 1,
                'approval_status' => TaxRuleStatus::DRAFT,
                'created_by' => $actor->getKey(),
            ]);
            $this->event($version, TaxRuleEvent::CREATED, $actor, 'Draft tax rule created.');
            $this->audit->record('tax_rule_created', $version, newValues: $this->auditValues($version), actor: $actor);

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(TaxRuleVersion $version, User $actor, array $input): TaxRuleVersion
    {
        $attributes = $this->attributes($input);

        return DB::transaction(function () use ($version, $actor, $attributes): TaxRuleVersion {
            $locked = $this->lockVersion($version);

            if ($locked->stringAttribute('approval_status') !== TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('approval_status', 'Only a draft tax rule can be edited. An approved rule is amended as a new version.');
            }

            $this->lockScope($attributes['tax_type'], $attributes['supply_classification'], $attributes['rule_code'], $locked->stringAttribute('rule_group_id'));
            $predecessor = $locked->getAttribute('supersedes_version_id');
            $this->assertNoOverlap($attributes, (int) $locked->getKey(), is_numeric($predecessor) ? (int) $predecessor : null);
            $before = $this->auditValues($locked);
            $locked->fill($attributes)->save();
            $this->event($locked, TaxRuleEvent::UPDATED, $actor, 'Draft tax rule updated.');
            $this->audit->record('tax_rule_updated', $locked, $before, $this->auditValues($locked), $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function amend(TaxRuleVersion $version, User $actor, array $input): TaxRuleVersion
    {
        $attributes = $this->attributes($input);

        return DB::transaction(function () use ($version, $actor, $attributes): TaxRuleVersion {
            $locked = $this->lockVersion($version);

            if ($locked->stringAttribute('approval_status') !== TaxRuleStatus::APPROVED) {
                throw new TaxRuleException('approval_status', 'Only an approved tax rule can be amended.');
            }

            $group = $locked->stringAttribute('rule_group_id');
            $openDraft = TaxRuleVersion::query()
                ->where('rule_group_id', $group)
                ->where('approval_status', TaxRuleStatus::DRAFT)
                ->exists();

            if ($openDraft) {
                throw new TaxRuleException('approval_status', 'This rule already has a draft amendment.');
            }

            $this->lockScope($attributes['tax_type'], $attributes['supply_classification'], $attributes['rule_code'], $group);
            $nextNumber = (int) TaxRuleVersion::query()->where('rule_group_id', $group)->max('version_number') + 1;
            $this->assertNoOverlap($attributes, null, (int) $locked->getKey());
            $created = TaxRuleVersion::query()->create([
                ...$attributes,
                'rule_group_id' => $group,
                'version_number' => $nextNumber,
                'approval_status' => TaxRuleStatus::DRAFT,
                'supersedes_version_id' => $locked->getKey(),
                'created_by' => $actor->getKey(),
            ]);
            $this->event($created, TaxRuleEvent::AMENDED, $actor, 'Draft amendment of version '.$locked->getAttribute('version_number').'.');
            $this->audit->record('tax_rule_amended', $created, newValues: [
                ...$this->auditValues($created),
                'supersedes_version_id' => $locked->getKey(),
            ], actor: $actor);

            return $created;
        });
    }

    public function approve(TaxRuleVersion $version, User $actor): TaxRuleVersion
    {
        return DB::transaction(function () use ($version, $actor): TaxRuleVersion {
            $locked = $this->lockVersion($version);

            if ($locked->stringAttribute('approval_status') !== TaxRuleStatus::DRAFT) {
                throw new TaxRuleException('approval_status', 'Only a draft tax rule can be approved.');
            }

            if ((int) $locked->created_by === (int) $actor->getKey()) {
                throw new TaxRuleException('approved_by', 'The person who prepared this version cannot approve it.');
            }

            $attributes = $this->attributesFromVersion($locked);
            $group = $locked->stringAttribute('rule_group_id');
            $this->lockScope($attributes['tax_type'], $attributes['supply_classification'], $attributes['rule_code'], $group);
            $this->closePredecessor($locked, $actor);
            $predecessor = $locked->getAttribute('supersedes_version_id');
            $this->assertNoOverlap($attributes, (int) $locked->getKey(), is_numeric($predecessor) ? (int) $predecessor : null);
            $locked->forceFill([
                'approval_status' => TaxRuleStatus::APPROVED,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->event($locked, TaxRuleEvent::APPROVED, $actor, 'Tax rule version approved.');
            $this->audit->record('tax_rule_approved', $locked, newValues: $this->auditValues($locked), actor: $actor);

            return $locked;
        });
    }

    public function retire(TaxRuleVersion $version, User $actor, string $reason): TaxRuleVersion
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new TaxRuleException('retired_reason', 'A retirement needs a reason.');
        }

        return DB::transaction(function () use ($version, $actor, $reason): TaxRuleVersion {
            $locked = $this->lockVersion($version);

            if ($locked->stringAttribute('approval_status') !== TaxRuleStatus::APPROVED) {
                throw new TaxRuleException('approval_status', 'Only an approved tax rule can be retired.');
            }

            $end = $locked->dateString('effective_to');

            if ($end === null) {
                $from = $locked->dateString('effective_from') ?? TaxRuleCalendar::today();
                $today = TaxRuleCalendar::today();
                $locked->effective_to = $today < $from ? $from : $today;
            }

            $locked->forceFill([
                'approval_status' => TaxRuleStatus::RETIRED,
                'retired_by' => $actor->getKey(),
                'retired_at' => now(),
            ])->save();
            $this->event($locked, TaxRuleEvent::RETIRED, $actor, $reason);
            $this->audit->record('tax_rule_retired', $locked, newValues: [
                'reason' => $reason,
                'effective_to' => $locked->dateString('effective_to'),
            ], actor: $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(array $input): array
    {
        $taxType = is_string($input['tax_type'] ?? null) ? $input['tax_type'] : '';

        if (! in_array($taxType, TaxType::all(), true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        $classification = TaxText::classification(is_string($input['supply_classification'] ?? null) ? $input['supply_classification'] : '');

        if ($classification === '' || strlen($classification) > 160) {
            throw new TaxRuleException('supply_classification', 'Enter the supply or service classification from the legal source.');
        }

        if (TaxText::rejectsClassification($classification)) {
            throw new TaxRuleException('supply_classification', 'A general label such as "standard" or "15%" cannot be a tax rule. Enter the classification the source actually names.');
        }

        $ruleCode = TaxText::ruleCode(is_string($input['rule_code'] ?? null) ? $input['rule_code'] : null);

        if (strlen($ruleCode) > 80) {
            throw new TaxRuleException('rule_code', 'The rule code must be 80 characters or fewer.');
        }

        $basis = is_string($input['rate_basis'] ?? null) ? $input['rate_basis'] : '';

        if (! in_array($basis, RateBasis::all(), true)) {
            throw new TaxRuleException('rate_basis', 'Choose how the amount is determined.');
        }

        $calculation = is_string($input['calculation_basis'] ?? null) ? $input['calculation_basis'] : '';
        $rounding = is_string($input['rounding_rule'] ?? null) ? $input['rounding_rule'] : '';
        $rate = $this->optionalDecimal($input['rate_percent'] ?? null, 'rate_percent', 4);
        $fixed = $this->optionalDecimal($input['fixed_amount'] ?? null, 'fixed_amount', 2);
        $threshold = $this->optionalDecimal($input['threshold_amount'] ?? null, 'threshold_amount', 2);
        $treatment = is_string($input['threshold_treatment'] ?? null) ? $input['threshold_treatment'] : ThresholdTreatment::NOT_APPLICABLE;

        if (! in_array($treatment, ThresholdTreatment::all(), true)) {
            throw new TaxRuleException('threshold_treatment', 'Choose a threshold treatment, or leave the threshold empty.');
        }

        if ($threshold === null && $treatment !== ThresholdTreatment::NOT_APPLICABLE) {
            throw new TaxRuleException('threshold_amount', 'A threshold treatment needs a threshold amount.');
        }

        if ($threshold !== null && $treatment === ThresholdTreatment::NOT_APPLICABLE) {
            throw new TaxRuleException('threshold_treatment', 'Record how a base below the threshold is treated. This screen will not guess.');
        }

        if (RateBasis::isZeroTreatment($basis)) {
            if ($rate !== null || $fixed !== null || $calculation !== CalculationBasis::NOT_APPLICABLE || $rounding !== RoundingRule::NOT_APPLICABLE) {
                throw new TaxRuleException('rate_basis', 'An exempt, zero-rated, or non-taxable rule has no rate and no inclusive or exclusive calculation.');
            }
        } elseif ($basis === RateBasis::FIXED_AMOUNT) {
            if ($fixed === null || $rate !== null || $calculation !== CalculationBasis::NOT_APPLICABLE || $rounding !== RoundingRule::HALF_AWAY_FROM_ZERO) {
                throw new TaxRuleException('fixed_amount', 'A fixed amount needs the amount, no percentage, and the application rounding rule.');
            }
        } elseif ($basis === RateBasis::PERCENTAGE) {
            if ($rate === null || $fixed !== null || ! in_array($calculation, [CalculationBasis::EXCLUSIVE, CalculationBasis::INCLUSIVE], true) || $rounding !== RoundingRule::HALF_AWAY_FROM_ZERO) {
                throw new TaxRuleException('rate_percent', 'A percentage rule needs a rate, an exclusive or inclusive basis, and the application rounding rule.');
            }

            if (Decimal::of($rate)->compare(Decimal::of('100')) > 0) {
                throw new TaxRuleException('rate_percent', 'The rate cannot be above 100 percent.');
            }
        }

        $from = $this->date($input['effective_from'] ?? null, 'effective_from');
        $to = $this->optionalDate($input['effective_to'] ?? null, 'effective_to');

        if ($to !== null && $to < $from) {
            throw new TaxRuleException('effective_to', 'The end date cannot be before the start date. Both dates are inclusive.');
        }

        $instrument = $this->requiredText($input['legal_instrument'] ?? null, 'legal_instrument', 200, 'Enter the SRO, rules, or other legal instrument.');
        $section = $this->requiredText($input['section_reference'] ?? null, 'section_reference', 200, 'Enter the section, rule, or paragraph.');
        $url = $this->requiredText($input['source_url'] ?? null, 'source_url', 500, 'Enter the https source URL.');

        if (! TaxText::httpsUrl($url)) {
            throw new TaxRuleException('source_url', 'The source URL must be an https address.');
        }

        return [
            'tax_type' => $taxType,
            'supply_classification' => $classification,
            'rule_code' => $ruleCode,
            'rate_basis' => $basis,
            'rate_percent' => $rate,
            'fixed_amount' => $fixed,
            'currency_code' => 'BDT',
            'calculation_basis' => $calculation,
            'rounding_rule' => $rounding,
            'threshold_amount' => $threshold,
            'threshold_treatment' => $treatment,
            'required_supporting_data' => $this->optionalText($input['required_supporting_data'] ?? null, 'required_supporting_data', 2000),
            'exemption_note' => $this->optionalText($input['exemption_note'] ?? null, 'exemption_note', 2000),
            'effective_from' => $from,
            'effective_to' => $to,
            'legal_instrument' => $instrument,
            'section_reference' => $section,
            'source_url' => $url,
            'publication_date' => $this->optionalDate($input['publication_date'] ?? null, 'publication_date'),
            'legal_effective_date' => $this->optionalDate($input['legal_effective_date'] ?? null, 'legal_effective_date'),
            'internal_notes' => $this->optionalText($input['internal_notes'] ?? null, 'internal_notes', 2000),
        ];
    }

    private function lockVersion(TaxRuleVersion $version): TaxRuleVersion
    {
        $locked = TaxRuleVersion::query()->whereKey($version->getKey())->lockForUpdate()->first();

        if (! $locked instanceof TaxRuleVersion) {
            throw new TaxRuleException('tax_rule', 'This tax rule could not be locked.');
        }

        return $locked;
    }

    private function lockScope(string $taxType, string $classification, string $ruleCode, ?string $group): void
    {
        TaxRuleVersion::query()
            ->where(function ($query) use ($taxType, $classification, $ruleCode, $group): void {
                $query->where(function ($scope) use ($taxType, $classification, $ruleCode): void {
                    $scope->where('tax_type', $taxType)
                        ->where('supply_classification', $classification)
                        ->where('rule_code', $ruleCode);
                });

                if ($group !== null) {
                    $query->orWhere('rule_group_id', $group);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * The immediate predecessor may still be open while its amendment is a draft.
     * That one range is compared as if approval will close it the day before
     * the new start. Every other version is compared as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assertNoOverlap(array $attributes, ?int $ignoreId, ?int $predecessorId): void
    {
        $versions = TaxRuleVersion::query()
            ->where('tax_type', $attributes['tax_type'])
            ->where('supply_classification', $attributes['supply_classification'])
            ->where('rule_code', $attributes['rule_code'])
            ->where('approval_status', '!=', TaxRuleStatus::RETIRED)
            ->orderBy('id')
            ->get();

        foreach ($versions as $existing) {
            if ($ignoreId !== null && (int) $existing->getKey() === $ignoreId) {
                continue;
            }

            $from = $existing->dateString('effective_from');

            if ($from === null) {
                continue;
            }

            $to = $existing->dateString('effective_to');

            if ($predecessorId !== null && (int) $existing->getKey() === $predecessorId && $attributes['effective_from'] > $from && $to === null) {
                $to = TaxRuleCalendar::dayBefore($attributes['effective_from']);
            }

            if (TaxRuleCalendar::overlaps($attributes['effective_from'], $attributes['effective_to'], $from, $to)) {
                throw new TaxRuleException('effective_from', 'This version overlaps another draft or approved rule for the same tax, classification, and rule code. End dates are inclusive, so the next version must start on the following day.');
            }
        }
    }

    private function closePredecessor(TaxRuleVersion $draft, User $actor): void
    {
        $previousId = $draft->getAttribute('supersedes_version_id');

        if ($previousId === null) {
            return;
        }

        $previous = TaxRuleVersion::query()->whereKey($previousId)->lockForUpdate()->first();

        if (! $previous instanceof TaxRuleVersion) {
            throw new TaxRuleException('supersedes_version_id', 'The previous version is missing.');
        }

        if ($previous->stringAttribute('approval_status') !== TaxRuleStatus::APPROVED) {
            throw new TaxRuleException('approval_status', 'The previous version must still be approved before this amendment can take its place.');
        }

        $from = $draft->dateString('effective_from');
        $previousFrom = $previous->dateString('effective_from');

        if ($from === null || $previousFrom === null || $from <= $previousFrom) {
            throw new TaxRuleException('effective_from', 'The amendment must start after the previous version starts.');
        }

        $closeOn = TaxRuleCalendar::dayBefore($from);
        $previousTo = $previous->dateString('effective_to');

        if ($previousTo === null) {
            $previous->effective_to = $closeOn;
            $previous->save();
            $this->event($previous, TaxRuleEvent::CLOSED, $actor, 'Closed because version '.$draft->getAttribute('version_number').' was approved.');

            return;
        }

        if ($previousTo >= $from) {
            throw new TaxRuleException('effective_from', 'The previous version already covers this start date. Its end date is not moved.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromVersion(TaxRuleVersion $version): array
    {
        return $this->attributes([
            'tax_type' => $version->stringAttribute('tax_type'),
            'supply_classification' => $version->stringAttribute('supply_classification'),
            'rule_code' => $version->stringAttribute('rule_code'),
            'rate_basis' => $version->stringAttribute('rate_basis'),
            'rate_percent' => $version->nullableDecimal('rate_percent'),
            'fixed_amount' => $version->nullableDecimal('fixed_amount'),
            'calculation_basis' => $version->stringAttribute('calculation_basis'),
            'rounding_rule' => $version->stringAttribute('rounding_rule'),
            'threshold_amount' => $version->nullableDecimal('threshold_amount'),
            'threshold_treatment' => $version->stringAttribute('threshold_treatment'),
            'required_supporting_data' => $version->getAttribute('required_supporting_data'),
            'exemption_note' => $version->getAttribute('exemption_note'),
            'effective_from' => $version->dateString('effective_from'),
            'effective_to' => $version->dateString('effective_to'),
            'legal_instrument' => $version->stringAttribute('legal_instrument'),
            'section_reference' => $version->stringAttribute('section_reference'),
            'source_url' => $version->stringAttribute('source_url'),
            'publication_date' => $version->dateString('publication_date'),
            'legal_effective_date' => $version->dateString('legal_effective_date'),
            'internal_notes' => $version->getAttribute('internal_notes'),
        ]);
    }

    private function event(TaxRuleVersion $version, string $event, User $actor, string $remarks): void
    {
        TaxRuleEvent::query()->create([
            'tax_rule_version_id' => $version->getKey(),
            'event' => $event,
            'actor_id' => $actor->getKey(),
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(TaxRuleVersion $version): array
    {
        return [
            'tax_type' => $version->stringAttribute('tax_type'),
            'supply_classification' => $version->stringAttribute('supply_classification'),
            'rule_code' => $version->stringAttribute('rule_code'),
            'rate_basis' => $version->stringAttribute('rate_basis'),
            'rate_percent' => $version->nullableDecimal('rate_percent'),
            'fixed_amount' => $version->nullableDecimal('fixed_amount'),
            'effective_from' => $version->dateString('effective_from'),
            'effective_to' => $version->dateString('effective_to'),
            'legal_instrument' => $version->stringAttribute('legal_instrument'),
            'approval_status' => $version->stringAttribute('approval_status'),
            'version_number' => $version->getAttribute('version_number'),
        ];
    }

    private function optionalDecimal(mixed $value, string $field, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new TaxRuleException($field, 'Enter a plain decimal number.');
        }

        $text = trim((string) $value);
        $pattern = $scale === 4 ? '/^\d+(\.\d{1,4})?$/' : '/^\d+(\.\d{1,2})?$/';

        if (! preg_match($pattern, $text)) {
            throw new TaxRuleException($field, 'Use up to '.$scale.' decimal places. A floating-point value is not accepted.');
        }

        $padded = Decimal::of($text)->toScale($scale)->__toString();

        return $padded;
    }

    private function date(mixed $value, string $field): string
    {
        $date = $this->optionalDate($value, $field);

        if ($date === null) {
            throw new TaxRuleException($field, 'Enter the date.');
        }

        return $date;
    }

    private function optionalDate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException($field, 'Enter the date as YYYY-MM-DD.');
        }

        $parsed = Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Dhaka');

        if (! $parsed instanceof Carbon || $parsed->toDateString() !== $value) {
            throw new TaxRuleException($field, 'That date is not a real calendar date.');
        }

        return $value;
    }

    private function requiredText(mixed $value, string $field, int $max, string $message): string
    {
        $text = $this->optionalText($value, $field, $max);

        if ($text === null) {
            throw new TaxRuleException($field, $message);
        }

        return $text;
    }

    private function optionalText(mixed $value, string $field, int $max): ?string
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
