<x-layouts.app title="General ledger">
    <h1 class="text-xl font-semibold">General ledger</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Posted journals only, in date order. The running balance increases on the debit side for assets and expenses, and on the credit side for liabilities, equity, and income. Amounts are BDT.</p>
    @include('accounting.partials.errors')
    <form method="GET" action="{{ route('reports.general-ledger') }}" class="mt-6 grid max-w-4xl gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="account" class="block text-sm font-medium">Account</label>
            <select id="account" name="account" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="">Choose an account</option>
                @foreach ($accounts as $choice)
                    <option value="{{ $choice->id }}" @selected($account && $account->id === $choice->id)>{{ $choice->code }} {{ $choice->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-sm font-medium">From</label>
            <input id="from" name="from" type="date" value="{{ $from }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="block text-sm font-medium">To</label>
            <input id="to" name="to" type="date" value="{{ $to }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="party" class="block text-sm font-medium">Party</label>
            <select id="party" name="party" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="">All parties</option>
                @foreach ($parties as $choice)
                    <option value="{{ $choice->id }}" @selected((string) $party === (string) $choice->id)>{{ $choice->legal_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Show</button>
        </div>
    </form>

    @if ($account)
        <h2 class="mt-6 text-base font-semibold">{{ $account->code }} {{ $account->name }}</h2>
        <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Date</th>
                        <th class="px-3 py-2 font-medium">Journal</th>
                        <th class="px-3 py-2 font-medium">Party</th>
                        <th class="px-3 py-2 font-medium">Memo</th>
                        <th class="px-3 py-2 text-right font-medium">Debit</th>
                        <th class="px-3 py-2 text-right font-medium">Credit</th>
                        <th class="px-3 py-2 text-right font-medium">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-line last:border-b-0">
                            <td class="px-3 py-2">{{ $row['date'] }}</td>
                            <td class="px-3 py-2">
                                @if ($currentUser?->hasPermission('journals.view') && $row['journal_id'] > 0)
                                    <a class="text-brand hover:text-brand-dark" href="{{ route('journals.show', $row['journal_id']) }}">{{ $row['number'] }}</a>
                                @else
                                    {{ $row['number'] }}
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $row['party'] }}</td>
                            <td class="px-3 py-2">{{ $row['memo'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['debit'])->groupedAmount() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['credit'])->groupedAmount() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($row['balance'])->groupedAmount() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-muted">No posted lines for this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
