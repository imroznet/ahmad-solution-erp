<?php

namespace App\Tax;

/**
 * A Mushak 6.3 is never marked as a system-certified tax determination.
 * treatment_recorded means a user attested a selected approved rule.
 * The percentage on the lines is still the user's figure.
 */
final class TaxReviewStatus
{
    public const REVIEW_REQUIRED = 'review_required';

    public const TREATMENT_RECORDED = 'treatment_recorded';

    public static function label(string $status): string
    {
        return match ($status) {
            self::REVIEW_REQUIRED => 'Tax review required',
            self::TREATMENT_RECORDED => 'User recorded a legal treatment',
            default => $status,
        };
    }
}
