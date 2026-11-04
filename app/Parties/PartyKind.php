<?php

namespace App\Parties;

final class PartyKind
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'company' => 'Company',
            'individual' => 'Individual',
            'government' => 'Government',
            'partnership' => 'Partnership',
            'proprietorship' => 'Proprietorship',
            'other' => 'Other',
        ];
    }

    public static function label(string $kind): string
    {
        return self::options()[$kind] ?? $kind;
    }
}
