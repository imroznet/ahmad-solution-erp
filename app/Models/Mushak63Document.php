<?php

namespace App\Models;

use App\Models\Concerns\ReadsStoredValues;
use App\Tax\IssuanceGate;
use App\Tax\TaxDocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'commercial_invoice_id',
    'status',
    'supply_classification',
    'rule_code',
    'pinned_tax_rule_version_id',
    'pinned_rule_snapshot',
    'party_legal_name',
    'party_trading_name',
    'party_tin',
    'party_bin',
    'party_vat_registration_number',
    'quotation_number',
    'invoice_number',
    'challan_numbers',
    'invoice_total',
    'currency_code',
    'company_snapshot',
    'internal_notes',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class Mushak63Document extends Model
{
    use ReadsStoredValues;

    protected static function booted(): void
    {
        static::saving(function (Mushak63Document $document): void {
            if (! IssuanceGate::mushakIssueAllowed() && ($document->stringAttribute('status') === TaxDocumentStatus::ISSUED || $document->getAttribute('issued_at') !== null)) {
                throw new LogicException('Mushak 6.3 cannot be issued while the legal gate is closed.');
            }
        });

        static::updating(function (Mushak63Document $document): void {
            $original = $document->getOriginal('status');

            if ($original === TaxDocumentStatus::VOIDED) {
                throw new LogicException('A voided Mushak working paper cannot be changed.');
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
            'pinned_rule_snapshot' => 'array',
            'challan_numbers' => 'array',
            'company_snapshot' => 'array',
            'invoice_total' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommercialInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'commercial_invoice_id');
    }

    /**
     * @return HasMany<Mushak63Line, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Mushak63Line::class)->orderBy('position');
    }

    /**
     * @return HasMany<Mushak63Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Mushak63Event::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<TaxRuleVersion, $this>
     */
    public function pinnedRule(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'pinned_tax_rule_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
