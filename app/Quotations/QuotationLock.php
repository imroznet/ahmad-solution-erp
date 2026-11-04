<?php

namespace App\Quotations;

use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Support\Money;

/**
 * Row locks used by numbering, revision, and status changes.
 * Call these inside a database transaction.
 */
final class QuotationLock
{
    /**
     * @return array{0: Quotation, 1: QuotationRevision}
     */
    public function current(int|string $quotationId): array
    {
        $quotation = Quotation::query()->whereKey($quotationId)->lockForUpdate()->first();

        if (! $quotation instanceof Quotation || $quotation->current_revision_id === null) {
            throw new QuotationRuleException('quotation', 'This quotation could not be locked.');
        }

        $revision = QuotationRevision::query()->whereKey($quotation->current_revision_id)->lockForUpdate()->first();

        if (! $revision instanceof QuotationRevision || (int) $revision->quotation_id !== (int) $quotation->getKey()) {
            throw new QuotationRuleException('quotation', 'This quotation has no current revision.');
        }

        $quotation->setRelation('currentRevision', $revision);

        return [$quotation, $revision];
    }

    public function activeCustomer(int|string $partyId): Party
    {
        $party = Party::query()->whereKey($partyId)->lockForUpdate()->first();

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole('customer')) {
            throw new QuotationRuleException('party_id', 'Choose an active customer.');
        }

        if ($party->stringAttribute('currency_code') !== Money::DEFAULT_CURRENCY) {
            throw new QuotationRuleException('party_id', 'Quotations in this release are issued in BDT only.');
        }

        return $party;
    }
}
