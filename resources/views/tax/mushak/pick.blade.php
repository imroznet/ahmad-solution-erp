<x-layouts.app title="Prepare Mushak 6.3">
    <h1 class="text-xl font-semibold">Choose an issued commercial invoice</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Only an issued commercial invoice can start a Mushak 6.3. Preparing one does not edit the invoice.</p>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Invoice</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2"></th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $invoice->displayNumber() }}</td>
                        <td class="px-3 py-2">{{ $invoice->party_legal_name }}</td>
                        <td class="px-3 py-2 text-right">
                            <form method="POST" action="{{ route('mushak.store') }}">
                                @csrf
                                <input type="hidden" name="commercial_invoice_id" value="{{ $invoice->id }}">
                                <button class="font-medium text-brand">Prepare Mushak 6.3</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-4 text-muted">No issued commercial invoice is waiting for a Mushak 6.3.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
