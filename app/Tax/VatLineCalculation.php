<?php

namespace App\Tax;

/**
 * One line after the two-way VAT calculation.
 *
 * A null rate, VAT amount, or inclusive amount means the percentage was blank
 * and nothing was calculated. Zero is a stored number, not a blank.
 */
final readonly class VatLineCalculation
{
    public function __construct(
        public string $base,
        public ?string $rate,
        public ?string $vat,
        public ?string $inclusive,
        public bool $calculated,
        public string $driver,
    ) {}

    /**
     * @return array{base: string, rate: ?string, vat: ?string, inclusive: ?string, calculated: bool, driver: string}
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'rate' => $this->rate,
            'vat' => $this->vat,
            'inclusive' => $this->inclusive,
            'calculated' => $this->calculated,
            'driver' => $this->driver,
        ];
    }
}
