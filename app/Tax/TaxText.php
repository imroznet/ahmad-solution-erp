<?php

namespace App\Tax;

final class TaxText
{
    /**
     * Classifications that would act as a silent default. A real service or
     * supply code is required instead.
     *
     * @var list<string>
     */
    private const REJECTED = [
        'default',
        'standard',
        'all',
        'any',
        'general',
        'vat',
        'output vat',
        '15',
        '15%',
        '15 percent',
        'universal',
    ];

    public static function classification(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return is_string($normalized) ? $normalized : '';
    }

    public static function ruleCode(?string $value): string
    {
        return self::classification($value ?? '');
    }

    public static function rejectsClassification(string $value): bool
    {
        return in_array(strtolower($value), self::REJECTED, true);
    }

    public static function httpsUrl(string $value): bool
    {
        if (strlen($value) > 500 || ! str_starts_with($value, 'https://')) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
