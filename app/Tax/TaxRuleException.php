<?php

namespace App\Tax;

use RuntimeException;

final class TaxRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
