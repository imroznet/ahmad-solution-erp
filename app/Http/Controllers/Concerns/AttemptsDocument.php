<?php

namespace App\Http\Controllers\Concerns;

use App\Accounting\AccountingException;
use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentRuleException;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

trait AttemptsDocument
{
    protected function attemptDocument(callable $action): mixed
    {
        try {
            return $action();
        } catch (DocumentRuleException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        } catch (AccountingException $exception) {
            throw ValidationException::withMessages([
                'document' => $exception->getMessage(),
            ]);
        }
    }

    protected function documentActor(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function visibleChallan(DeliveryChallan $challan, DocumentAuthorizer $authorizer): DeliveryChallan
    {
        abort_unless($authorizer->canViewChallan($this->documentActor(), $challan), 404);

        return $challan;
    }

    protected function visibleInvoice(CommercialInvoice $invoice, DocumentAuthorizer $authorizer): CommercialInvoice
    {
        abort_unless($authorizer->canViewInvoice($this->documentActor(), $invoice), 404);

        return $invoice;
    }
}
