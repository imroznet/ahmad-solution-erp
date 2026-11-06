<x-layouts.app title="Mushak 6.3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3 working papers</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">A working paper is linked to an issued commercial invoice. It is not that invoice, and it is not an issued tax invoice.</p>
        </div>
        @if ($currentUser?->hasPermission('mushak.create'))
            <a href="{{ route('mushak.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Prepare a working paper</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Paper</th><th class="px-3 py-2">Invoice</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2">Status</th></tr></thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="text-brand underline" href="{{ route('mushak.show', $document) }}">Paper {{ $document->id }}</a></td>
                        <td class="px-3 py-2">{{ $document->invoice_number }}</td>
                        <td class="px-3 py-2">{{ $document->party_legal_name }}</td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxDocumentStatus::label($document->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-muted">No Mushak working papers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
