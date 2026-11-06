<x-layouts.app title="Tax rules">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Tax rules</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Output VAT, VDS, and AIT are separate. A stored percentage is not copied onto a Mushak 6.3. No rate is seeded.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('tax-rules.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New rule</a>
        @endif
    </div>
    <form method="GET" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-4">
        <select name="tax_type" class="rounded border border-line px-3 py-2 text-sm" aria-label="Tax type">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected($filterType === $type)>{{ \App\Tax\TaxType::label($type) }}</option>
            @endforeach
        </select>
        <input name="classification" value="{{ $classification }}" placeholder="Classification" class="rounded border border-line px-3 py-2 text-sm" aria-label="Classification">
        <input name="on" type="date" value="{{ $previewDate }}" class="rounded border border-line px-3 py-2 text-sm" aria-label="Date">
        <button class="rounded border border-line px-3 py-2 text-sm">Check which rule matches</button>
    </form>
    @if ($preview)
        <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">{{ $preview->explanation }}</p>
    @endif
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Rule</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Version</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">From</th></tr></thead>
            <tbody>
                @forelse ($versions as $version)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="font-medium text-brand" href="{{ route('tax-rules.show', $version) }}">{{ $version->rule?->rule_code }}</a></td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxType::label($version->rule?->tax_type ?? '') }}</td>
                        <td class="px-3 py-2">{{ $version->version_number }}</td>
                        <td class="px-3 py-2">{{ $version->statusLabel() }}</td>
                        <td class="px-3 py-2">{{ $version->effective_from?->toDateString() }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-4 text-muted" colspan="5">No tax rules.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $versions->links() }}</div>
</x-layouts.app>
