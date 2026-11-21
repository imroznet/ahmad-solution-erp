<x-layouts.app title="Cash flow">
    <h1 class="text-xl font-semibold">Cash flow</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Direct cash flow from {{ $report['from'] }} to {{ $report['to'] }}. Inflows are the amount received on receipts that are not void. Outflows are supplier payments that are not void. Each document keeps the operating, investing, or financing classification chosen when it was recorded. VDS and AIT are not cash. A manual journal that moves cash is left out. Amounts are BDT.</p>
    <form method="GET" action="{{ route('reports.cash-flow') }}" class="mt-4 flex flex-wrap items-end gap-3">
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
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Classification</th>
                    <th class="px-3 py-2 text-right font-medium">Inflow</th>
                    <th class="px-3 py-2 text-right font-medium">Outflow</th>
                    <th class="px-3 py-2 text-right font-medium">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['sections'] as $section)
                    <tr class="border-b border-line">
                        <td class="px-3 py-2">{{ $section['label'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($section['inflow'])->groupedAmount() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($section['outflow'])->groupedAmount() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($section['net'])->groupedAmount() }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-line font-medium">
                <tr>
                    <td class="px-3 py-2">Total</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['inflow'])->groupedAmount() }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['outflow'])->groupedAmount() }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['net'])->groupedAmount() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-layouts.app>
