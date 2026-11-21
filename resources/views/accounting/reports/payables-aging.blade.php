<x-layouts.app title="Payables aging">
    <h1 class="text-xl font-semibold">Payables aging</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Posted journals that credit Accounts Payable, opening Accounts Payable, and manual payable adjustments, as at {{ $report['as_of'] }} (Asia/Dhaka). A journal dated after that day is left out. Drafts are left out. Allocations are from payments that are not void and are dated on or before that day. An unallocated payment remainder is an advance and reduces the supplier total. Amounts are BDT.</p>
    @forelse ($report['parties'] as $party)
        <section class="mt-6">
            <h2 class="text-sm font-medium">{{ $party['name'] }}</h2>
            <div class="mt-2 overflow-x-auto rounded border border-line bg-white">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-line text-left text-muted">
                        <tr>
                            <th class="px-3 py-2 font-medium">Document</th>
                            <th class="px-3 py-2 font-medium">Date</th>
                            <th class="px-3 py-2 text-right font-medium">Payable</th>
                            <th class="px-3 py-2 text-right font-medium">Allocated</th>
                            <th class="px-3 py-2 text-right font-medium">Outstanding</th>
                            <th class="px-3 py-2 font-medium">Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($party['lines'] as $line)
                            <tr class="border-b border-line last:border-b-0">
                                <td class="px-3 py-2">{{ $line['label'] }}</td>
                                <td class="px-3 py-2">{{ $line['date'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line['invoiced'])->groupedAmount() }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line['allocated'])->groupedAmount() }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line['outstanding'])->groupedAmount() }}</td>
                                <td class="px-3 py-2">{{ ['current' => 'Current', '1_30' => '1–30', '31_60' => '31–60', '61_90' => '61–90', '90_plus' => '90+'][$line['bucket']] ?? $line['bucket'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-muted">No open payables. The advance is the supplier balance.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-line font-medium">
                        <tr>
                            <td colspan="4" class="px-3 py-2">Advance</td>
                            <td colspan="2" class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($party['advance'])->groupedAmount() }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-3 py-2">Total</td>
                            <td colspan="2" class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($party['total'])->groupedAmount() }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-3 py-2">Accounts payable</td>
                            <td colspan="2" class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($party['payable'])->groupedAmount() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @empty
        <p class="mt-6 text-sm text-muted">No payable journals, opening payables, adjustments, or supplier payments as at this date.</p>
    @endforelse
    @if ($report['parties'] !== [])
        <p class="mt-4 text-sm font-medium">All suppliers {{ \App\Support\Money::of($report['total'])->groupedAmount() }}</p>
    @endif
</x-layouts.app>
