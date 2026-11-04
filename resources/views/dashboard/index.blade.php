<x-layouts.app title="Dashboard">
    <h1 class="text-xl font-semibold">Dashboard</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Operational counts below come from the database. Invoice, receivable, VAT, and expense figures are omitted until those modules are installed. No sample amounts are shown.</p>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Users</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ $userCount }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Active users</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ $activeUserCount }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Audit events</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ $auditCount }}</dd>
        </div>
    </dl>

    @if (is_array($documentExpiry))
        <section class="mt-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">Document expiry</h2>
                    <p class="mt-1 max-w-3xl text-sm text-muted">Dates use Asia/Dhaka. A document expiring within 7 days is also counted within 15, 30, and 60 days. Documents with no expiry date are not included.</p>
                </div>
                <a href="{{ route('company.documents.expiring') }}" class="text-sm font-medium text-brand hover:text-brand-dark">Open the expiry list</a>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-5">
                <div class="rounded border border-line bg-white px-4 py-3">
                    <dt class="text-sm text-muted">Expired</dt>
                    <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('company.documents.expiring', ['window' => 'expired']) }}">{{ $documentExpiry['expired'] }}</a></dd>
                </div>
                <div class="rounded border border-line bg-white px-4 py-3">
                    <dt class="text-sm text-muted">Within 7 days</dt>
                    <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('company.documents.expiring', ['window' => '7']) }}">{{ $documentExpiry['within_7'] }}</a></dd>
                </div>
                <div class="rounded border border-line bg-white px-4 py-3">
                    <dt class="text-sm text-muted">Within 15 days</dt>
                    <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('company.documents.expiring', ['window' => '15']) }}">{{ $documentExpiry['within_15'] }}</a></dd>
                </div>
                <div class="rounded border border-line bg-white px-4 py-3">
                    <dt class="text-sm text-muted">Within 30 days</dt>
                    <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('company.documents.expiring', ['window' => '30']) }}">{{ $documentExpiry['within_30'] }}</a></dd>
                </div>
                <div class="rounded border border-line bg-white px-4 py-3">
                    <dt class="text-sm text-muted">Within 60 days</dt>
                    <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('company.documents.expiring', ['window' => '60']) }}">{{ $documentExpiry['within_60'] }}</a></dd>
                </div>
            </dl>
        </section>
    @endif

    @if (is_array($quotationCounts))
        <section class="mt-8">
            <h2 class="text-base font-semibold">Quotations</h2>
            <p class="mt-1 max-w-3xl text-sm text-muted">Counts only. Amounts stay on the quotation itself. Tax is not calculated.</p>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded border border-line bg-white px-4 py-3">
                    <dt class="text-sm text-muted">Open quotations</dt>
                    <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('quotations.index') }}">{{ $quotationCounts['open'] }}</a></dd>
                </div>
                @if ($quotationCounts['awaiting'] !== null)
                    <div class="rounded border border-line bg-white px-4 py-3">
                        <dt class="text-sm text-muted">Awaiting approval</dt>
                        <dd class="mt-1 text-2xl font-semibold"><a href="{{ route('quotations.approvals') }}">{{ $quotationCounts['awaiting'] }}</a></dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    @if (is_array($partyCounts))
        <section class="mt-8">
            <h2 class="text-base font-semibold">Parties</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @if ($partyCounts['customers'] !== null)
                    <div class="rounded border border-line bg-white px-4 py-3">
                        <dt class="text-sm text-muted">Active customers</dt>
                        <dd class="mt-1 text-2xl font-semibold"><a href="{{ $currentUser?->hasPermission('customers.view') ? route('customers.index') : route('parties.index', ['role' => 'customer']) }}">{{ $partyCounts['customers'] }}</a></dd>
                    </div>
                @endif
                @if ($partyCounts['suppliers'] !== null)
                    <div class="rounded border border-line bg-white px-4 py-3">
                        <dt class="text-sm text-muted">Active suppliers</dt>
                        <dd class="mt-1 text-2xl font-semibold"><a href="{{ $currentUser?->hasPermission('suppliers.view') ? route('suppliers.index') : route('parties.index', ['role' => 'supplier']) }}">{{ $partyCounts['suppliers'] }}</a></dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="text-base font-semibold">Recent activity</h2>
        @if ($recentActivity->isEmpty())
            <p class="mt-3 text-sm text-muted">No activity has been recorded.</p>
        @else
            <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-line text-left text-muted">
                        <tr>
                            <th class="px-3 py-2 font-medium">When</th>
                            <th class="px-3 py-2 font-medium">Event</th>
                            <th class="px-3 py-2 font-medium">Actor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentActivity as $activity)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $activity->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
                                <td class="px-3 py-2">{{ $activity->label() }}</td>
                                <td class="px-3 py-2">{{ $activity->user?->name ?? 'Unknown' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
