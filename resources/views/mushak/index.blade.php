<x-layouts.app title="Mushak 6.3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Tax invoices. A Mushak 6.3 is not a commercial invoice. VAT is calculated from a percentage you type. VDS and AIT are separate records.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('mushak.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New Mushak 6.3</a>
        @endif
    </div>
    <form method="GET" action="{{ route('mushak.index') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-3">
        <input name="q" value="{{ $filters['q'] }}" placeholder="Number or buyer" class="rounded border border-line px-3 py-2 text-sm" aria-label="Search">
        <select name="status" class="rounded border border-line px-3 py-2 text-sm" aria-label="Status">
            <option value="">Any status</option>
            @foreach (['draft' => 'Draft', 'issued' => 'Issued', 'voided' => 'Voided'] as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Filter</button>
    </form>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Buyer</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Final</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('mushak.show', $invoice) }}" class="font-medium text-brand">{{ $invoice->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $invoice->buyer_name }}</td>
                        <td class="px-3 py-2">{{ $invoice->statusLabel() }}</td>
                        <td class="px-3 py-2">{{ $invoice->final_total ?? 'Not calculated' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-4 text-muted" colspan="4">No Mushak 6.3 documents.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
