<?php

namespace App\Parties;

final class PartyRoleName
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'customer' => 'Customer',
            'supplier' => 'Supplier',
        ];
    }

    public static function label(string $role): string
    {
        return self::options()[$role] ?? $role;
    }
}
