<?php

namespace App\Accounting;

enum MfsProvider: string
{
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';

    public function label(): string
    {
        return match ($this) {
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Rocket => 'Rocket',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $provider) {
            $options[$provider->value] = $provider->label();
        }

        return $options;
    }
}
