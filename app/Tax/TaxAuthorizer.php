<?php

namespace App\Tax;

use App\Models\MushakInvoice;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;

/**
 * Mushak visibility follows the parent quotation's customer visibility.
 * VDS and AIT screens are permission checks only. They are accounts records,
 * not customer-facing tax invoices.
 */
final class TaxAuthorizer
{
    public function __construct(private readonly QuotationAuthorizer $quotations) {}

    public function canViewMushak(User $user, MushakInvoice $mushak): bool
    {
        $mushak->loadMissing('quotation');

        return $user->hasPermission('mushak.view')
            && $mushak->quotation !== null
            && $this->quotations->canView($user, $mushak->quotation);
    }

    public function canUpdateMushak(User $user, MushakInvoice $mushak): bool
    {
        return $user->hasPermission('mushak.update')
            && $this->canViewMushak($user, $mushak)
            && $mushak->stringAttribute('status') === 'draft';
    }

    public function canIssueMushak(User $user, MushakInvoice $mushak): bool
    {
        return $user->hasPermission('mushak.issue')
            && $this->canViewMushak($user, $mushak)
            && $mushak->stringAttribute('status') === 'draft';
    }

    public function canVoidMushak(User $user, MushakInvoice $mushak): bool
    {
        return $user->hasPermission('mushak.void')
            && $this->canViewMushak($user, $mushak)
            && $mushak->stringAttribute('status') === 'issued';
    }

    public function canDownloadMushak(User $user, MushakInvoice $mushak): bool
    {
        return $user->hasPermission('mushak.pdf') && $this->canViewMushak($user, $mushak);
    }
}
