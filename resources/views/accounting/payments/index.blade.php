<x-layouts.app title="Payments">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Payments</h1>
        @if ($canCreate)
            <a href="{{ route('payments.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Record payment</a>
        @endif
    </div>
    <p class="mt-1 max-w-3xl text-sm text-muted">A supplier payment posts when it is recorded. It debits Accounts Payable and credits the settlement account. It is not edited. Void it and record another. VDS and AIT are not entered here.</p>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Supplier</th>
                    <th class="px-3 py-2 text-right font-medium">Paid</th>
                    <th class="px-3 py-2 font-medium">Cash flow</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr class="border-b border-line last:border-b-0">
                        <td class="px-3 py-2"><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('payments.show', $payment) }}">{{ $payment->number }}</a></td>
                        <td class="px-3 py-2">{{ $payment->paymentDateString() }}</td>
                        <td class="px-3 py-2">{{ $payment->party?->legal_name }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($payment->decimalAttribute('amount'))->groupedAmount() }}</td>
                        <td class="px-3 py-2">{{ \App\Accounting\CashFlowCategory::tryFrom((string) $payment->cash_flow_category)?->label() }}</td>
                        <td class="px-3 py-2">{{ $payment->status->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-muted">No payments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
