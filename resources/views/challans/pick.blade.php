<x-layouts.app title="New delivery challan">
    <h1 class="text-xl font-semibold">New delivery challan</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Choose an accepted quotation. Quantities are typed by hand. This does not move stock.</p>
    @if ($message)
        <p role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">{{ $message }}</p>
    @endif
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Quotation</th>
                    <th class="px-3 py-2 font-medium">Customer</th>
                    <th class="px-3 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $quotation)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $quotation->number }}</td>
                        <td class="px-3 py-2">{{ $quotation->currentRevision?->party_legal_name }}</td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('challans.create', ['quotation' => $quotation->id]) }}" class="font-medium text-brand">Use this quotation</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-4 text-muted">No accepted quotations are available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
