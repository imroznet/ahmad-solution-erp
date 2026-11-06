<?php

namespace App\Models;

use App\Tax\TaxType;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'direction',
    'party_id',
    'commercial_invoice_id',
    'underlying_reference',
    'supply_classification',
    'rule_code',
    'basis_amount',
    'currency_code',
    'record_date',
    'pinned_tax_rule_version_id',
    'pinned_rule_snapshot',
    'certificate_reference',
    'treasury_reference',
    'party_legal_name',
    'party_tin',
    'party_bin',
    'company_snapshot',
    'internal_notes',
    'status',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class VdsRecord extends WithholdingRecord
{
    protected $table = 'vds_records';

    public function taxType(): string
    {
        return TaxType::VDS;
    }
}
