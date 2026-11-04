<x-layouts.app title="Quotation approvals">
    <h1 class="text-xl font-semibold">Approval queue</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Submitted quotations waiting for someone other than the creator to approve them. Returning a quotation sends that revision back to draft.</p>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Customer</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 text-right font-medium">Total</th>
                    <th class="px-3 py-2 font-medium">Created by</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $quotation)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('quotations.show', $quotation) }}" class="font-medium text-brand">{{ $quotation->number }}</a></td>
                        <td class="px-3 py-2">{{ $quotation->currentRevision?->party_legal_name }}</td>
                        <td class="px-3 py-2">{{ $quotation->currentRevision?->quotation_date?->format('d M Y') }}</td>
                        <td class="px-3 py-2 text-right">{{ $quotation->currentRevision?->totalMoney()->format() }}</td>
                        <td class="px-3 py-2">{{ $quotation->createdBy?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-muted">Nothing is waiting for approval.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $quotations->links() }}</div>
</x-layouts.app>
