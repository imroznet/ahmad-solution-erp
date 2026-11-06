<?php

namespace App\Tax;

/**
 * Output VAT, VDS, and income-tax withholding are different kinds of tax.
 * A row of one kind is never a row of another.
 */
final class TaxType
{
    public const OUTPUT_VAT = 'output_vat';

    public const VDS = 'vds';

    public const AIT = 'ait';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::OUTPUT_VAT => 'Output VAT',
            self::VDS => 'VDS',
            self::AIT => 'AIT / income-tax withholding',
        ];
    }

    public static function label(string $type): string
    {
        return self::options()[$type] ?? $type;
    }

    public static function isKnown(string $type): bool
    {
        return isset(self::options()[$type]);
    }
}
