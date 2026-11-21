<x-layouts.app title="Balance sheet">
    <h1 class="text-xl font-semibold">Balance sheet</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Posted balances as at {{ $report['as_of'] }} (Asia/Dhaka). Drafts are left out. Current earnings is income minus expenses, including a year-close journal, so a closed year sits in Retained Earnings and an open year stays in current earnings. Assets minus liabilities minus equity and earnings must be zero. Amounts are BDT.</p>
    <form method="GET" action="{{ route('reports.balance-sheet') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="as_of" class="block text-sm font-medium">As at</label>
            <input id="as_of" name="as_of" type="date" value="{{ $report['as_of'] }}" class="mt-1 rounded border border-line px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Show</button>
    </form>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <tbody>
                @foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $heading)
                    <tr class="border-b border-line"><th colspan="2" class="px-3 py-2 text-left font-medium">{{ $heading }}</th></tr>
                    @foreach ($report[$key] as $row)
                        <tr class="border-b border-line">
                            <td class="px-3 py-2">{{ $row['code'] }} {{ $row['name'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['amount'])->groupedAmount() }}</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr class="border-b border-line">
                    <td class="px-3 py-2">Current earnings</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['earnings'])->groupedAmount() }}</td>
                </tr>
                <tr class="border-b border-line font-medium">
                    <td class="px-3 py-2">Assets</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['asset_total'])->groupedAmount() }}</td>
                </tr>
                <tr class="border-b border-line font-medium">
                    <td class="px-3 py-2">Liabilities</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['liability_total'])->groupedAmount() }}</td>
                </tr>
                <tr class="border-b border-line font-medium">
                    <td class="px-3 py-2">Equity and current earnings</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['equity_and_earnings'])->groupedAmount() }}</td>
                </tr>
                <tr class="font-medium">
                    <td class="px-3 py-2">Difference</td>
                    <td class="px-3 py-2 text-right tabular-nums {{ $report['difference'] === '0.00' ? '' : 'text-red-800' }}">{{ \App\Support\Money::of($report['difference'])->groupedAmount() }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
