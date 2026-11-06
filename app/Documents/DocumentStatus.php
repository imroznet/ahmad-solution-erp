<?php

namespace App\Documents;

/**
 * Shared lifecycle for a delivery challan and a commercial invoice.
 *
 * Dispatch and acknowledgment are events on an issued challan. They are not statuses.
 * A PDF download does not change this status.
 */
final class DocumentStatus
{
    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const VOIDED = 'voided';

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::ISSUED => 'Issued',
            self::VOIDED => 'Voided',
            default => $status,
        };
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::DRAFT, self::ISSUED, self::VOIDED];
    }
}
