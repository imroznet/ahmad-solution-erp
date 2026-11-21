<?php

namespace App\Http\Controllers\Concerns;

use App\Accounting\AccountingException;
use Illuminate\Http\RedirectResponse;

trait RedirectsAccountingErrors
{
    protected function accountingError(AccountingException $exception, string $key = 'journal'): RedirectResponse
    {
        return back()->withInput()->withErrors([$key => $exception->getMessage()]);
    }
}
