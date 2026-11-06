<?php

namespace App\Documents;

use App\Support\Decimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Delivered and invoiced quantities use the quotation scale of 4 decimal places.
 * Extra digits are rejected. Zero means the line is omitted.
 */
final class DocumentQuantity
{
    public function positive(string $value, string $field): string
    {
        $quantity = $this->exact($value, $field);
        $maximum = Decimal::of((string) config('asl.quotations.max_quantity', '9999999.9999'));

        if ($quantity->isNegative() || $quantity->isZero()) {
            throw new DocumentRuleException($field, 'Quantity must be greater than zero.');
        }

        if ($quantity->compare($maximum) > 0) {
            throw new DocumentRuleException($field, 'Quantity cannot exceed '.$maximum.'.');
        }

        return (string) $quantity->toScale(4);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{index: int, id: int, quantity: string}>
     */
    public function included(array $input, string $idKey, string $emptyMessage): array
    {
        $lines = $input['lines'] ?? null;

        if (! is_array($lines)) {
            throw new DocumentRuleException('lines', $emptyMessage);
        }

        $included = [];

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                throw new DocumentRuleException('lines', $emptyMessage);
            }

            $raw = trim((string) ($line['quantity'] ?? ''));
            $position = is_int($index) ? $index : count($included);

            if ($raw === '' || $this->isZero($raw)) {
                continue;
            }

            $id = $line[$idKey] ?? null;

            if (! is_int($id) && ! (is_string($id) && preg_match('/^[1-9][0-9]*$/', $id) === 1)) {
                throw new DocumentRuleException('lines.'.$position.'.'.$idKey, 'Choose a line.');
            }

            $included[] = [
                'index' => $position,
                'id' => (int) $id,
                'quantity' => $this->positive($raw, 'lines.'.$position.'.quantity'),
            ];
        }

        $maximum = (int) config('asl.quotations.max_lines', 50);

        if (count($included) > $maximum) {
            throw new DocumentRuleException('lines', 'A document can contain at most '.$maximum.' lines.');
        }

        if ($included === []) {
            throw new DocumentRuleException('lines', $emptyMessage);
        }

        return $included;
    }

    private function isZero(string $value): bool
    {
        try {
            return Decimal::of($value)->isZero();
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function exact(string $value, string $field): Decimal
    {
        try {
            $decimal = Decimal::of(trim($value));
        } catch (InvalidArgumentException) {
            throw new DocumentRuleException($field, 'Enter a plain decimal number.');
        }

        try {
            return $decimal->toScale(4, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw new DocumentRuleException($field, 'Use at most 4 decimal places.');
        }
    }
}
