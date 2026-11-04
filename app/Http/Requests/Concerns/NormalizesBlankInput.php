<?php

namespace App\Http\Requests\Concerns;

trait NormalizesBlankInput
{
    /**
     * @param  list<string>  $keys
     */
    protected function blankToNull(array $keys): void
    {
        $merged = [];

        foreach ($keys as $key) {
            $value = $this->input($key);

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            $merged[$key] = $trimmed === '' ? null : $trimmed;
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
