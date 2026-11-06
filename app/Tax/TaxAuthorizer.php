<?php

namespace App\Tax;

use App\Models\AitRecord;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\User;
use App\Models\VdsRecord;
use App\Quotations\QuotationAuthorizer;

/**
 * A tax document follows the commercial invoice's customer visibility.
 * Holding the tax permission is not enough to open another customer's document.
 */
final class TaxAuthorizer
{
    public function __construct(private readonly QuotationAuthorizer $quotations) {}

    public function canViewMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.view') && $this->invoiceVisible($user, $document->invoice);
    }

    public function canUpdateMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.update')
            && $this->canViewMushak($user, $document)
            && $document->stringAttribute('status') === 'draft';
    }

    public function canIssueMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.issue')
            && $this->canViewMushak($user, $document)
            && $document->stringAttribute('status') === 'draft';
    }

    public function canVoidMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.void')
            && $this->canViewMushak($user, $document)
            && in_array($document->stringAttribute('status'), ['draft', 'issued'], true);
    }

    public function canDownloadMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.pdf') && $this->canViewMushak($user, $document);
    }

    public function canListMushak(User $user): bool
    {
        return $user->hasPermission('mushak.view') && $this->quotations->canAccessCustomerQuotations($user);
    }

    public function canUseInvoice(User $user, CommercialInvoice $invoice): bool
    {
        return $this->invoiceVisible($user, $invoice);
    }

    public function canUseMushak(User $user, Mushak63Document $document): bool
    {
        return $this->invoiceVisible($user, $document->invoice);
    }

    public function canViewVds(User $user, VdsRecord $record): bool
    {
        return $user->hasPermission('vds.view') && $this->linkedVisible($user, $record->invoice, $record->mushak);
    }

    public function canViewAit(User $user, AitRecord $record): bool
    {
        return $user->hasPermission('ait.view') && $this->linkedVisible($user, $record->invoice, $record->mushak);
    }

    private function invoiceVisible(User $user, ?CommercialInvoice $invoice): bool
    {
        if (! $invoice instanceof CommercialInvoice) {
            return false;
        }

        $invoice->loadMissing('quotation');
        $quotation = $invoice->quotation;

        return $quotation !== null && $this->quotations->canView($user, $quotation);
    }

    private function linkedVisible(User $user, ?CommercialInvoice $invoice, ?Mushak63Document $mushak): bool
    {
        if ($mushak instanceof Mushak63Document) {
            return $this->invoiceVisible($user, $mushak->invoice);
        }

        if ($invoice instanceof CommercialInvoice) {
            return $this->invoiceVisible($user, $invoice);
        }

        return $this->quotations->canAccessCustomerQuotations($user);
    }
}
