<x-layouts.app :title="$invoice->displayNumber()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $invoice->displayNumber() }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $invoice->party_legal_name }} · {{ $invoice->statusLabel() }} · {{ $invoice->quotation?->number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPdf)
                <a href="{{ route('invoices.pdf', $invoice) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>
            @endif
            @if ($canMushak)
                <a href="{{ route('mushak.create', ['invoice' => $invoice->id]) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Mushak 6.3</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('invoices.edit', $invoice) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">{{ $invoice->tax_notice }}</p>

    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Invoice date</dt><dd>{{ $invoice->invoice_date?->format('d M Y') }}</dd></div>
        <div><dt class="text-muted">Total</dt><dd>{{ $invoice->totalMoney()->format() }}</dd></div>
        <div><dt class="text-muted">Tax treatment</dt><dd>Excluded</dd></div>
        @foreach ($invoice->addressLines('billing_address') as $line)
            <div class="sm:col-span-2"><dt class="text-muted">Billing address snapshot</dt><dd>{{ $line }}</dd></div>
        @endforeach
    </dl>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Challan</th>
                    <th class="px-3 py-2 font-medium">Description</th>
                    <th class="px-3 py-2 text-right font-medium">Quantity</th>
                    <th class="px-3 py-2 text-right font-medium">Rate</th>
                    <th class="px-3 py-2 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $line->challanLine?->challan?->displayNumber() }}</td>
                        <td class="px-3 py-2">{{ $line->description }} <span class="text-muted">{{ $line->unit }}</span></td>
                        <td class="px-3 py-2 text-right">{{ $line->quantityLabel() }}</td>
                        <td class="px-3 py-2 text-right">{{ $line->unitPriceMoney()->format() }}</td>
                        <td class="px-3 py-2 text-right">{{ $line->lineTotalMoney()->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($invoice->stringAttribute('status') === 'draft')
        <p class="mt-3 text-sm text-muted">Amounts are calculated again when the invoice is issued, so a later invoice cannot push the line above the accepted total.</p>
    @endif

    @if ($invoice->customer_notes)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Notes for the customer</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $invoice->customer_notes }}</p>
        </section>
    @endif
    @if ($invoice->internal_notes)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Internal notes</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $invoice->internal_notes }}</p>
        </section>
    @endif
    @if ($invoice->void_reason)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Void reason</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $invoice->void_reason }}</p>
        </section>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @if ($canIssue)
            <form method="POST" action="{{ route('invoices.issue', $invoice) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <h2 class="text-sm font-medium">Issue</h2>
                <p class="mt-1 text-sm text-muted">Issuing assigns the next number and freezes the lines, amounts, and snapshots.</p>
                <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Issue invoice</button>
            </form>
        @endif
        @if ($canVoid)
            <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <h2 class="text-sm font-medium">Void</h2>
                <p class="mt-1 text-sm text-muted">A voided invoice does not count as invoiced. The challan stays as it is.</p>
                <label for="void_reason" class="mt-3 block text-sm">Reason</label>
                <textarea id="void_reason" name="void_reason" rows="2" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                <button type="submit" class="mt-3 rounded border border-line px-3 py-2 text-sm">Void invoice</button>
            </form>
        @endif
    </div>
</x-layouts.app>
