<?php

namespace App\Tax;

use App\Models\TaxRuleVersion;

/**
 * The rule that would apply on a date, or the reason none applies.
 * Draft and retired versions are not candidates.
 */
final readonly class TaxSelection
{
    /**
     * @param  list<string>  $reasons
     * @param  list<int>  $candidateIds
     */
    public function __construct(
        public string $outcome,
        public ?TaxRuleVersion $version,
        public array $reasons,
        public array $candidateIds,
    ) {}

    public function matched(): bool
    {
        return $this->outcome === 'matched' && $this->version instanceof TaxRuleVersion;
    }
}
