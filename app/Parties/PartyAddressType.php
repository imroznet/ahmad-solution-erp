<?php

namespace App\Parties;

final class PartyAddressType
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'registered' => 'Registered',
            'office' => 'Office',
            'billing' => 'Billing',
            'shipping' => 'Shipping',
            'project_site' => 'Project/Site',
            'branch' => 'Branch',
            'other' => 'Other',
        ];
    }

    public static function label(string $type): string
    {
        return self::options()[$type] ?? $type;
    }
}
