<x-layouts.app title="Receipts">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Receipts</h1>
        @if ($canCreate)
            <a href="{{ route('receipts.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Record receipt</a>
        @endif
    </div>
    <p class="mt-1 max-w-3xl text-sm text-muted">A customer receipt posts when it is recorded. It is not edited. Void it and record another. VDS and AIT are amounts typed on the receipt. They are not calculated.</p>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Customer</th>
                    <th class="px-3 py-2 text-right font-medium">Received</th>
                    <th class="px-3 py-2 text-right font-medium">VDS</th>
                    <th class="px-3 py-2 text-right font-medium">AIT</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipts as $receipt)
                    <tr class="border-b border-line last:border-b-0">
                        <td class="px-3 py-2"><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('receipts.show', $receipt) }}">{{ $receipt->number }}</a></td>
                        <td class="px-3 py-2">{{ $receipt->receiptDateString() }}</td>
                        <td class="px-3 py-2">{{ $receipt->party?->legal_name }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($receipt->decimalAttribute('amount_received'))->groupedAmount() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($receipt->decimalAttribute('vds_deducted'))->groupedAmount() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($receipt->decimalAttribute('ait_deducted'))->groupedAmount() }}</td>
                        <td class="px-3 py-2">{{ $receipt->status->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-4 text-muted">No receipts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $receipts->links() }}</div>
</x-layouts.app>
