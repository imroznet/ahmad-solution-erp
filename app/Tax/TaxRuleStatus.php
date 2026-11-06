<?php

namespace App\Tax;

final class TaxRuleStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const RETIRED = 'retired';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::RETIRED => 'Retired',
        ];
    }

    public static function label(string $status): string
    {
        return self::options()[$status] ?? $status;
    }
}
