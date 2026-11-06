<?php

namespace App\Tax;

use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentStatus;
use App\Models\MushakDocument;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;

/**
 * Route middleware checks the Mushak permission.
 * A document linked to a quotation also requires that customer quotation to be visible.
 */
final class MushakAuthorizer
{
    public function __construct(
        private readonly QuotationAuthorizer $quotations,
        private readonly DocumentAuthorizer $documents,
    ) {}

    public function canView(User $user, MushakDocument $document): bool
    {
        if (! $user->hasPermission('mushak.view')) {
            return false;
        }

        if ($document->quotation_id === null) {
            return true;
        }

        $document->loadMissing('quotation');
        $quotation = $document->quotation;

        return $quotation !== null && $this->quotations->canView($user, $quotation);
    }

    public function canUpdate(User $user, MushakDocument $document): bool
    {
        return $user->hasPermission('mushak.create')
            && $this->canView($user, $document)
            && $document->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canIssue(User $user, MushakDocument $document): bool
    {
        return $user->hasPermission('mushak.issue')
            && $this->canView($user, $document)
            && $document->stringAttribute('status') === DocumentStatus::DRAFT;
    }

    public function canVoid(User $user, MushakDocument $document): bool
    {
        return $user->hasPermission('mushak.void')
            && $this->canView($user, $document)
            && in_array($document->stringAttribute('status'), [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true);
    }

    public function canDownload(User $user, MushakDocument $document): bool
    {
        return $user->hasPermission('mushak.pdf') && $this->canView($user, $document);
    }

    public function canRecordTreatment(User $user, MushakDocument $document): bool
    {
        return $user->hasPermission('mushak.record_treatment')
            && $this->canView($user, $document)
            && $document->stringAttribute('status') === DocumentStatus::ISSUED
            && $document->stringAttribute('tax_review_status') === TaxReviewStatus::REVIEW_REQUIRED;
    }

    public function documents(): DocumentAuthorizer
    {
        return $this->documents;
    }
}
