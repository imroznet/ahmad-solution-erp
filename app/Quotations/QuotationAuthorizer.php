<?php

namespace App\Quotations;

use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;

/**
 * Route middleware checks that the user holds a quotation permission.
 * These methods decide which customer quotation that permission can open.
 */
final class QuotationAuthorizer
{
    public function canAccessCustomerQuotations(User $user): bool
    {
        return $user->hasPermission('quotations.view')
            && ($user->hasPermission('parties.view')
                || $user->hasPermission('customers.view')
                || $user->hasPermission('customers.manage'));
    }

    public function canView(User $user, Quotation $quotation): bool
    {
        return $this->canAccessCustomerQuotations($user)
            && $quotation->stringAttribute('party_role') === 'customer';
    }

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('quotations.create') && $this->canAccessCustomerQuotations($user);
    }

    public function canUpdate(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.update')
            && $this->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::DRAFT;
    }

    public function canRevise(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.update')
            && $this->canView($user, $quotation)
            && in_array($quotation->stringAttribute('status'), QuotationStatus::REVISABLE, true);
    }

    public function canSubmit(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.submit')
            && $this->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::DRAFT;
    }

    public function canApprove(User $user, Quotation $quotation): bool
    {
        if (! $user->hasPermission('quotations.approve') || ! $this->canView($user, $quotation)) {
            return false;
        }

        if ($quotation->stringAttribute('status') !== QuotationStatus::SUBMITTED) {
            return false;
        }

        $revision = $quotation->currentRevision;

        if (! $revision instanceof QuotationRevision) {
            return false;
        }

        if ((int) $revision->created_by === (int) $user->getKey()) {
            return false;
        }

        $revision->loadMissing('approvals');
        $submission = $revision->approvals->where('action', 'submitted')->sortByDesc('id')->first();

        return $submission === null || (int) $submission->user_id !== (int) $user->getKey();
    }

    public function canReturn(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.approve')
            && $this->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::SUBMITTED;
    }

    public function canSend(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.send')
            && $this->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::APPROVED;
    }

    public function canCancel(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.cancel')
            && $this->canView($user, $quotation)
            && QuotationStatus::canMove($quotation->stringAttribute('status'), QuotationStatus::CANCELLED);
    }

    public function canExpire(User $user, Quotation $quotation): bool
    {
        $revision = $quotation->currentRevision;

        return $user->hasPermission('quotations.update')
            && $this->canView($user, $quotation)
            && $revision instanceof QuotationRevision
            && $revision->isPastValidity()
            && QuotationStatus::canMove($quotation->stringAttribute('status'), QuotationStatus::EXPIRED);
    }

    public function canRespond(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.respond')
            && $this->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::SENT;
    }

    public function canShare(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.share')
            && $this->canView($user, $quotation)
            && $quotation->stringAttribute('status') === QuotationStatus::SENT;
    }

    public function canRevokeShare(User $user, Quotation $quotation): bool
    {
        return $user->hasPermission('quotations.share') && $this->canView($user, $quotation);
    }
}
