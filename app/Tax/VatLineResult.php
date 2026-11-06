<?php

namespace App\Tax;

/**
 * One line of the manual-rate VAT calculation.
 *
 * final = base + VAT + adjustment, at the selected precision, when a rate
 * is present. A blank rate leaves VAT and the final amount empty.
 */
final readonly class VatLineResult
{
    public function __construct(
        public string $base,
        public ?string $rate,
        public ?string $vat,
        public ?string $forwardVat,
        public bool $residualApplied,
        public string $adjustment,
        public ?string $final,
        public int $precision,
        public string $roundingMethod,
        public string $driver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'formula' => 'final = base + VAT + adjustment',
            'driver' => $this->driver,
            'precision' => $this->precision,
            'rounding_method' => $this->roundingMethod,
            'base' => $this->base,
            'rate' => $this->rate,
            'vat' => $this->vat,
            'forward_vat' => $this->forwardVat,
            'residual_applied' => $this->residualApplied,
            'adjustment' => $this->adjustment,
            'final' => $this->final,
            'legal_rate_determined' => false,
            'stages' => [
                'User-entered base, adjustment, and final are rounded once to the selected precision with the selected method.',
                'A percentage is not rounded. More than 4 decimal places is rejected.',
                'Forward VAT is base times the percentage, divided by 100, rounded once to the selected precision.',
                'When the final amount is edited, base is net divided by (1 + rate/100), rounded once. VAT is the residual net minus base so the entered final amount is kept.',
                'BDT presentation on Mushak 6.3 is a later half-up rounding to 2 decimal places and is not written back over these values.',
            ],
        ];
    }
}
