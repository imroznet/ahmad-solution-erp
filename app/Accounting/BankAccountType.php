<?php

namespace App\Accounting;

enum BankAccountType: string
{
    case Savings = 'savings';
    case Current = 'current';

    public function label(): string
    {
        return match ($this) {
            self::Savings => 'Savings',
            self::Current => 'Current',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
