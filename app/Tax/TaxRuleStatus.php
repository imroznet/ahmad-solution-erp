<?php

namespace App\Tax;

final class TaxRuleStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const RETIRED = 'retired';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::DRAFT, self::APPROVED, self::RETIRED];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::RETIRED => 'Retired',
            default => $status,
        };
    }
}
