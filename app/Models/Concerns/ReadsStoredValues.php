<?php

namespace App\Models\Concerns;

trait ReadsStoredValues
{
    public function stringAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    public function dateString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        return null;
    }

    public function dateTimeString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function decimalAttribute(string $key): string
    {
        $value = $this->getAttribute($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return '0.00';
    }

    /**
     * @return list<string>
     */
    public function addressLines(string $attribute): array
    {
        $address = $this->getAttribute($attribute);

        if (! is_array($address)) {
            return [];
        }

        $lines = $address['lines'] ?? null;

        if (! is_array($lines)) {
            return [];
        }

        $result = [];

        foreach ($lines as $line) {
            if (is_string($line) && $line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }
}
