<x-layouts.app title="Mushak 6.3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">A tax invoice linked to an issued commercial invoice. It is a separate document. VAT percentages are entered on the draft. No percentage is selected automatically.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('mushak.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New Mushak 6.3</a>
        @endif
    </div>
    <form method="GET" class="mt-4">
        <label for="status" class="text-sm font-medium">Status</label>
        <select id="status" name="status" class="ml-2 rounded border border-line bg-white px-3 py-2 text-sm">
            <option value="">All</option>
            @foreach (['draft' => 'Draft', 'issued' => 'Issued', 'voided' => 'Voided'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="ml-2 rounded border border-line bg-white px-3 py-2 text-sm">Apply</button>
    </form>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Buyer</th>
                    <th class="px-3 py-2 font-medium">Invoice</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Review</th>
                    <th class="px-3 py-2 text-right font-medium">VAT</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('mushak.show', $document) }}" class="font-medium text-brand">{{ $document->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $document->buyer_legal_name }}</td>
                        <td class="px-3 py-2">{{ $document->invoice?->displayNumber() }}</td>
                        <td class="px-3 py-2">{{ $document->statusLabel() }}</td>
                        <td class="px-3 py-2">{{ $document->reviewLabel() }}</td>
                        <td class="px-3 py-2 text-right">{{ $document->money('vat_total')?->format() ?? 'Not calculated' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-muted">No Mushak 6.3 documents.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-layouts.app>
