<x-layouts.app title="Mushak 6.3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">A tax invoice linked to an issued commercial invoice. It does not change that invoice. The VAT percentage starts blank.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('mushak.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Prepare from an invoice</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Number</th><th class="px-3 py-2">Buyer</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Review</th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('mushak.show', $invoice) }}" class="font-medium text-brand">{{ $invoice->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $invoice->buyer_name }}</td>
                        <td class="px-3 py-2">{{ $invoice->statusLabel() }}</td>
                        <td class="px-3 py-2">{{ $invoice->reviewLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-muted">No Mushak 6.3 documents yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
