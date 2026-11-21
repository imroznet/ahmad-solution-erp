<x-layouts.app title="Record receipt">
    <h1 class="text-xl font-semibold">Record receipt</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Money received, VDS deducted, and AIT deducted are typed. At least one has to be more than zero. Allocate issued invoices of this customer dated on or before the receipt. Anything left over stays as an advance on Accounts Receivable. Amounts are BDT.</p>
    @include('accounting.partials.errors')
    @php
        $submitted = old('allocations', array_fill(0, 6, ['commercial_invoice_id' => '', 'amount' => '']));
    @endphp
    <form method="POST" action="{{ route('receipts.store') }}" class="mt-6 max-w-5xl space-y-4">
        @csrf
        <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2">
            <div>
                <label for="party_id" class="block text-sm font-medium">Customer</label>
                <select id="party_id" name="party_id" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">Choose</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((string) old('party_id') === (string) $party->id)>{{ $party->legal_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="account_id" class="block text-sm font-medium">Received into</label>
                <select id="account_id" name="account_id" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">Choose</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="receipt_date" class="block text-sm font-medium">Date</label>
                <input id="receipt_date" name="receipt_date" type="date" required value="{{ old('receipt_date', $today) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
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
                <label for="amount_received" class="block text-sm font-medium">Amount received</label>
                <input id="amount_received" name="amount_received" value="{{ old('amount_received', '0.00') }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="reference" class="block text-sm font-medium">Reference</label>
                <input id="reference" name="reference" value="{{ old('reference') }}" maxlength="80" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="cash_flow_category" class="block text-sm font-medium">Cash flow</label>
                <select id="cash_flow_category" name="cash_flow_category" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach (\App\Accounting\CashFlowCategory::ordered() as $category)
                        <option value="{{ $category->value }}" @selected(old('cash_flow_category', 'operating') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="vds_deducted" class="block text-sm font-medium">VDS deducted</label>
                <input id="vds_deducted" name="vds_deducted" value="{{ old('vds_deducted', '0.00') }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="ait_deducted" class="block text-sm font-medium">AIT deducted</label>
                <input id="ait_deducted" name="ait_deducted" value="{{ old('ait_deducted', '0.00') }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
        </div>
        <div class="space-y-3">
            @foreach ($submitted as $index => $row)
                <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-[1.4fr_12rem]">
                    <div>
                        <label for="invoice-{{ $index }}" class="block text-sm font-medium">Invoice</label>
                        <select id="invoice-{{ $index }}" name="allocations[{{ $index }}][commercial_invoice_id]" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                            <option value="">None</option>
                            @foreach ($invoices as $invoice)
                                <option value="{{ $invoice->id }}" @selected((string) ($row['commercial_invoice_id'] ?? '') === (string) $invoice->id)>{{ $invoice->displayNumber() }} · {{ $invoice->quotation?->party?->legal_name }}</option>
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
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Record receipt</button>
            <a href="{{ route('receipts.index') }}" class="rounded border border-line px-3 py-2 text-sm">Cancel</a>
        </div>
    </form>
</x-layouts.app>
