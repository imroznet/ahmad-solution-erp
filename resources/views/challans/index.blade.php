<x-layouts.app title="Delivery challans">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Delivery challans</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">A challan records quantities delivered from an accepted quotation. It is not a commercial invoice, a tax invoice, or a Mushak form. A draft has no number until it is issued.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('challans.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New challan</a>
        @endif
    </div>

    <form method="GET" action="{{ route('challans.index') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="number" class="block text-sm font-medium">Number</label>
            <input id="number" name="number" value="{{ $filters['number'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="customer" class="block text-sm font-medium">Customer</label>
            <input id="customer" name="customer" value="{{ $filters['customer'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium">Status</label>
            <select id="status" name="status" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (\App\Documents\DocumentStatus::all() as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ \App\Documents\DocumentStatus::label($status) }}</option>
                @endforeach
            </select>
        </div>
        @if ($filters['quotation'] !== '')
            <input type="hidden" name="quotation" value="{{ $filters['quotation'] }}">
        @endif
        @if ($filters['party'] !== '')
            <input type="hidden" name="party" value="{{ $filters['party'] }}">
        @endif
        <div class="flex items-end">
            <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Apply</button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Customer</th>
                    <th class="px-3 py-2 font-medium">Quotation</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($challans as $challan)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('challans.show', $challan) }}" class="font-medium text-brand">{{ $challan->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $challan->party_legal_name }}</td>
                        <td class="px-3 py-2">{{ $challan->quotation?->number }}</td>
                        <td class="px-3 py-2">{{ $challan->challan_date?->format('d M Y') }}</td>
                        <td class="px-3 py-2">{{ $challan->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-muted">No delivery challans match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $challans->links() }}</div>
</x-layouts.app>
