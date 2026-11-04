<?php

namespace App\Quotations;

use RuntimeException;

/**
 * A business rule stopped the quotation action. The field matches a form input.
 */
final class QuotationRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
