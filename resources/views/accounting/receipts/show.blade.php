<x-layouts.app :title="$receipt->number">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ $receipt->number }}</h1>
        <a href="{{ route('receipts.index') }}" class="text-sm text-brand hover:text-brand-dark">All receipts</a>
    </div>
    @include('accounting.partials.errors')
    <dl class="mt-6 grid max-w-3xl gap-3 rounded border border-line bg-white p-3 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Date</dt><dd>{{ $receipt->receiptDateString() }}</dd></div>
        <div><dt class="text-muted">Status</dt><dd>{{ $receipt->status->label() }}</dd></div>
        <div><dt class="text-muted">Customer</dt><dd>{{ $receipt->party?->legal_name }}</dd></div>
        <div><dt class="text-muted">Received into</dt><dd>{{ $receipt->account?->code }} {{ $receipt->account?->name }}</dd></div>
        <div><dt class="text-muted">Amount received</dt><dd class="tabular-nums">{{ \App\Support\Money::of($receipt->decimalAttribute('amount_received'))->groupedAmount() }}</dd></div>
        <div><dt class="text-muted">VDS deducted</dt><dd class="tabular-nums">{{ \App\Support\Money::of($receipt->decimalAttribute('vds_deducted'))->groupedAmount() }}</dd></div>
        <div><dt class="text-muted">AIT deducted</dt><dd class="tabular-nums">{{ \App\Support\Money::of($receipt->decimalAttribute('ait_deducted'))->groupedAmount() }}</dd></div>
        <div><dt class="text-muted">Reference</dt><dd>{{ $receipt->reference }}</dd></div>
    </dl>
    <h2 class="mt-6 text-sm font-medium">Allocations</h2>
    <div class="mt-2 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Invoice</th>
                    <th class="px-3 py-2 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipt->allocations as $allocation)
                    <tr class="border-b border-line last:border-b-0">
                        <td class="px-3 py-2">{{ $allocation->invoice?->displayNumber() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($allocation->decimalAttribute('amount'))->groupedAmount() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-3 py-4 text-muted">Nothing is allocated. The whole receipt is an advance.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($receipt->status === \App\Accounting\ReceiptStatus::Void)
        <p class="mt-4 max-w-3xl text-sm text-muted">Voided {{ $receipt->voided_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i') }} by {{ $receipt->voidedBy?->name }}. {{ $receipt->void_reason }}</p>
    @endif
    @if ($canVoid)
        <form method="POST" action="{{ route('receipts.void', $receipt) }}" class="mt-6 max-w-xl space-y-3 rounded border border-line bg-white p-3">
            @csrf
            <label for="void_reason" class="block text-sm font-medium">Void reason</label>
            <textarea id="void_reason" name="void_reason" required minlength="3" maxlength="1000" rows="3" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Void receipt</button>
        </form>
    @endif
</x-layouts.app>
