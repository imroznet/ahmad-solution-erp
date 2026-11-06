<?php

namespace App\Tax;

use RuntimeException;

/**
 * A tax rule or tax document action was refused. The field matches a form input.
 */
final class TaxRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
