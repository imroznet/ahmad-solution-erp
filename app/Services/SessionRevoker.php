<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes database session rows for one account.
 * The current request's session is rewritten by the session middleware when
 * that request is already authenticated as the same user, so an administrator
 * who changes their own password stays signed in. Every other session id does not.
 */
final class SessionRevoker
{
    public function revoke(User $user): void
    {
        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
