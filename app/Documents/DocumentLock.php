<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Quotations\QuotationLock;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationStatus;

/**
 * Row locks for challans and commercial invoices.
 *
 * Call these inside a database transaction. The lock order is always:
 * 1. the quotation header, then its current revision
 * 2. challans in ascending id order, then the commercial invoice
 * 3. the number-sequence row, and only inside issue
 *
 * Operations that add delivered or invoiced quantity lock the quotation
 * first, so two writers for the same quotation cannot both pass the cap.
 */
final class DocumentLock
{
    public function __construct(private readonly QuotationLock $quotations) {}

    /**
     * @return array{0: Quotation, 1: QuotationRevision}
     */
    public function header(int $quotationId): array
    {
        try {
            return $this->quotations->current($quotationId);
        } catch (QuotationRuleException $exception) {
            throw new DocumentRuleException('quotation_id', $exception->getMessage());
        }
    }

    /**
     * @return array{0: Quotation, 1: QuotationRevision}
     */
    public function accepted(int $quotationId): array
    {
        [$quotation, $revision] = $this->header($quotationId);

        if ($quotation->stringAttribute('status') !== QuotationStatus::ACCEPTED
            || $revision->stringAttribute('status') !== QuotationStatus::ACCEPTED
            || (int) $quotation->current_revision_id !== (int) $revision->getKey()) {
            throw new DocumentRuleException(
                'quotation_id',
                'Only an accepted quotation can be used. This quotation is '.QuotationStatus::label($quotation->stringAttribute('status')).'.',
            );
        }

        $revision->load('lines');

        return [$quotation, $revision];
    }

    public function challan(int $id): DeliveryChallan
    {
        $challan = DeliveryChallan::query()->whereKey($id)->lockForUpdate()->first();

        if (! $challan instanceof DeliveryChallan) {
            throw new DocumentRuleException('challan', 'This delivery challan could not be locked.');
        }

        return $challan;
    }

    public function invoice(int $id): CommercialInvoice
    {
        $invoice = CommercialInvoice::query()->whereKey($id)->lockForUpdate()->first();

        if (! $invoice instanceof CommercialInvoice) {
            throw new DocumentRuleException('invoice', 'This commercial invoice could not be locked.');
        }

        return $invoice;
    }

    /**
     * @param  list<int>  $ids
     * @return list<DeliveryChallan>
     */
    public function challansInOrder(array $ids): array
    {
        $locked = [];

        foreach ($this->sorted($ids) as $id) {
            $locked[] = $this->challan($id);
        }

        return $locked;
    }

    /**
     * @param  list<int>  $ids
     * @return list<CommercialInvoice>
     */
    public function invoicesInOrder(array $ids): array
    {
        $locked = [];

        foreach ($this->sorted($ids) as $id) {
            $locked[] = $this->invoice($id);
        }

        return $locked;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        $unique = array_values(array_unique(array_map(intval(...), $ids)));
        sort($unique, SORT_NUMERIC);

        return $unique;
    }
}
