<?php

namespace App\Parties;

final class PartyStatus
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived',
        ];
    }

    public static function label(string $status): string
    {
        return self::options()[$status] ?? $status;
    }
}
