<x-layouts.app title="Choose a commercial invoice">
    <h1 class="text-xl font-semibold">Choose an issued commercial invoice</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Mushak 6.3 starts from an issued commercial invoice. It does not turn that invoice into a tax invoice.</p>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2 font-medium">Invoice</th><th class="px-3 py-2 font-medium">Customer</th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('mushak.create', ['invoice' => $invoice->id]) }}" class="font-medium text-brand">{{ $invoice->displayNumber() }}</a></td>
                        <td class="px-3 py-2">{{ $invoice->party_legal_name }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-4 text-muted" colspan="2">Issue a commercial invoice first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
