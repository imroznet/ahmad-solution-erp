<?php

namespace App\Tax;

use App\Models\TaxRule;
use Illuminate\Support\Collection;

/**
 * Explains which approved rule matches a date. The match is not a VAT
 * percentage and is never copied onto a Mushak line.
 */
final class TaxRulePreview
{
    /**
     * @return array{status: string, message: string, rules: Collection<int, TaxRule>}
     */
    public function explain(string $taxType, ?string $classification, string $onDate): array
    {
        if (! in_array($taxType, TaxType::ALL, true)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate) !== 1) {
            throw new TaxRuleException('on_date', 'Enter the date to explain.');
        }

        $scope = TaxRuleWindow::normalizeClassification($classification);
        $rules = TaxRule::query()
            ->where('tax_type', $taxType)
            ->where('supply_classification', $scope)
            ->where('approval_status', 'approved')
            ->where('activity_status', 'active')
            ->whereDate('effective_from', '<=', $onDate)
            ->where(function ($query) use ($onDate): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $onDate);
            })
            ->orderBy('id')
            ->get();

        $scopeLabel = $scope === '' ? 'the unspecified classification' : 'classification "'.$scope.'"';

        if ($rules->count() === 1 && $rules->first() instanceof TaxRule) {
            $rule = $rules->first();

            return [
                'status' => 'matched',
                'message' => 'Approved '.$rule->stringAttribute('rule_code').' version '.$rule->getAttribute('version').' matches '.$scopeLabel.' on '.$onDate.'. This entry explains a source. It is not applied as a VAT percentage.',
                'rules' => $rules,
            ];
        }

        if ($rules->count() > 1) {
            return [
                'status' => 'ambiguous',
                'message' => 'More than one active approved rule matches '.$scopeLabel.' on '.$onDate.'. The treatment stays in review. No percentage was selected.',
                'rules' => $rules,
            ];
        }

        return [
            'status' => 'review_required',
            'message' => 'No active approved rule matches '.$scopeLabel.' on '.$onDate.'. The treatment stays in review. No percentage was selected.',
            'rules' => $rules,
        ];
    }
}
