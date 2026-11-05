<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationStatus;

/**
 * Route middleware checks the document permission.
 * These methods decide which customer document that permission can open.
 * Opening a challan or invoice also requires access to its customer quotation.
 */
final class DocumentAuthorizer
{
    public function __construct(private readonly QuotationAuthorizer $quotations) {}

    public function canViewChallan(User $user, DeliveryChallan $challan): bool
    {
        $challan->loadMissing('quotation');

        return $user->hasPermission('challans.view') && $this->visibleQuotation($user, $challan->quotation);
    }

    public function canCreateChallan(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('challans.create')
            && $this->quotations->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::ACCEPTED;
    }

    public function canUpdateChallan(User $user, DeliveryChallan $challan): bool
    {
        return $user->hasPermission('challans.update')
            && $this->canViewChallan($user, $challan)
            && $challan->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canIssueChallan(User $user, DeliveryChallan $challan): bool
    {
        return $user->hasPermission('challans.issue')
            && $this->canViewChallan($user, $challan)
            && $challan->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canDispatchChallan(User $user, DeliveryChallan $challan): bool
    {
        return $user->hasPermission('challans.dispatch')
            && $this->canViewChallan($user, $challan)
            && $challan->stringAttribute('status') === DocumentStatus::ISSUED
            && $challan->dispatched_at === null;
    }

    public function canAcknowledgeChallan(User $user, DeliveryChallan $challan): bool
    {
        return $user->hasPermission('challans.acknowledge')
            && $this->canViewChallan($user, $challan)
            && $challan->stringAttribute('status') === DocumentStatus::ISSUED
            && $challan->acknowledged_at === null;
    }

    public function canVoidChallan(User $user, DeliveryChallan $challan): bool
    {
        return $user->hasPermission('challans.void')
            && $this->canViewChallan($user, $challan)
            && in_array($challan->stringAttribute('status'), [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true);
    }

    public function canDownloadChallan(User $user, DeliveryChallan $challan): bool
    {
        return $user->hasPermission('challans.pdf') && $this->canViewChallan($user, $challan);
    }

    public function canViewInvoice(User $user, CommercialInvoice $invoice): bool
    {
        $invoice->loadMissing('quotation');

        return $user->hasPermission('invoices.view') && $this->visibleQuotation($user, $invoice->quotation);
    }

    public function canCreateInvoice(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('invoices.create')
            && $this->quotations->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::ACCEPTED;
    }

    public function canUpdateInvoice(User $user, CommercialInvoice $invoice): bool
    {
        return $user->hasPermission('invoices.update')
            && $this->canViewInvoice($user, $invoice)
            && $invoice->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canIssueInvoice(User $user, CommercialInvoice $invoice): bool
    {
        return $user->hasPermission('invoices.issue')
            && $this->canViewInvoice($user, $invoice)
            && $invoice->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canVoidInvoice(User $user, CommercialInvoice $invoice): bool
    {
        return $user->hasPermission('invoices.void')
            && $this->canViewInvoice($user, $invoice)
            && in_array($invoice->stringAttribute('status'), [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true);
    }

    public function canDownloadInvoice(User $user, CommercialInvoice $invoice): bool
    {
        return $user->hasPermission('invoices.pdf') && $this->canViewInvoice($user, $invoice);
    }

    private function visibleQuotation(User $user, ?Quotation $quotation): bool
    {
        return $quotation instanceof Quotation && $this->quotations->canView($user, $quotation);
    }
}
