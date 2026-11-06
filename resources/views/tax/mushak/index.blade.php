<x-layouts.app title="Mushak 6.3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">A tax invoice, separate from a commercial invoice. VAT percentage and VAT amount stay blank until someone types them. The ERP does not calculate them.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('mushak.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New Mushak 6.3</a>
        @endif
    </div>

    <form method="GET" action="{{ route('mushak.index') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-4">
        <div>
            <label for="number" class="block text-sm font-medium">Number</label>
            <input id="number" name="number" value="{{ $filters['number'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="buyer" class="block text-sm font-medium">Buyer</label>
            <input id="buyer" name="buyer" value="{{ $filters['buyer'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
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
        <div class="flex items-end">
            <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Apply</button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Buyer</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Tax review</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('mushak.show', $document) }}" class="font-medium text-brand hover:underline">{{ $document->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $document->buyer_name }}</td>
                        <td class="px-3 py-2">{{ $document->statusLabel() }}</td>
                        <td class="px-3 py-2">{{ $document->reviewLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-muted">No Mushak 6.3 documents.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-layouts.app>
