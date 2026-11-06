<?php

namespace App\Tax;

use App\Models\TaxRuleVersion;
use App\Tax\TaxRuleStatus as Status;

/**
 * Selects at most one approved version. No match is review_required.
 * There is no fallback rate.
 */
final class TaxRuleSelector
{
    public function select(string $taxType, string $classification, string $ruleCode, string $onDate): TaxSelection
    {
        $classification = TaxText::classification($classification);
        $ruleCode = TaxText::ruleCode($ruleCode);
        $versions = TaxRuleVersion::query()
            ->where('tax_type', $taxType)
            ->where('supply_classification', $classification)
            ->where('rule_code', $ruleCode)
            ->where('approval_status', Status::APPROVED)
            ->orderBy('id')
            ->get();

        $matched = [];

        foreach ($versions as $version) {
            $from = $version->dateString('effective_from');

            if ($from === null) {
                continue;
            }

            if (TaxRuleCalendar::contains($from, $version->dateString('effective_to'), $onDate)) {
                $matched[] = $version;
            }
        }

        if ($matched === []) {
            return new TaxSelection('review_required', null, [
                'No approved '.$this->label($taxType).' rule matches this classification, rule code, and date. Nothing is calculated, and no default rate is used.',
            ], []);
        }

        if (count($matched) > 1) {
            return new TaxSelection('review_required', null, [
                'More than one approved rule matches this date. The amount is left for review instead of picking one.',
            ], array_map(fn (TaxRuleVersion $version): int => (int) $version->getKey(), $matched));
        }

        $version = $matched[0];

        return new TaxSelection('matched', $version, $this->reasons($version, $onDate), [(int) $version->getKey()]);
    }

    /**
     * @return list<string>
     */
    private function reasons(TaxRuleVersion $version, string $onDate): array
    {
        $to = $version->dateString('effective_to');
        $range = 'from '.$version->dateString('effective_from').($to === null ? ', with no end date' : ' through '.$to);

        return [
            'Approved '.TaxType::label($version->stringAttribute('tax_type')).' version '.$version->getAttribute('version_number').' (rule '.$version->getKey().') matches '.$onDate.'.',
            'Classification "'.$version->stringAttribute('supply_classification').'" and rule code "'.($version->stringAttribute('rule_code') === '' ? 'none' : $version->stringAttribute('rule_code')).'" are an exact match. A blank code does not cover a coded rule, and a coded rule does not cover a blank code.',
            'The version is effective '.$range.'.',
            'Legal instrument: '.$version->stringAttribute('legal_instrument').', '.$version->stringAttribute('section_reference').'.',
            'Publication date on the rule: '.($version->dateString('publication_date') ?? 'not recorded').'. Legal effective date on the rule: '.($version->dateString('legal_effective_date') ?? 'not recorded').'. Those dates are not the same field as the version\'s own effective dates.',
            'Source: '.$version->stringAttribute('source_url').'.',
        ];
    }

    private function label(string $taxType): string
    {
        return TaxType::label($taxType);
    }
}
