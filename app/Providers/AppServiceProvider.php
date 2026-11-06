<?php

namespace App\Providers;

use App\Authorization\PermissionCatalog;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $proxies = config('asl.trusted_proxies', []);

        if (is_array($proxies) && $proxies !== []) {
            TrustProxies::at($proxies);
            TrustProxies::withHeaders(
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
            );
        }

        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            return $user->isSuperAdministrator() ? true : null;
        });

        foreach (PermissionCatalog::permissions() as $permission) {
            Gate::define($permission['slug'], fn (User $user): bool => $user->hasPermission($permission['slug']));
        }

        View::composer([
            'components.layouts.app',
            'dashboard.index',
            'users.*',
            'roles.*',
            'audit.index',
            'system.status',
            'company.*',
            'parties.*',
            'parties.contacts.*',
            'parties.addresses.*',
            'quotations.*',
            'challans.*',
            'invoices.*',
            'tax-rules.*',
            'mushak.*',
            'withholding.*',
        ], function ($view): void {
            $user = auth()->user();

            if ($user instanceof User) {
                $user->loadMissing('roles.permissions');
            }

            $companyName = config('asl.company.legal_name');
            $storedName = CompanyProfile::query()->value('legal_name');

            if (is_string($storedName) && $storedName !== '') {
                $companyName = $storedName;
            }

            $view->with('currentUser', $user instanceof User ? $user : null);
            $view->with('companyLegalName', is_string($companyName) ? $companyName : 'Ahmad Solution Ltd.');
        });

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->save();
            app(AuditLogger::class)->record('login', $event->user, actor: $event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            app(AuditLogger::class)->record('logout', $event->user, actor: $event->user);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;

            app(AuditLogger::class)->record('login_failed', newValues: [
                'email' => is_string($email) ? $email : null,
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            app(AuditLogger::class)->record('login_lockout', newValues: [
                'email' => (string) $event->request->input('email'),
            ]);
        });
    }
}
