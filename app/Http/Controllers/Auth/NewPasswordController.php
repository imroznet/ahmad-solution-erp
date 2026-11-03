<?php

namespace App\Http\Controllers\Auth;

use App\Auth\PasswordRule;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($audit): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $audit->record('password_reset', $user, actor: $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => 'This reset link is invalid or has expired.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Password updated. You can sign in.');
    }
}
