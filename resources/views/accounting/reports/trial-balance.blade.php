<x-layouts.app title="Trial balance">
    <h1 class="text-xl font-semibold">Trial balance</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Posted journals only. Drafts are left out. Amounts are BDT. Debits minus credits must be zero. A non-zero difference is a defect in the ledger.</p>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Code</th>
                    <th class="px-3 py-2 font-medium">Account</th>
                    <th class="px-3 py-2 font-medium">Type</th>
                    <th class="px-3 py-2 text-right font-medium">Debit</th>
                    <th class="px-3 py-2 text-right font-medium">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr class="border-b border-line">
                        <td class="px-3 py-2">{{ $row['code'] }}</td>
                        <td class="px-3 py-2">{{ $row['name'] }}{{ $row['active'] ? '' : ' (inactive)' }}</td>
                        <td class="px-3 py-2">{{ $row['type'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['debit'])->groupedAmount() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['credit'])->groupedAmount() }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-line font-medium">
                <tr>
                    <td colspan="3" class="px-3 py-2">Totals</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['debit_total'])->groupedAmount() }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($report['credit_total'])->groupedAmount() }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="px-3 py-2">Difference (debits minus credits)</td>
                    <td colspan="2" class="px-3 py-2 text-right tabular-nums {{ $report['difference'] === '0.00' ? '' : 'text-red-800' }}">{{ \App\Support\Money::of($report['difference'])->groupedAmount() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-layouts.app>
