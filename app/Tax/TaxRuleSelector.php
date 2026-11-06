<?php

namespace App\Tax;

use App\Models\TaxRule;
use Illuminate\Support\Collection;

/**
 * Finds an approved rule for an explanation. It never returns a rate to apply.
 */
final class TaxRuleSelector
{
    /**
     * @return array{outcome: string, reason: string, rules: Collection<int, TaxRule>}
     */
    public function explain(string $taxType, string $on, ?string $ruleKey = null, ?string $classification = null): array
    {
        if (! TaxType::isKnown($taxType)) {
            throw new TaxRuleException('tax_type', 'Choose output VAT, VDS, or AIT.');
        }

        $on = TaxAmount::date($on, 'on', true);
        $query = TaxRule::query()
            ->where('tax_type', $taxType)
            ->where('status', TaxRuleStatus::APPROVED)
            ->whereDate('effective_from', '<=', $on)
            ->where(function ($builder) use ($on): void {
                $builder->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on);
            });

        if (is_string($ruleKey) && trim($ruleKey) !== '') {
            $query->where('rule_key', strtoupper(trim($ruleKey)));
        }

        if (is_string($classification) && trim($classification) !== '') {
            $query->where('supply_classification', trim($classification));
        }

        /** @var Collection<int, TaxRule> $rules */
        $rules = $query->orderBy('rule_key')->orderBy('version')->get();

        if ($rules->isEmpty()) {
            return [
                'outcome' => 'none',
                'reason' => 'No approved '.TaxType::label($taxType).' rule covers '.$on.' for this scope. Tax review is required. No rate is applied.',
                'rules' => $rules,
            ];
        }

        if ($rules->count() > 1) {
            return [
                'outcome' => 'ambiguous',
                'reason' => 'More than one approved rule covers '.$on.'. No rate is applied. Choose a rule key.',
                'rules' => $rules,
            ];
        }

        $rule = $rules->first();

        return [
            'outcome' => 'matched',
            'reason' => 'Approved rule '.$rule->stringAttribute('rule_key').' version '.(int) $rule->getAttribute('version').' covers '.$on.'. Its configured rate is not copied onto a document.',
            'rules' => $rules,
        ];
    }
}
