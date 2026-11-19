<?php

namespace App\Units;

/**
 * The text stored on a document line. A missing field means "keep the copied unit".
 * A present field must be a trimmed label of at most 40 characters.
 */
final class UnitLabel
{
    public static function fromSubmitted(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(strip_tags($value));

        if ($text === '' || $text === '__other__' || mb_strlen($text) > 40) {
            return null;
        }

        return $text;
    }
}
