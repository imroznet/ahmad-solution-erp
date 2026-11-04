<?php

namespace App\Company;

final class VerificationStatus
{
    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::PENDING => 'Pending',
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
        ];
    }

    public static function label(string $status): string
    {
        return self::options()[$status] ?? $status;
    }
}
