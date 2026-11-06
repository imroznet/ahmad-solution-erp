<?php

namespace App\Tax;

use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\Mushak63Line;
use App\Models\User;

/**
 * Prepares Mushak lines from the posted base, percentage, or inclusive amount.
 * The VAT amount is never taken from the request. Supplementary duty is stored
 * beside the VAT figures and is not part of the VAT amount.
 */
final class MushakLines
{
    public function __construct(private readonly VatPriceCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, Mushak63Line>  $previous
     * @return array{lines: list<array<string, mixed>>, base_total: string, vat_total: ?string, inclusive_total: ?string, supplementary_duty_total: ?string, duties_total: ?string}
     */
    public function prepare(CommercialInvoice $invoice, array $input, array $previous, User $actor): array
    {
        $submitted = $input['lines'] ?? null;

        if (! is_array($submitted) || $submitted === []) {
            throw new TaxRuleException('lines', 'Include every line of the commercial invoice.');
        }

        $invoice->loadMissing('lines');
        $expected = [];

        foreach ($invoice->lines as $line) {
            $expected[(int) $line->getKey()] = $line;
        }

        $seen = [];
        $prepared = [];
        $position = 0;

        foreach ($submitted as $index => $row) {
            if (! is_array($row)) {
                throw new TaxRuleException('lines', 'Each line must be a set of fields.');
            }

            $prefix = 'lines.'.$index.'.';
            $lineId = is_numeric($row['commercial_invoice_line_id'] ?? null) ? (int) $row['commercial_invoice_line_id'] : 0;
            $source = $expected[$lineId] ?? null;

            if (! $source instanceof CommercialInvoiceLine || isset($seen[$lineId])) {
                throw new TaxRuleException($prefix.'commercial_invoice_line_id', 'Each current commercial invoice line must appear once.');
            }

            $seen[$lineId] = true;
            $position++;
            $prepared[] = $this->line($prefix, $position, $source, $row, $previous[$lineId] ?? null, $actor);
        }

        if (count($seen) !== count($expected)) {
            throw new TaxRuleException('lines', 'Include every current line of the commercial invoice.');
        }

        return [
            'lines' => $prepared,
            ...$this->totals($prepared),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function line(string $prefix, int $position, CommercialInvoiceLine $source, array $row, ?Mushak63Line $previous, User $actor): array
    {
        $description = trim(is_string($row['description'] ?? null) ? $row['description'] : '');
        $unit = trim(is_string($row['unit'] ?? null) ? $row['unit'] : '');

        if ($description === '' || mb_strlen($description) > 2000) {
            throw new TaxRuleException($prefix.'description', 'Enter the goods or service description.');
        }

        if ($unit === '' || mb_strlen($unit) > 40) {
            throw new TaxRuleException($prefix.'unit', 'Enter the unit of supply.');
        }

        $driver = is_string($row['driver'] ?? null) ? $row['driver'] : 'base';

        if (! in_array($driver, ['base', 'inclusive', 'rate', 'components'], true)) {
            $driver = 'base';
        }

        $rate = $this->attempt($prefix, fn (): ?string => $this->calculator->rate(
            is_string($row['vat_rate'] ?? null) ? $row['vat_rate'] : null,
            'vat_rate',
        ));
        $storedRate = $previous?->nullableDecimal('vat_rate_percent');

        if ($storedRate !== null && $rate !== null && $storedRate !== $rate && $driver === 'inclusive') {
            $driver = 'rate';
        }

        $quantity = $this->attempt($prefix, fn (): string => $this->calculator->quantity(
            is_string($row['quantity'] ?? null) ? $row['quantity'] : '',
            'quantity',
        ));
        $unitPrice = $this->attempt($prefix, fn (): string => $this->calculator->money(
            is_string($row['unit_price'] ?? null) ? $row['unit_price'] : '',
            'unit_price',
            'Unit price',
        ));

        if ($driver === 'components') {
            $calculation = $this->attempt($prefix, fn (): VatLineCalculation => $this->calculator->fromQuantity($quantity, $unitPrice, $rate));
            $storedDriver = 'components';
        } elseif ($driver === 'inclusive') {
            $calculation = $this->attempt($prefix, fn (): VatLineCalculation => $this->calculator->reverse(
                is_string($row['inclusive_amount'] ?? null) ? $row['inclusive_amount'] : '',
                $rate ?? '',
            ));
            $storedDriver = 'inclusive';
        } else {
            $calculation = $this->attempt($prefix, fn (): VatLineCalculation => $this->calculator->forward(
                is_string($row['base_amount'] ?? null) ? $row['base_amount'] : '',
                $rate,
            ));
            $storedDriver = $calculation->calculated ? ($driver === 'rate' ? 'rate' : 'base') : 'none';
        }

        $duty = $this->attempt($prefix, fn (): ?string => $this->calculator->optionalMoney(
            is_string($row['supplementary_duty_amount'] ?? null) ? $row['supplementary_duty_amount'] : null,
            'supplementary_duty_amount',
            'Supplementary duty',
        ));
        $duties = null;

        if ($calculation->inclusive !== null) {
            $duties = $this->attempt($prefix, fn (): string => $this->calculator->sum(
                [$calculation->inclusive, $duty ?? '0.00'],
                'duties_amount',
                'Value including duties and taxes',
            ));
        }

        $rateEnteredBy = null;
        $rateEnteredAt = null;

        if ($calculation->rate !== null) {
            if ($previous instanceof Mushak63Line && $previous->nullableDecimal('vat_rate_percent') === $calculation->rate && $previous->getAttribute('rate_entered_at') !== null) {
                $rateEnteredBy = $previous->getAttribute('rate_entered_by');
                $rateEnteredAt = $previous->getAttribute('rate_entered_at');
            } else {
                $rateEnteredBy = $actor->getKey();
                $rateEnteredAt = now();
            }
        }

        return [
            'commercial_invoice_line_id' => $source->getKey(),
            'position' => $position,
            'description' => $description,
            'unit' => $unit,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'base_amount' => $calculation->base,
            'vat_rate_percent' => $calculation->rate,
            'vat_amount' => $calculation->vat,
            'inclusive_amount' => $calculation->inclusive,
            'supplementary_duty_amount' => $duty,
            'duties_amount' => $duties,
            'calculation_driver' => $storedDriver,
            'rate_entered_by' => $rateEnteredBy,
            'rate_entered_at' => $rateEnteredAt,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{base_total: string, vat_total: ?string, inclusive_total: ?string, supplementary_duty_total: ?string, duties_total: ?string}
     */
    private function totals(array $lines): array
    {
        $bases = [];
        $vats = [];
        $inclusives = [];
        $duties = [];
        $dutyParts = [];
        $anyDuty = false;
        $complete = true;

        foreach ($lines as $line) {
            $bases[] = (string) $line['base_amount'];

            if (! is_string($line['vat_amount']) || ! is_string($line['inclusive_amount']) || ! is_string($line['duties_amount'])) {
                $complete = false;
            } else {
                $vats[] = $line['vat_amount'];
                $inclusives[] = $line['inclusive_amount'];
                $duties[] = $line['duties_amount'];
            }

            if (is_string($line['supplementary_duty_amount'])) {
                $anyDuty = true;
                $dutyParts[] = $line['supplementary_duty_amount'];
            } else {
                $dutyParts[] = '0.00';
            }
        }

        return [
            'base_total' => $this->calculator->sum($bases, 'base_total', 'Base total'),
            'vat_total' => $complete ? $this->calculator->sum($vats, 'vat_total', 'VAT total') : null,
            'inclusive_total' => $complete ? $this->calculator->sum($inclusives, 'inclusive_total', 'VAT-inclusive total') : null,
            'supplementary_duty_total' => $anyDuty ? $this->calculator->sum($dutyParts, 'supplementary_duty_total', 'Supplementary duty total') : null,
            'duties_total' => $complete ? $this->calculator->sum($duties, 'duties_total', 'Total including duties and taxes') : null,
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function attempt(string $prefix, callable $action): mixed
    {
        try {
            return $action();
        } catch (TaxRuleException $exception) {
            throw new TaxRuleException($prefix.$exception->field, $exception->getMessage());
        }
    }
}
