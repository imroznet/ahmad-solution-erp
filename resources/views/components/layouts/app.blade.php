@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Dashboard' }} — {{ config('asl.company.legal_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink antialiased">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    <div x-data="{ sidebar: false }" class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
        <div x-show="sidebar" x-cloak class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="sidebar = false"></div>
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-brand text-white transition-transform lg:static lg:translate-x-0"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-3 px-4 py-5">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded bg-white text-sm font-semibold text-brand">AS</span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold">Ahmad Solution</span>
                    <span class="block text-xs text-white/75">Business system</span>
                </span>
            </div>
            <nav class="flex-1 space-y-1 px-3" aria-label="Primary">
                @if ($currentUser?->hasPermission('dashboard.view'))
                    <a href="{{ route('dashboard') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('dashboard'), 'hover:bg-white/10' => ! request()->routeIs('dashboard')])>Dashboard</a>
                @endif
                @if ($currentUser?->hasPermission('company.view'))
                    <a href="{{ route('company.show') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('company.show', 'company.edit', 'company.addresses.*'), 'hover:bg-white/10' => ! request()->routeIs('company.show', 'company.edit', 'company.addresses.*')])>Company</a>
                @endif
                @if ($currentUser?->hasPermission('company.documents.view'))
                    <a href="{{ route('company.documents.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('company.documents.*', 'company.categories.*'), 'hover:bg-white/10' => ! request()->routeIs('company.documents.*', 'company.categories.*')])>Documents</a>
                @endif
                @if ($currentUser?->hasPermission('quotations.view'))
                    <a href="{{ route('quotations.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('quotations.*') && ! request()->routeIs('quotations.approvals'), 'hover:bg-white/10' => ! (request()->routeIs('quotations.*') && ! request()->routeIs('quotations.approvals'))])>Quotations</a>
                @endif
                @if ($currentUser?->hasPermission('challans.view'))
                    <a href="{{ route('challans.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('challans.*'), 'hover:bg-white/10' => ! request()->routeIs('challans.*')])>Challans</a>
                @endif
                @if ($currentUser?->hasPermission('invoices.view'))
                    <a href="{{ route('invoices.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('invoices.*'), 'hover:bg-white/10' => ! request()->routeIs('invoices.*')])>Invoices</a>
                @endif
                @if ($currentUser?->hasPermission('quotations.approve'))
                    <a href="{{ route('quotations.approvals') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('quotations.approvals'), 'hover:bg-white/10' => ! request()->routeIs('quotations.approvals')])>Approvals</a>
                @endif
                @if ($currentUser?->hasPermission('customers.view'))
                    <a href="{{ route('customers.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('customers.*') || (request()->routeIs('parties.*') && ! $currentUser?->hasPermission('parties.view') && ! $currentUser?->hasPermission('suppliers.view')), 'hover:bg-white/10' => ! (request()->routeIs('customers.*') || (request()->routeIs('parties.*') && ! $currentUser?->hasPermission('parties.view') && ! $currentUser?->hasPermission('suppliers.view')))])>Customers</a>
                @endif
                @if ($currentUser?->hasPermission('suppliers.view'))
                    <a href="{{ route('suppliers.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('suppliers.*'), 'hover:bg-white/10' => ! request()->routeIs('suppliers.*')])>Suppliers</a>
                @endif
                @if ($currentUser?->hasPermission('parties.view'))
                    <a href="{{ route('parties.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('parties.*'), 'hover:bg-white/10' => ! request()->routeIs('parties.*')])>All parties</a>
                @endif
                @if ($currentUser?->hasPermission('users.view'))
                    <a href="{{ route('users.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('users.*'), 'hover:bg-white/10' => ! request()->routeIs('users.*')])>Users</a>
                @endif
                @if ($currentUser?->hasPermission('roles.view'))
                    <a href="{{ route('roles.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('roles.*'), 'hover:bg-white/10' => ! request()->routeIs('roles.*')])>Roles</a>
                @endif
                @if ($currentUser?->hasPermission('audit.view'))
                    <a href="{{ route('audit.index') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('audit.*'), 'hover:bg-white/10' => ! request()->routeIs('audit.*')])>Audit log</a>
                @endif
                @if ($currentUser?->hasPermission('system.view'))
                    <a href="{{ route('system.status') }}" @class(['block rounded px-3 py-2 text-sm focus-visible:outline-white', 'bg-white/15 font-medium' => request()->routeIs('system.*'), 'hover:bg-white/10' => ! request()->routeIs('system.*')])>System status</a>
                @endif
            </nav>
            <p class="px-4 py-4 text-xs text-white/70">Internal use</p>
        </aside>
        <div class="min-w-0">
            <header class="flex items-center justify-between border-b border-line bg-white px-4 py-3">
                <button type="button" class="rounded border border-line px-3 py-2 text-sm lg:hidden" @click="sidebar = true">Menu</button>
                <p class="hidden text-sm text-muted lg:block">{{ $companyLegalName ?? config('asl.company.legal_name') }}</p>
                <div class="flex items-center gap-4 text-sm">
                    <span>{{ $currentUser?->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-medium text-brand hover:text-brand-dark">Sign out</button>
                    </form>
                </div>
            </header>
            <main id="content" class="px-4 py-6 sm:px-6">
                @if (session('status'))
                    <p class="mb-4 rounded border border-line bg-white px-3 py-2 text-sm" role="status">{{ session('status') }}</p>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
