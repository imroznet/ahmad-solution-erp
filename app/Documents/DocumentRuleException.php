<?php

namespace App\Documents;

use RuntimeException;

/**
 * A business rule stopped the challan or invoice action. The field matches a form input.
 */
final class DocumentRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
