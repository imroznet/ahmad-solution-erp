<x-layouts.app title="Profit and loss">
    <h1 class="text-xl font-semibold">Profit and loss</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Posted income and expense from {{ $report['from'] }} to {{ $report['to'] }}. Drafts are left out. A year-close journal, and a reversal of that journal, are left out, so closing a year does not remove the period's result from this report. Amounts are BDT.</p>
    <form method="GET" action="{{ route('reports.profit-and-loss') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="from" class="block text-sm font-medium">From</label>
            <input id="from" name="from" type="date" value="{{ $report['from'] }}" class="mt-1 rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="block text-sm font-medium">To</label>
            <input id="to" name="to" type="date" value="{{ $report['to'] }}" class="mt-1 rounded border border-line px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Show</button>
    </form>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <tbody>
                <tr class="border-b border-line"><th colspan="2" class="px-3 py-2 text-left font-medium">Income</th></tr>
                @forelse ($report['income'] as $row)
                    <tr class="border-b border-line">
                        <td class="px-3 py-2">{{ $row['code'] }} {{ $row['name'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['amount'])->groupedAmount() }}</td>
                    </tr>
                @empty
                    <tr class="border-b border-line"><td colspan="2" class="px-3 py-2 text-muted">No income in this period.</td></tr>
                @endforelse
                <tr class="border-b border-line font-medium">
                    <td class="px-3 py-2">Income total</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['income_total'])->groupedAmount() }}</td>
                </tr>
                <tr class="border-b border-line"><th colspan="2" class="px-3 py-2 text-left font-medium">Expenses</th></tr>
                @forelse ($report['expenses'] as $row)
                    <tr class="border-b border-line">
                        <td class="px-3 py-2">{{ $row['code'] }} {{ $row['name'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['amount'])->groupedAmount() }}</td>
                    </tr>
                @empty
                    <tr class="border-b border-line"><td colspan="2" class="px-3 py-2 text-muted">No expenses in this period.</td></tr>
                @endforelse
                <tr class="border-b border-line font-medium">
                    <td class="px-3 py-2">Expense total</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['expense_total'])->groupedAmount() }}</td>
                </tr>
                <tr class="font-medium">
                    <td class="px-3 py-2">Profit</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['profit'])->groupedAmount() }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
