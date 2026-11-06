<x-layouts.app title="Choose an invoice">
    <h1 class="text-xl font-semibold">Choose an issued commercial invoice</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Mushak 6.3 is prepared from an issued commercial invoice. It does not replace that invoice.</p>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Invoice</th>
                    <th class="px-3 py-2 font-medium">Customer</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="font-medium text-brand" href="{{ route('mushak.create', ['invoice' => $invoice->id]) }}">{{ $invoice->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $invoice->party_legal_name }}</td>
                        <td class="px-3 py-2">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-4 text-muted">Issue a commercial invoice before preparing Mushak 6.3.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
