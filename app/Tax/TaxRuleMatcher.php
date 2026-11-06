<?php

namespace App\Tax;

use App\Models\TaxRuleVersion;

/**
 * Explains which approved versions cover a date.
 * The result is not a rate to apply. Documents keep the percentage a user types.
 */
final class TaxRuleMatcher
{
    public function __construct(private readonly TaxRuleWindow $windows) {}

    /**
     * @return array{on: string, tax_type: string, applied: false, matches: list<array<string, mixed>>, message: string}
     */
    public function explain(string $taxType, string $on): array
    {
        if (! in_array($taxType, TaxType::all(), true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        $on = $this->date($on);
        $matches = [];

        $versions = TaxRuleVersion::query()
            ->with('rule')
            ->where('approval_status', 'approved')
            ->whereDate('effective_from', '<=', $on)
            ->where(function ($query) use ($on): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on);
            })
            ->whereHas('rule', function ($query) use ($taxType): void {
                $query->where('tax_type', $taxType)->where('status', 'active');
            })
            ->orderBy('tax_rule_id')
            ->orderBy('version_number')
            ->get();

        foreach ($versions as $version) {
            $from = $version->dateString('effective_from');

            if ($from === null || ! $this->windows->contains($from, $version->dateString('effective_to'), $on)) {
                continue;
            }

            $matches[] = [
                'version_id' => (int) $version->getKey(),
                'rule_code' => $version->rule?->stringAttribute('code'),
                'rule_name' => $version->rule?->stringAttribute('name'),
                'version_number' => (int) $version->version_number,
                'legal_instrument' => $version->stringAttribute('legal_instrument'),
                'section_reference' => $version->nullableAttribute('section_reference'),
                'rate_percent' => $version->nullableDecimal('rate_percent'),
                'applied' => false,
            ];
        }

        $label = TaxType::label($taxType);
        $message = $matches === []
            ? 'No approved '.$label.' rule covers '.$on.'. No percentage is applied. A tax document stays marked for review until a user records the treatment.'
            : count($matches).' approved '.$label.' rule version'.(count($matches) === 1 ? '' : 's').' cover '.$on.'. None of them is applied automatically. The Mushak percentage is entered by the user.';

        return [
            'on' => $on,
            'tax_type' => $taxType,
            'applied' => false,
            'matches' => $matches,
            'message' => $message,
        ];
    }

    public function approvedFor(string $taxType, string $on, int $versionId): TaxRuleVersion
    {
        $explanation = $this->explain($taxType, $on);

        foreach ($explanation['matches'] as $match) {
            if ((int) $match['version_id'] === $versionId) {
                $version = TaxRuleVersion::query()->find($versionId);

                if ($version instanceof TaxRuleVersion) {
                    return $version;
                }
            }
        }

        throw new TaxRuleException('tax_rule_version_id', 'Choose an approved '.TaxType::label($taxType).' version whose window covers the document date. A draft, retired, or other tax type is not selected.');
    }

    private function date(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new TaxRuleException('on', 'Enter the date as YYYY-MM-DD.');
        }

        $parsed = date_create_immutable($value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new TaxRuleException('on', 'Enter a real date.');
        }

        return $value;
    }
}
