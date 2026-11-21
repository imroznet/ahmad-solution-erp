<?php

namespace App\Accounting;

enum CashFlowCategory: string
{
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';

    public function label(): string
    {
        return match ($this) {
            self::Operating => 'Operating',
            self::Investing => 'Investing',
            self::Financing => 'Financing',
        };
    }

    public static function fromInput(mixed $value): self
    {
        if ($value === null || $value === '') {
            return self::Operating;
        }

        if (! is_string($value)) {
            throw new AccountingException('Choose an operating, investing, or financing classification.');
        }

        $category = self::tryFrom($value);

        if (! $category instanceof self) {
            throw new AccountingException('Choose an operating, investing, or financing classification.');
        }

        return $category;
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Operating, self::Investing, self::Financing];
    }
}
