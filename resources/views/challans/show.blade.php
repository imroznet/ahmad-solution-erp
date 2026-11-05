<x-layouts.app :title="$challan->displayNumber()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $challan->displayNumber() }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $challan->party_legal_name }} · {{ $challan->statusLabel() }} · {{ $challan->quotation?->number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPdf)
                <a href="{{ route('challans.pdf', $challan) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('challans.edit', $challan) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
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

    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">Generating a PDF does not record dispatch or receipt. This challan is not a commercial invoice or a tax invoice.</p>

    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Challan date</dt><dd>{{ $challan->challan_date?->format('d M Y') }}</dd></div>
        <div><dt class="text-muted">Delivery location</dt><dd>{{ $challan->delivery_location ?: '—' }}</dd></div>
        <div><dt class="text-muted">Recipient</dt><dd>{{ $challan->recipient_name ?: '—' }}</dd></div>
        <div><dt class="text-muted">Phone</dt><dd>{{ $challan->recipient_phone ?: '—' }}</dd></div>
        <div><dt class="text-muted">Vehicle or carrier</dt><dd>{{ $challan->vehicle_or_carrier ?: '—' }}</dd></div>
        <div><dt class="text-muted">Carrier reference</dt><dd>{{ $challan->carrier_reference ?: '—' }}</dd></div>
        <div><dt class="text-muted">Dispatched</dt><dd>{{ $challan->dispatched_at ? $challan->dispatched_at->timezone('Asia/Dhaka')->format('d M Y H:i') : 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Acknowledged</dt><dd>{{ $challan->acknowledged_at ? $challan->acknowledged_at->timezone('Asia/Dhaka')->format('d M Y H:i').' · '.$challan->acknowledgment_name : 'Not recorded' }}</dd></div>
        @foreach ($challan->addressLines('shipping_address') as $line)
            <div class="sm:col-span-2"><dt class="text-muted">Delivery address snapshot</dt><dd>{{ $line }}</dd></div>
        @endforeach
    </dl>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Description</th>
                    <th class="px-3 py-2 font-medium">Unit</th>
                    <th class="px-3 py-2 text-right font-medium">Accepted</th>
                    <th class="px-3 py-2 text-right font-medium">This delivery</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($challan->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $line->description }}</td>
                        <td class="px-3 py-2">{{ $line->unit }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Quotations\QuotationCalculator::formatQuantity($line->decimalAttribute('accepted_quantity')) }}</td>
                        <td class="px-3 py-2 text-right">{{ $line->quantityLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($challan->customer_notes)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Notes for the customer</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $challan->customer_notes }}</p>
        </section>
    @endif
    @if ($challan->internal_notes)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Internal notes</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $challan->internal_notes }}</p>
        </section>
    @endif
    @if ($challan->void_reason)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Void reason</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $challan->void_reason }}</p>
        </section>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @if ($canIssue)
            <form method="POST" action="{{ route('challans.issue', $challan) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <h2 class="text-sm font-medium">Issue</h2>
                <p class="mt-1 text-sm text-muted">Issuing assigns the next number and freezes the quantities and customer snapshot.</p>
                <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Issue challan</button>
            </form>
        @endif
        @if ($canDispatch)
            <form method="POST" action="{{ route('challans.dispatch', $challan) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <h2 class="text-sm font-medium">Record dispatch</h2>
                <p class="mt-1 text-sm text-muted">The vehicle recorded on the draft stays as it was at issue.</p>
                <label for="dispatch-recipient" class="mt-3 block text-sm">Recipient on this event</label>
                <input id="dispatch-recipient" name="recipient_name" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <label for="dispatch-remarks" class="mt-3 block text-sm">Remarks</label>
                <textarea id="dispatch-remarks" name="remarks" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Record dispatch</button>
            </form>
        @endif
        @if ($canAcknowledge)
            <form method="POST" action="{{ route('challans.acknowledge', $challan) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <h2 class="text-sm font-medium">Record acknowledgment</h2>
                <p class="mt-1 text-sm text-muted">Acknowledgment does not require a dispatch record.</p>
                <label for="acknowledgment_name" class="mt-3 block text-sm">Name</label>
                <input id="acknowledgment_name" name="acknowledgment_name" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <label for="ack-remarks" class="mt-3 block text-sm">Remarks</label>
                <textarea id="ack-remarks" name="remarks" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Record acknowledgment</button>
            </form>
        @endif
        @if ($canVoid)
            <form method="POST" action="{{ route('challans.void', $challan) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <h2 class="text-sm font-medium">Void</h2>
                <p class="mt-1 text-sm text-muted">A voided challan does not count as delivered. Void any commercial invoice that uses it first.</p>
                <label for="void_reason" class="mt-3 block text-sm">Reason</label>
                <textarea id="void_reason" name="void_reason" rows="2" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                <button type="submit" class="mt-3 rounded border border-line px-3 py-2 text-sm">Void challan</button>
            </form>
        @endif
    </div>
</x-layouts.app>
