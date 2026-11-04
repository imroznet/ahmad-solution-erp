<x-layouts.app title="Quotations">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Quotations</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Manual lines for active customers. Tax is excluded and is not calculated. A sent or approved quotation is revised by creating a new revision.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('quotations.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New quotation</a>
        @endif
    </div>

    <form method="GET" action="{{ route('quotations.index') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
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
                <option value="" @selected($filters['status'] === '')>All statuses</option>
                @foreach (\App\Quotations\QuotationStatus::all() as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ \App\Quotations\QuotationStatus::label($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="created_by" class="block text-sm font-medium">Creator</label>
            <select id="created_by" name="created_by" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">Anyone</option>
                <option value="me" @selected((string) request('created_by') === 'me')>Me</option>
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-sm font-medium">From</label>
            <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="date_to" class="block text-sm font-medium">To</label>
            <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        @if ($filters['party'])
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
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 text-right font-medium">Total</th>
                    <th class="px-3 py-2 font-medium">Creator</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $quotation)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('quotations.show', $quotation) }}" class="font-medium text-brand">{{ $quotation->number }}</a></td>
                        <td class="px-3 py-2">{{ $quotation->currentRevision?->party_legal_name ?: $quotation->party?->legal_name }}</td>
                        <td class="px-3 py-2">{{ $quotation->currentRevision?->quotation_date?->format('d M Y') }}</td>
                        <td class="px-3 py-2">{{ $quotation->statusLabel() }}</td>
                        <td class="px-3 py-2 text-right">{{ $quotation->currentRevision?->totalMoney()->format() }}</td>
                        <td class="px-3 py-2">{{ $quotation->createdBy?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-muted">No quotations match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $quotations->links() }}</div>
</x-layouts.app>
