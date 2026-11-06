<x-layouts.app :title="$invoice ? 'Edit invoice' : 'New commercial invoice'">
    <h1 class="text-xl font-semibold">{{ $invoice ? 'Edit draft invoice' : 'New commercial invoice' }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $quotation->number }} · {{ $revision->party_legal_name }}. Each line bills a quantity from one issued challan line. Use 0 to leave a line off this invoice. Prices come from the accepted quotation.</p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($rows === [])
        <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">Issue a delivery challan before creating an invoice.</p>
    @endif

    <form method="POST" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}" class="mt-4 space-y-4">
        @csrf
        @if ($invoice)
            @method('PUT')
        @endif
        <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
        <div class="grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
            <div>
                <label for="invoice_date" class="block text-sm font-medium">Invoice date</label>
                <input id="invoice_date" name="invoice_date" type="date" required value="{{ old('invoice_date', $invoice?->invoice_date?->toDateString() ?? $today) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="customer_notes" class="block text-sm font-medium">Notes for the customer</label>
                <textarea id="customer_notes" name="customer_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('customer_notes', $invoice?->customer_notes) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
                <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $invoice?->internal_notes) }}</textarea>
                <p class="mt-1 text-xs text-muted">Internal notes stay off the customer PDF.</p>
            </div>
        </div>

        @if ($rows !== [])
            <div class="overflow-x-auto rounded border border-line bg-white">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-muted">
                        <tr>
                            <th class="px-3 py-2 font-medium">Challan</th>
                            <th class="px-3 py-2 font-medium">Description</th>
                            <th class="px-3 py-2 font-medium">Delivered</th>
                            <th class="px-3 py-2 font-medium">Already invoiced</th>
                            <th class="px-3 py-2 font-medium">This invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr class="border-t border-line">
                                <td class="px-3 py-2">{{ $row['challan'] }}</td>
                                <td class="px-3 py-2">{{ $row['description'] }} <span class="text-muted">{{ $row['unit'] }}</span></td>
                                <td class="px-3 py-2">{{ $row['delivered'] }}</td>
                                <td class="px-3 py-2">{{ $row['invoiced'] }}</td>
                                <td class="px-3 py-2">
                                    <input type="hidden" name="lines[{{ $index }}][delivery_challan_line_id]" value="{{ $row['id'] }}">
                                    <input name="lines[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" inputmode="decimal" class="w-32 rounded border border-line px-3 py-2 text-sm" aria-label="Quantity for {{ $row['description'] }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
        @endif
    </form>
</x-layouts.app>
