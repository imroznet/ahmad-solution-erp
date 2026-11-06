<x-layouts.app title="Tax rules">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Tax rules</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">These rows cite a source and an effective period. They do not choose a VAT percentage for Mushak 6.3. No statutory rate is seeded.</p>
        </div>
        @if ($currentUser?->hasPermission('tax_rules.create'))
            <a href="{{ route('tax.rules.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New draft rule</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Version</th>
                    <th class="px-3 py-2">Window</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('tax.rules.show', $rule) }}" class="font-medium text-brand">{{ $rule->rule_code }}</a></td>
                        <td class="px-3 py-2">{{ $rule->typeLabel() }}</td>
                        <td class="px-3 py-2">{{ $rule->version }}</td>
                        <td class="px-3 py-2">{{ $rule->dateString('effective_from') }} – {{ $rule->dateString('effective_to') ?? 'open' }}</td>
                        <td class="px-3 py-2">{{ $rule->approval_status }} / {{ $rule->activity_status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-muted">No tax rules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rules->links() }}</div>
</x-layouts.app>
