<?php

namespace App\Tax;

use App\Models\TaxRule;
use App\Models\TaxRuleVersion;
use Illuminate\Support\Collection;

/**
 * Picks the single approved rule version whose scope covers a date.
 *
 * Scope is the tax type plus the classification text. An empty classification
 * is its own scope. It is not a wildcard and it does not apply to every supply.
 * The version's own percentage or fixed amount is never returned as a rate to apply.
 */
final class TaxRuleMatcher
{
    public function explain(string $taxType, string $classification, string $date): TaxRuleMatch
    {
        if (! in_array($taxType, TaxType::all(), true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        $key = self::classificationKey($classification);
        $versions = TaxRuleVersion::query()
            ->with('rule')
            ->where('status', TaxRuleStatus::APPROVED)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->whereHas('rule', function ($query) use ($taxType, $key): void {
                $query->where('tax_type', $taxType)->where('classification_key', $key);
            })
            ->orderBy('id')
            ->get();

        return $this->describe($versions, $key, $date);
    }

    public static function classificationKey(string $value): string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? '' : mb_strtolower($trimmed);
    }

    /**
     * @param  Collection<int, TaxRuleVersion>  $versions
     */
    private function describe(Collection $versions, string $key, string $date): TaxRuleMatch
    {
        $scope = $key === '' ? 'no supply classification' : 'classification "'.$key.'"';

        if ($versions->count() > 1) {
            $codes = $versions->map(function (TaxRuleVersion $version): string {
                $rule = $version->rule;

                return $rule instanceof TaxRule ? $rule->stringAttribute('rule_code') : 'rule';
            })->implode(', ');

            return new TaxRuleMatch(
                null,
                "More than one approved rule covers {$date} for {$scope}: {$codes}. Retire or close one so the date is unambiguous. No percentage was applied.",
                true,
            );
        }

        $version = $versions->first();

        if (! $version instanceof TaxRuleVersion) {
            return new TaxRuleMatch(
                null,
                "No approved rule covers {$date} for {$scope}. No percentage was applied. The document stays in manual tax review until someone records a reason.",
                false,
            );
        }

        $rule = $version->rule;
        $code = $rule instanceof TaxRule ? $rule->stringAttribute('rule_code') : 'rule';
        $text = "Matched {$code} version {$version->version_number}, effective ".($version->dateAttribute('effective_from') ?? '');
        $until = $version->dateAttribute('effective_to');
        $text .= $until === null ? ', with no end date.' : ", through {$until}.";
        $text .= ' '.$this->unusedRate($version);

        return new TaxRuleMatch($version, $text, false);
    }

    private function unusedRate(TaxRuleVersion $version): string
    {
        $kind = $version->stringAttribute('rate_kind');

        if ($kind === 'percentage' && $version->rate_value !== null) {
            return 'The rule record contains a percentage of '.$version->decimalAttribute('rate_value').'. That percentage was not copied onto the document.';
        }

        if ($kind === 'fixed' && $version->fixed_amount !== null) {
            return 'The rule record contains a fixed amount of '.$version->decimalAttribute('fixed_amount').'. That amount was not copied onto the document.';
        }

        return 'The rule has no stored percentage. The document percentage, if any, is entered on the document.';
    }
}
