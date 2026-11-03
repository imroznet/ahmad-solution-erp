<x-layouts.app title="Dashboard">
    <h1 class="text-xl font-semibold">Dashboard</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Operational counts below come from the database. Quotation, invoice, receivable, VAT, and expense figures are omitted until those modules are installed. No sample amounts are shown.</p>

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
