<?php

namespace App\Tax;

final class TaxDocumentStatus
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
}
