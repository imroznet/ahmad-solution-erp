<?php

namespace App\Tax;

/**
 * A matched assessment is the result of one approved rule version.
 * review_required leaves the amount empty. It is not a zero-tax invoice.
 */
final readonly class TaxAssessment
{
    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>|null  $snapshot
     */
    public function __construct(
        public string $outcome,
        public string $taxType,
        public ?string $amount,
        public string $base,
        public ?int $ruleVersionId,
        public array $reasons,
        public ?array $snapshot,
    ) {}

    public function matched(): bool
    {
        return $this->outcome === 'matched';
    }
}
