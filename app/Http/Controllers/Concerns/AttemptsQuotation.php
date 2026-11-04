<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationRuleException;
use Illuminate\Validation\ValidationException;

trait AttemptsQuotation
{
    protected function attemptQuotation(callable $action): mixed
    {
        try {
            return $action();
        } catch (QuotationRuleException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }
    }

    protected function quotationActor(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function visibleQuotation(Quotation $quotation, QuotationAuthorizer $authorizer): Quotation
    {
        abort_unless($authorizer->canView($this->quotationActor(), $quotation), 404);

        return $quotation;
    }
}
