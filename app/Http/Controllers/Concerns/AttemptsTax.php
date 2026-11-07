<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Tax\TaxRuleException;
use Illuminate\Validation\ValidationException;

trait AttemptsTax
{
    protected function attemptTax(callable $action): mixed
    {
        try {
            return $action();
        } catch (TaxRuleException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }
    }

    protected function taxActor(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
