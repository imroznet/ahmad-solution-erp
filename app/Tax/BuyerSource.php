<?php

namespace App\Tax;

final class BuyerSource
{
    public const PARTY = 'party';

    public const MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::PARTY => 'Existing customer',
            self::MANUAL => 'One-off buyer, not saved as a customer',
        ];
    }
}
