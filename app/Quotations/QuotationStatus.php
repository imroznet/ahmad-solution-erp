<?php

namespace App\Quotations;

/**
 * Lifecycle of one quotation revision.
 *
 * A new revision does not change the previous revision's status. The quotation
 * header follows the current revision only.
 */
final class QuotationStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const SENT = 'sent';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /**
     * Statuses from which a new revision may be opened. The previous revision stays as it is.
     *
     * @var list<string>
     */
    public const REVISABLE = [
        self::APPROVED,
        self::SENT,
        self::REJECTED,
        self::EXPIRED,
    ];

    /**
     * @var list<string>
     */
    public const TERMINAL = [
        self::ACCEPTED,
        self::REJECTED,
        self::EXPIRED,
        self::CANCELLED,
    ];

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::DRAFT => [self::SUBMITTED, self::CANCELLED],
        self::SUBMITTED => [self::APPROVED, self::DRAFT, self::CANCELLED],
        self::APPROVED => [self::SENT, self::EXPIRED, self::CANCELLED],
        self::SENT => [self::ACCEPTED, self::REJECTED, self::EXPIRED, self::CANCELLED],
        self::ACCEPTED => [],
        self::REJECTED => [],
        self::EXPIRED => [],
        self::CANCELLED => [],
    ];

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::APPROVED,
            self::SENT,
            self::ACCEPTED,
            self::REJECTED,
            self::EXPIRED,
            self::CANCELLED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted for approval',
            self::APPROVED => 'Approved',
            self::SENT => 'Sent',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
            default => $status,
        };
    }
}
