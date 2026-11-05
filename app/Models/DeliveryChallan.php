<?php

namespace App\Models;

use App\Documents\DocumentStatus;
use App\Models\Concerns\ReadsStoredValues;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'number',
    'quotation_id',
    'quotation_revision_id',
    'status',
    'challan_date',
    'delivery_location',
    'recipient_name',
    'recipient_phone',
    'vehicle_or_carrier',
    'carrier_reference',
    'party_legal_name',
    'party_trading_name',
    'party_kind',
    'party_tin',
    'party_bin',
    'party_vat_registration_number',
    'party_email',
    'party_phone',
    'contact_name',
    'contact_designation',
    'contact_email',
    'contact_phone',
    'billing_address',
    'shipping_address',
    'customer_notes',
    'internal_notes',
    'company_snapshot',
    'issued_at',
    'issued_by',
    'dispatched_at',
    'dispatched_by',
    'acknowledged_at',
    'acknowledged_by',
    'acknowledgment_name',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class DeliveryChallan extends Model
{
    use ReadsStoredValues;

    /**
     * Columns that may change after a challan leaves draft.
     * The number, customer snapshot, lines, and company snapshot are not in this list.
     *
     * @var list<string>
     */
    public const MUTABLE_AFTER_ISSUE = [
        'status',
        'updated_at',
        'dispatched_at',
        'dispatched_by',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledgment_name',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (DeliveryChallan $challan): void {
            $original = $challan->getOriginal('status');

            if (! is_string($original) || $original === DocumentStatus::DRAFT) {
                return;
            }

            $forbidden = array_values(array_diff(array_keys($challan->getDirty()), self::MUTABLE_AFTER_ISSUE));

            if ($forbidden !== []) {
                throw new LogicException('An issued delivery challan cannot change its content.');
            }
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challan_date' => 'date',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'company_snapshot' => 'array',
            'issued_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<QuotationRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'quotation_revision_id');
    }

    /**
     * @return HasMany<DeliveryChallanLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryChallanLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<DeliveryChallanEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeliveryChallanEvent::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function statusLabel(): string
    {
        return DocumentStatus::label($this->stringAttribute('status'));
    }

    public function displayNumber(): string
    {
        $number = $this->stringAttribute('number');

        return $number !== '' ? $number : 'Draft';
    }
}
