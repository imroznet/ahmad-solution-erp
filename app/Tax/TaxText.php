<?php

namespace App\Tax;

final class TaxText
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function nullable(array $input, string $key, int $max): ?string
    {
        $value = $input[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new TaxRuleException($key, 'Enter text.');
        }

        $text = trim(strip_tags($value));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw new TaxRuleException($key, 'This text is too long.');
        }

        return $text;
    }

    public function required(mixed $value, string $field, int $max): string
    {
        if (! is_string($value)) {
            throw new TaxRuleException($field, 'Enter text.');
        }

        $text = trim(strip_tags($value));

        if ($text === '') {
            throw new TaxRuleException($field, 'This field is required.');
        }

        if (mb_strlen($text) > $max) {
            throw new TaxRuleException($field, 'This text is too long.');
        }

        return $text;
    }
}
