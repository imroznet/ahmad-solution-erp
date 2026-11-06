<?php

namespace App\Tax;

use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\User;

/**
 * Mushak visibility follows the linked commercial invoice.
 * A missing customer permission is a 404, same as a challan.
 */
final class TaxAuthorizer
{
    public function __construct(private readonly DocumentAuthorizer $documents) {}

    public function canView(User $user, MushakInvoice $invoice): bool
    {
        $invoice->loadMissing('commercialInvoice');

        return $user->hasPermission('mushak.view')
            && $invoice->commercialInvoice instanceof CommercialInvoice
            && $this->documents->canViewInvoice($user, $invoice->commercialInvoice);
    }

    public function canCreate(User $user, CommercialInvoice $invoice): bool
    {
        return $user->hasPermission('mushak.create')
            && $this->documents->canViewInvoice($user, $invoice)
            && $invoice->stringAttribute('status') === DocumentStatus::ISSUED;
    }

    public function canUpdate(User $user, MushakInvoice $invoice): bool
    {
        return $user->hasPermission('mushak.update')
            && $this->canView($user, $invoice)
            && $invoice->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canIssue(User $user, MushakInvoice $invoice): bool
    {
        return $user->hasPermission('mushak.issue')
            && $this->canView($user, $invoice)
            && $invoice->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canVoid(User $user, MushakInvoice $invoice): bool
    {
        return $user->hasPermission('mushak.void')
            && $this->canView($user, $invoice)
            && $invoice->stringAttribute('status') !== DocumentStatus::VOIDED;
    }

    public function canDownload(User $user, MushakInvoice $invoice): bool
    {
        return $user->hasPermission('mushak.pdf') && $this->canView($user, $invoice);
    }
}
