<?php

namespace App\Auth;

use Illuminate\Validation\Rules\Password;

final class PasswordRule
{
    public static function defaults(): Password
    {
        return Password::min(12)->mixedCase()->numbers();
    }
}
