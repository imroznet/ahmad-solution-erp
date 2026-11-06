<?php

namespace App\Documents;

use App\Models\QuotationRevision;

/**
 * Copies the customer identity already stored on the accepted revision.
 * A later edit to the party does not change this copy.
 */
final class DocumentCustomerSnapshot
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
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
    ];

    /**
     * @return array<string, mixed>
     */
    public function fromRevision(QuotationRevision $revision): array
    {
        $snapshot = [];

        foreach (self::COLUMNS as $column) {
            $value = $revision->getAttribute($column);

            if ($column === 'billing_address' || $column === 'shipping_address') {
                $snapshot[$column] = is_array($value) ? $value : null;

                continue;
            }

            $snapshot[$column] = is_string($value) && $value !== '' ? $value : (in_array($column, ['party_legal_name', 'party_kind'], true) ? (is_string($value) ? $value : '') : null);
        }

        return $snapshot;
    }
}
