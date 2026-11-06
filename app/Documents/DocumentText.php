<?php

namespace App\Documents;

final class DocumentText
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
            throw new DocumentRuleException($key, 'Enter text.');
        }

        $text = trim(strip_tags($value));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw new DocumentRuleException($key, 'This text is too long.');
        }

        return $text;
    }

    public function required(mixed $value, string $field, int $max): string
    {
        if (! is_string($value)) {
            throw new DocumentRuleException($field, 'Enter text.');
        }

        $text = trim(strip_tags($value));

        if ($text === '') {
            throw new DocumentRuleException($field, 'This field is required.');
        }

        if (mb_strlen($text) > $max) {
            throw new DocumentRuleException($field, 'This text is too long.');
        }

        return $text;
    }

    public function date(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new DocumentRuleException($field, 'Enter a date as YYYY-MM-DD.');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (! $parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $value) {
            throw new DocumentRuleException($field, 'Enter a real date.');
        }

        $latest = now()->timezone('Asia/Dhaka')->addYear()->toDateString();

        if ($value < '2000-01-01' || $value > $latest) {
            throw new DocumentRuleException($field, 'The date is outside the allowed range.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function id(array $input, string $key): int
    {
        $value = $input[$key] ?? null;

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int) $value;
        }

        throw new DocumentRuleException($key, 'Choose a document.');
    }
}
