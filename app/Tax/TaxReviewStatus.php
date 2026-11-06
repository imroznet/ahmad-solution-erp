<?php

namespace App\Tax;

/**
 * A stored document is not tax-complete by itself.
 * Review required is the state after issue until someone records a treatment.
 */
final class TaxReviewStatus
{
    public const REVIEW_REQUIRED = 'review_required';

    public const TREATMENT_RECORDED = 'treatment_recorded';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::REVIEW_REQUIRED => 'Tax review required',
            self::TREATMENT_RECORDED => 'Treatment recorded',
        ];
    }

    public static function label(string $status): string
    {
        return self::options()[$status] ?? $status;
    }
}
