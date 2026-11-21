<x-layouts.app title="Record payment">
    <h1 class="text-xl font-semibold">Record payment</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">The amount is typed. Allocate posted journals that credit Accounts Payable for this supplier and are dated on or before the payment. Anything left over stays as an advance on Accounts Payable. The cash-flow classification is chosen here. It is not taken from the settlement account. Amounts are BDT.</p>
    @include('accounting.partials.errors')
    @php
        $submitted = old('allocations', array_fill(0, 6, ['journal_id' => '', 'amount' => '']));
    @endphp
    <form method="POST" action="{{ route('payments.store') }}" class="mt-6 max-w-5xl space-y-4">
        @csrf
        <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2">
            <div>
                <label for="party_id" class="block text-sm font-medium">Supplier</label>
                <select id="party_id" name="party_id" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">Choose</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((string) old('party_id') === (string) $party->id)>{{ $party->legal_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="account_id" class="block text-sm font-medium">Paid from</label>
                <select id="account_id" name="account_id" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">Choose</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="payment_date" class="block text-sm font-medium">Date</label>
                <input id="payment_date" name="payment_date" type="date" required value="{{ old('payment_date', $today) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="mode" class="block text-sm font-medium">Mode</label>
                <select id="mode" name="mode" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($modes as $value => $label)
                        <option value="{{ $value }}" @selected(old('mode', 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="amount" class="block text-sm font-medium">Amount paid</label>
                <input id="amount" name="amount" required value="{{ old('amount', '0.00') }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="reference" class="block text-sm font-medium">Reference</label>
                <input id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="cash_flow_category" class="block text-sm font-medium">Cash flow</label>
                <select id="cash_flow_category" name="cash_flow_category" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(old('cash_flow_category', 'operating') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="space-y-3">
            @foreach ($submitted as $index => $row)
                <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-[1.4fr_12rem]">
                    <div>
                        <label for="journal-{{ $index }}" class="block text-sm font-medium">Journal</label>
                        <select id="journal-{{ $index }}" name="allocations[{{ $index }}][journal_id]" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                            <option value="">None</option>
                            @foreach ($journals as $journal)
                                <option value="{{ $journal->id }}" @selected((string) ($row['journal_id'] ?? '') === (string) $journal->id)>{{ $journal->number }} · {{ $journal->journalDateString() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="allocation-{{ $index }}" class="block text-sm font-medium">Amount</label>
                        <input id="allocation-{{ $index }}" name="allocations[{{ $index }}][amount]" value="{{ $row['amount'] ?? '' }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Record payment</button>
            <a href="{{ route('payments.index') }}" class="rounded border border-line px-3 py-2 text-sm">Cancel</a>
        </div>
    </form>
</x-layouts.app>
