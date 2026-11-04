<?php

namespace App\Company;

/**
 * Registered office and corporate/head office are different places.
 * Nothing in this list copies one into the other.
 */
final class AddressType
{
    public const REGISTERED_OFFICE = 'registered_office';

    public const CORPORATE_OFFICE = 'corporate_office';

    public const BRANCH_OFFICE = 'branch_office';

    public const PROJECT_OFFICE = 'project_office';

    public const FACTORY = 'factory';

    public const OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::REGISTERED_OFFICE => 'Registered Office',
            self::CORPORATE_OFFICE => 'Corporate/Head Office',
            self::BRANCH_OFFICE => 'Branch Office',
            self::PROJECT_OFFICE => 'Project Office',
            self::FACTORY => 'Factory/Workshop',
            self::OTHER => 'Other',
        ];
    }

    /**
     * Types that may have only one active row at a time.
     *
     * @return list<string>
     */
    public static function exclusiveActive(): array
    {
        return [
            self::REGISTERED_OFFICE,
            self::CORPORATE_OFFICE,
        ];
    }

    public static function label(string $type): string
    {
        return self::options()[$type] ?? $type;
    }
}
