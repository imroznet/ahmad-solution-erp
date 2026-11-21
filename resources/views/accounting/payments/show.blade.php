<x-layouts.app :title="$payment->number">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ $payment->number }}</h1>
        <a href="{{ route('payments.index') }}" class="text-sm text-brand hover:text-brand-dark">All payments</a>
    </div>
    @include('accounting.partials.errors')
    <dl class="mt-6 grid max-w-3xl gap-3 rounded border border-line bg-white p-3 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Date</dt><dd>{{ $payment->paymentDateString() }}</dd></div>
        <div><dt class="text-muted">Status</dt><dd>{{ $payment->status->label() }}</dd></div>
        <div><dt class="text-muted">Supplier</dt><dd>{{ $payment->party?->legal_name }}</dd></div>
        <div><dt class="text-muted">Paid from</dt><dd>{{ $payment->account?->code }} {{ $payment->account?->name }}</dd></div>
        <div><dt class="text-muted">Amount paid</dt><dd class="tabular-nums">{{ \App\Support\Money::of($payment->decimalAttribute('amount'))->groupedAmount() }}</dd></div>
        <div><dt class="text-muted">Cash flow</dt><dd>{{ \App\Accounting\CashFlowCategory::tryFrom((string) $payment->cash_flow_category)?->label() }}</dd></div>
        <div><dt class="text-muted">Reference</dt><dd>{{ $payment->reference }}</dd></div>
    </dl>
    <h2 class="mt-6 text-sm font-medium">Allocations</h2>
    <div class="mt-2 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Journal</th>
                    <th class="px-3 py-2 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payment->allocations as $allocation)
                    <tr class="border-b border-line last:border-b-0">
                        <td class="px-3 py-2">{{ $allocation->journal?->number }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($allocation->decimalAttribute('amount'))->groupedAmount() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-3 py-4 text-muted">Nothing is allocated. The whole payment is an advance.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($payment->status === \App\Accounting\PaymentStatus::Void)
        <p class="mt-4 max-w-3xl text-sm text-muted">Voided {{ $payment->voided_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i') }} by {{ $payment->voidedBy?->name }}. {{ $payment->void_reason }}</p>
    @endif
    @if ($canVoid)
        <form method="POST" action="{{ route('payments.void', $payment) }}" class="mt-6 max-w-xl space-y-3 rounded border border-line bg-white p-3">
            @csrf
            <label for="void_reason" class="block text-sm font-medium">Void reason</label>
            <textarea id="void_reason" name="void_reason" required minlength="3" maxlength="1000" rows="3" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Void payment</button>
        </form>
    @endif
</x-layouts.app>
