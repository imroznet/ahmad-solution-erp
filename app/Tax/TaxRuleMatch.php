<?php

namespace App\Tax;

use App\Models\TaxRuleVersion;

/**
 * The rule selected for a tax type, classification, and date.
 * A match does not supply a VAT percentage to a Mushak document.
 */
final readonly class TaxRuleMatch
{
    public function __construct(
        public ?TaxRuleVersion $version,
        public string $explanation,
        public bool $ambiguous,
    ) {}
}
