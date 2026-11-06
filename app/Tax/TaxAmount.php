<?php

namespace App\Tax;

use App\Quotations\QuotationCalculator;
use App\Support\Decimal;
use App\Support\Money;

/**
 * Parses typed tax amounts. Blank stays null. Nothing here multiplies a rate.
 */
final class TaxAmount
{
    public static function money(mixed $value, string $field): ?string
    {
        $text = self::text($value, $field, 'Enter an amount in taka with up to 2 decimal places.');

        if ($text === null) {
            return null;
        }

        if (preg_match('/^(\d+\.\d{2})0{1,2}$/', $text, $parts) === 1) {
            $text = $parts[1];
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $text) !== 1) {
            throw new TaxRuleException($field, 'Enter an amount in taka with up to 2 decimal places.');
        }

        $amount = Money::of($text)->amount();

        if (Decimal::of($amount)->compare(Decimal::of(QuotationCalculator::TOTAL_MAX)) > 0) {
            throw new TaxRuleException($field, 'The amount cannot exceed '.QuotationCalculator::TOTAL_MAX.' BDT.');
        }

        return $amount;
    }

    public static function quantity(mixed $value, string $field): ?string
    {
        $text = self::text($value, $field, 'Enter a quantity greater than zero, with up to 4 decimal places.');

        if ($text === null) {
            return null;
        }

        if (preg_match('/^\d+(\.\d{1,4})?$/', $text) !== 1) {
            throw new TaxRuleException($field, 'Enter a quantity greater than zero, with up to 4 decimal places.');
        }

        $quantity = Decimal::of($text)->toScale(4);

        if ($quantity->isZero() || $quantity->isNegative()) {
            throw new TaxRuleException($field, 'Quantity must be greater than zero.');
        }

        if ($quantity->compare(Decimal::of('9999999.9999')) > 0) {
            throw new TaxRuleException($field, 'Quantity cannot exceed 9999999.9999.');
        }

        return (string) $quantity;
    }

    public static function percentage(mixed $value, string $field): ?string
    {
        $text = self::text($value, $field, 'Enter a percentage from 0 to 100, or leave it blank.');

        if ($text === null) {
            return null;
        }

        if (preg_match('/^\d+(\.\d{1,4})?$/', $text) !== 1) {
            throw new TaxRuleException($field, 'Enter a percentage from 0 to 100 with up to 4 decimal places, or leave it blank.');
        }

        $rate = Decimal::of($text);

        if ($rate->compare(Decimal::of('100')) > 0) {
            throw new TaxRuleException($field, 'Enter a percentage from 0 to 100, or leave it blank. This is not a statutory rate.');
        }

        return (string) $rate->toScale(4);
    }

    public static function date(mixed $value, string $field, bool $required = false): ?string
    {
        $text = self::text($value, $field, 'Enter a date as YYYY-MM-DD.');

        if ($text === null) {
            if ($required) {
                throw new TaxRuleException($field, 'Enter a date as YYYY-MM-DD.');
            }

            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $parts) !== 1) {
            throw new TaxRuleException($field, 'Enter a date as YYYY-MM-DD.');
        }

        if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new TaxRuleException($field, 'Enter a real date.');
        }

        return $text;
    }

    public static function time(mixed $value, string $field): ?string
    {
        $text = self::text($value, $field, 'Enter a time as HH:MM.');

        if ($text === null) {
            return null;
        }

        if (preg_match('/^(\d{2}):(\d{2})$/', $text, $parts) !== 1) {
            throw new TaxRuleException($field, 'Enter a time as HH:MM.');
        }

        $hour = (int) $parts[1];
        $minute = (int) $parts[2];

        if ($hour > 23 || $minute > 59) {
            throw new TaxRuleException($field, 'Enter a real time as HH:MM.');
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private static function text(mixed $value, string $field, string $message): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            throw new TaxRuleException($field, $message);
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
