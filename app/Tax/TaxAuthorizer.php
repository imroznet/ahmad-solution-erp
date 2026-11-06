<?php

namespace App\Tax;

use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentStatus;
use App\Models\AitRecord;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\User;
use App\Models\VdsRecord;
use App\Models\WithholdingRecord;

/**
 * Route middleware checks the named permission.
 * These methods decide which customer document that permission can open.
 */
final class TaxAuthorizer
{
    public function __construct(private readonly DocumentAuthorizer $documents) {}

    public function canViewMushak(User $user, Mushak63Document $document): bool
    {
        $document->loadMissing('invoice');
        $invoice = $document->invoice;

        return $user->hasPermission('mushak.view')
            && $invoice instanceof CommercialInvoice
            && $this->documents->canViewInvoice($user, $invoice);
    }

    public function canPrepareMushak(User $user, CommercialInvoice $invoice): bool
    {
        return $user->hasPermission('mushak.create')
            && $this->documents->canViewInvoice($user, $invoice)
            && $invoice->stringAttribute('status') === DocumentStatus::ISSUED;
    }

    public function canUpdateMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.update')
            && $this->canViewMushak($user, $document)
            && $document->stringAttribute('status') === TaxDocumentStatus::DRAFT;
    }

    public function canAttemptMushakIssue(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.issue')
            && $this->canViewMushak($user, $document)
            && $document->stringAttribute('status') === TaxDocumentStatus::DRAFT;
    }

    public function canVoidMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.void')
            && $this->canViewMushak($user, $document)
            && $document->stringAttribute('status') === TaxDocumentStatus::DRAFT;
    }

    public function canDownloadMushak(User $user, Mushak63Document $document): bool
    {
        return $user->hasPermission('mushak.pdf') && $this->canViewMushak($user, $document);
    }

    public function canViewWithholding(User $user, WithholdingRecord $record): bool
    {
        $permission = $record instanceof VdsRecord ? 'vds.view' : 'ait.view';

        if (! $user->hasPermission($permission)) {
            return false;
        }

        if ($record instanceof AitRecord && $record->taxType() !== TaxType::AIT) {
            return false;
        }

        $record->loadMissing('invoice');
        $invoice = $record->invoice;

        if (! $invoice instanceof CommercialInvoice) {
            return $user->hasPermission('parties.view')
                || $user->hasPermission('customers.view')
                || $user->hasPermission('suppliers.view');
        }

        return $this->documents->canViewInvoice($user, $invoice);
    }

    public function canDownloadWithholding(User $user, WithholdingRecord $record): bool
    {
        $permission = $record instanceof VdsRecord ? 'vds.pdf' : 'ait.pdf';

        return $user->hasPermission($permission) && $this->canViewWithholding($user, $record);
    }
}
