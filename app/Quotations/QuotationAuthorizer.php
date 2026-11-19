<?php

namespace App\Quotations;

use App\Models\Quotation;
use App\Models\QuotationApproval;
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

        return $this->selfApprovalRefusal($user, $revision) === null;
    }

    public function preparedThisRevision(User $user, QuotationRevision $revision): bool
    {
        if ((int) $revision->created_by === (int) $user->getKey()) {
            return true;
        }

        return $this->latestSubmitterId($revision) === (int) $user->getKey();
    }

    /**
     * A holder of quotations.approve may still be blocked from a revision they
     * created or submitted. quotations.approve_own lifts that block only.
     */
    public function selfApprovalRefusal(User $user, QuotationRevision $revision): ?string
    {
        if ($user->hasPermission('quotations.approve_own')) {
            return null;
        }

        if ((int) $revision->created_by === (int) $user->getKey()) {
            return 'The person who created this revision cannot approve it.';
        }

        if ($this->latestSubmitterId($revision) === (int) $user->getKey()) {
            return 'The person who submitted this revision cannot approve it.';
        }

        return null;
    }

    private function latestSubmitterId(QuotationRevision $revision): ?int
    {
        $revision->loadMissing('approvals');
        $submission = $revision->approvals->where('action', 'submitted')->sortByDesc('id')->first();

        if (! $submission instanceof QuotationApproval || $submission->user_id === null) {
            return null;
        }

        return (int) $submission->user_id;
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
