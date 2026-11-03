<?php

namespace App\Support;

/**
 * Debug output is allowed only for local development and the test suite.
 * Staging and production stay dark even if APP_DEBUG is mistakenly true.
 */
final class DebugMode
{
    public static function enabled(?string $environment, bool $requested): bool
    {
        return $requested && in_array($environment, ['local', 'testing'], true);
    }
}
