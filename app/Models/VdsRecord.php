<?php

namespace App\Models;

use App\Models\Concerns\WithholdingRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'direction',
    'status',
    'counterparty_source',
    'party_id',
    'counterparty_name',
    'counterparty_bin',
    'counterparty_tin',
    'mushak_document_id',
    'commercial_invoice_id',
    'underlying_reference',
    'basis_amount',
    'deducted_amount',
    'record_date',
    'certificate_number',
    'treasury_reference',
    'tax_rule_id',
    'rule_snapshot',
    'tax_review_status',
    'notes',
    'internal_notes',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
    'updated_by',
])]
class VdsRecord extends Model
{
    use WithholdingRecord;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->withholdingCasts();
    }
}
