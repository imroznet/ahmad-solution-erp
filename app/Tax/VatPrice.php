<?php

namespace App\Tax;

/**
 * One calculated line at the selected precision.
 *
 * When the rate is blank, VAT and the final amount are null. A stored zero
 * rate is an explicit entry, not a blank.
 */
final readonly class VatPrice
{
    public function __construct(
        public string $baseAmount,
        public ?string $rate,
        public ?string $vatAmount,
        public string $adjustmentAmount,
        public ?string $finalAmount,
        public string $residualAmount,
        public string $origin,
        public int $precision,
        public string $roundingMethod,
    ) {}

    public function rateIsBlank(): bool
    {
        return $this->rate === null;
    }
}
