<?php

namespace App\Tax;

/**
 * Output VAT, VDS, and income-tax withholding are different kinds of rule.
 * A rule of one type is never applied as another type.
 */
final class TaxType
{
    public const OUTPUT_VAT = 'output_vat';

    public const VDS = 'vds';

    public const AIT = 'ait';

    public static function label(string $type): string
    {
        return match ($type) {
            self::OUTPUT_VAT => 'Output VAT',
            self::VDS => 'VDS',
            self::AIT => 'AIT / withholding income tax',
            default => $type,
        };
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::OUTPUT_VAT, self::VDS, self::AIT];
    }
}
