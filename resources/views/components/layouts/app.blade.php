@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Dashboard' }} — {{ config('asl.company.legal_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>
        try {
            if (window.localStorage.getItem('asl-sidebar') === '1') {
                document.documentElement.dataset.sidebar = 'collapsed';
            }
        } catch (error) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink antialiased">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    @php
        $customerActive = request()->routeIs('customers.*') || (request()->routeIs('parties.*') && ! $currentUser?->hasPermission('parties.view') && ! $currentUser?->hasPermission('suppliers.view'));
        $salesVisible = $currentUser?->hasPermission('quotations.view') || $currentUser?->hasPermission('challans.view') || $currentUser?->hasPermission('invoices.view');
        $taxVisible = $currentUser?->hasPermission('mushak.view') || $currentUser?->hasPermission('tax_rules.view') || $currentUser?->hasPermission('vds.view') || $currentUser?->hasPermission('ait.view');
        $partyVisible = $currentUser?->hasPermission('customers.view') || $currentUser?->hasPermission('suppliers.view') || $currentUser?->hasPermission('parties.view');
        $accountingVisible = $currentUser?->hasPermission('accounting.view') || $currentUser?->hasPermission('chart_of_accounts.view') || $currentUser?->hasPermission('journals.view') || $currentUser?->hasPermission('opening_balances.manage') || $currentUser?->hasPermission('reports.view');
        $companyVisible = $currentUser?->hasPermission('company.view') || $currentUser?->hasPermission('company.documents.view') || $currentUser?->hasPermission('units.view');
        $adminVisible = $currentUser?->hasPermission('quotations.approve') || $currentUser?->hasPermission('users.view') || $currentUser?->hasPermission('roles.view') || $currentUser?->hasPermission('audit.view') || $currentUser?->hasPermission('system.view') || $currentUser?->hasPermission('public_site.manage');
    @endphp
    <div x-data="{ sidebar: false }" class="app-shell min-h-screen">
        <div x-show="sidebar" x-cloak class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="sidebar = false"></div>
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-hidden bg-brand text-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0 lg:self-start"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-3 px-4 py-5">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded bg-white text-sm font-semibold text-brand">AS</span>
                <span class="nav-copy leading-tight">
                    <span class="block text-sm font-semibold">Ahmad Solution</span>
                    <span class="block text-xs text-white/75">Business system</span>
                </span>
            </div>
            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-4" aria-label="Primary">
                @if ($currentUser?->hasPermission('dashboard.view'))
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="grid" label="Dashboard" />
                @endif

                @if ($salesVisible)
                    <p class="nav-copy px-3 pt-3 text-xs font-medium uppercase tracking-wide text-white/60">Sales</p>
                    @if ($currentUser?->hasPermission('quotations.view'))
                        <x-nav-link :href="route('quotations.index')" :active="request()->routeIs('quotations.*') && ! request()->routeIs('quotations.approvals')" icon="file" label="Quotations" />
                    @endif
                    @if ($currentUser?->hasPermission('challans.view'))
                        <x-nav-link :href="route('challans.index')" :active="request()->routeIs('challans.*')" icon="truck" label="Challans" />
                    @endif
                    @if ($currentUser?->hasPermission('invoices.view'))
                        <x-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')" icon="receipt" label="Invoices" />
                    @endif
                @endif

                @if ($taxVisible)
                    <p class="nav-copy px-3 pt-3 text-xs font-medium uppercase tracking-wide text-white/60">Tax</p>
                    @if ($currentUser?->hasPermission('mushak.view'))
                        <x-nav-link :href="route('mushak.index')" :active="request()->routeIs('mushak.*')" icon="percent" label="Mushak 6.3" />
                    @endif
                    @if ($currentUser?->hasPermission('tax_rules.view'))
                        <x-nav-link :href="route('tax.rules.index')" :active="request()->routeIs('tax.rules.*')" icon="book" label="Tax rules" />
                    @endif
                    @if ($currentUser?->hasPermission('vds.view'))
                        <x-nav-link :href="route('deductions.index', ['kind' => 'vds'])" :active="request()->routeIs('deductions.*') && request()->route('kind') === 'vds'" icon="minus" label="VDS" />
                    @endif
                    @if ($currentUser?->hasPermission('ait.view'))
                        <x-nav-link :href="route('deductions.index', ['kind' => 'ait'])" :active="request()->routeIs('deductions.*') && request()->route('kind') === 'ait'" icon="landmark" label="AIT" />
                    @endif
                @endif

                @if ($accountingVisible)
                    <p class="nav-copy px-3 pt-3 text-xs font-medium uppercase tracking-wide text-white/60">Accounting</p>
                    @if ($currentUser?->hasPermission('accounting.view'))
                        <x-nav-link :href="route('accounting.home')" :active="request()->routeIs('accounting.home')" icon="book" label="Overview" />
                    @endif
                    @if ($currentUser?->hasPermission('chart_of_accounts.view'))
                        <x-nav-link :href="route('accounts.index')" :active="request()->routeIs('accounts.*')" icon="list" label="Chart of accounts" />
                        <x-nav-link :href="route('cash-accounts.index')" :active="request()->routeIs('cash-accounts.*', 'bank-accounts.*', 'mfs-accounts.*')" icon="landmark" label="Bank and MFS" />
                        <x-nav-link :href="route('financial-years.index')" :active="request()->routeIs('financial-years.*')" icon="check" label="Financial years" />
                    @endif
                    @if ($currentUser?->hasPermission('journals.view'))
                        <x-nav-link :href="route('journals.index')" :active="request()->routeIs('journals.*')" icon="file" label="Journals" />
                    @endif
                    @if ($currentUser?->hasPermission('opening_balances.manage'))
                        <x-nav-link :href="route('opening-balances.edit')" :active="request()->routeIs('opening-balances.*')" icon="receipt" label="Opening balances" />
                    @endif
                    @if ($currentUser?->hasPermission('reports.view'))
                        <x-nav-link :href="route('reports.trial-balance')" :active="request()->routeIs('reports.trial-balance')" icon="activity" label="Trial balance" />
                        <x-nav-link :href="route('reports.general-ledger')" :active="request()->routeIs('reports.general-ledger')" icon="activity" label="General ledger" />
                    @endif
                @endif

                @if ($partyVisible)
                    <p class="nav-copy px-3 pt-3 text-xs font-medium uppercase tracking-wide text-white/60">Parties</p>
                    @if ($currentUser?->hasPermission('customers.view'))
                        <x-nav-link :href="route('customers.index')" :active="$customerActive" icon="user" label="Customers" />
                    @endif
                    @if ($currentUser?->hasPermission('suppliers.view'))
                        <x-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')" icon="building" label="Suppliers" />
                    @endif
                    @if ($currentUser?->hasPermission('parties.view'))
                        <x-nav-link :href="route('parties.index')" :active="request()->routeIs('parties.*')" icon="users" label="All parties" />
                    @endif
                @endif

                @if ($companyVisible)
                    <p class="nav-copy px-3 pt-3 text-xs font-medium uppercase tracking-wide text-white/60">Company</p>
                    @if ($currentUser?->hasPermission('company.view'))
                        <x-nav-link :href="route('company.show')" :active="request()->routeIs('company.show', 'company.edit', 'company.addresses.*')" icon="office" label="Company" />
                    @endif
                    @if ($currentUser?->hasPermission('company.documents.view'))
                        <x-nav-link :href="route('company.documents.index')" :active="request()->routeIs('company.documents.*', 'company.categories.*')" icon="folder" label="Documents" />
                    @endif
                    @if ($currentUser?->hasPermission('units.view'))
                        <x-nav-link :href="route('units.index')" :active="request()->routeIs('units.*')" icon="ruler" label="Units" />
                    @endif
                @endif

                @if ($adminVisible)
                    <p class="nav-copy px-3 pt-3 text-xs font-medium uppercase tracking-wide text-white/60">Administration</p>
                    @if ($currentUser?->hasPermission('quotations.approve'))
                        <x-nav-link :href="route('quotations.approvals')" :active="request()->routeIs('quotations.approvals')" icon="check" label="Approvals" />
                    @endif
                    @if ($currentUser?->hasPermission('users.view'))
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="user" label="Users" />
                    @endif
                    @if ($currentUser?->hasPermission('roles.view'))
                        <x-nav-link :href="route('roles.index')" :active="request()->routeIs('roles.*')" icon="shield" label="Roles" />
                    @endif
                    @if ($currentUser?->hasPermission('audit.view'))
                        <x-nav-link :href="route('audit.index')" :active="request()->routeIs('audit.*')" icon="list" label="Audit log" />
                    @endif
                    @if ($currentUser?->hasPermission('system.view'))
                        <x-nav-link :href="route('system.status')" :active="request()->routeIs('system.*')" icon="activity" label="System status" />
                    @endif
                    @if ($currentUser?->hasPermission('public_site.manage'))
                        <x-nav-link :href="route('public-site.edit')" :active="request()->routeIs('public-site.*')" icon="activity" label="Public site" />
                    @endif
                @endif
            </nav>
            <div class="border-t border-white/15 px-3 py-3">
                <button
                    type="button"
                    class="hidden min-h-11 w-full items-center gap-2 rounded px-3 py-2 text-left text-sm hover:bg-white/10 lg:flex"
                    title="Collapse or expand the sidebar"
                    @click="
                        const next = document.documentElement.dataset.sidebar === 'collapsed' ? 'expanded' : 'collapsed';
                        document.documentElement.dataset.sidebar = next;
                        try { localStorage.setItem('asl-sidebar', next === 'collapsed' ? '1' : '0'); } catch (error) {}
                    "
                >
                    <x-nav-icon name="list" />
                    <span class="nav-copy">Collapse</span>
                </button>
                <p class="nav-copy px-3 pt-2 text-xs text-white/70">Internal use</p>
            </div>
        </aside>
        <div class="min-w-0 overflow-x-clip">
            <header class="flex items-center justify-between gap-3 border-b border-line bg-white px-4 py-3">
                <button type="button" class="inline-flex min-h-11 items-center rounded border border-line px-3 py-2 text-sm lg:hidden" @click="sidebar = true">Menu</button>
                <p class="hidden min-w-0 truncate text-sm text-muted lg:block">{{ $companyLegalName ?? config('asl.company.legal_name') }}</p>
                <div class="ml-auto flex min-w-0 items-center gap-3 text-sm">
                    <span class="max-w-36 truncate sm:max-w-none">{{ $currentUser?->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex max-lg:min-h-11 items-center font-medium text-brand hover:text-brand-dark">Sign out</button>
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
