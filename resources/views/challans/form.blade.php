<x-layouts.app :title="$challan ? 'Edit challan' : 'New delivery challan'">
    <h1 class="text-xl font-semibold">{{ $challan ? 'Edit draft challan' : 'New delivery challan' }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $quotation->number }} · {{ $revision->party_legal_name }} · Revision {{ $revision->revision_number }}. Enter the quantity delivered now. Use 0 to leave a line off this challan.</p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $challan ? route('challans.update', $challan) : route('challans.store') }}" class="mt-4 space-y-4">
        @csrf
        @if ($challan)
            @method('PUT')
        @endif
        <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">

        <div class="grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
            <div>
                <label for="challan_date" class="block text-sm font-medium">Challan date</label>
                <input id="challan_date" name="challan_date" type="date" required value="{{ old('challan_date', $challan?->challan_date?->toDateString() ?? $today) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="delivery_location" class="block text-sm font-medium">Delivery location</label>
                <input id="delivery_location" name="delivery_location" value="{{ old('delivery_location', $challan?->delivery_location) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="recipient_name" class="block text-sm font-medium">Recipient</label>
                <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $challan?->recipient_name) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="recipient_phone" class="block text-sm font-medium">Recipient phone</label>
                <input id="recipient_phone" name="recipient_phone" value="{{ old('recipient_phone', $challan?->recipient_phone) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="vehicle_or_carrier" class="block text-sm font-medium">Vehicle or carrier</label>
                <input id="vehicle_or_carrier" name="vehicle_or_carrier" value="{{ old('vehicle_or_carrier', $challan?->vehicle_or_carrier) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="carrier_reference" class="block text-sm font-medium">Carrier reference</label>
                <input id="carrier_reference" name="carrier_reference" value="{{ old('carrier_reference', $challan?->carrier_reference) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="customer_notes" class="block text-sm font-medium">Notes for the customer</label>
                <textarea id="customer_notes" name="customer_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('customer_notes', $challan?->customer_notes) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
                <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $challan?->internal_notes) }}</textarea>
                <p class="mt-1 text-xs text-muted">Internal notes stay off the customer PDF.</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Description</th>
                        <th class="px-3 py-2 font-medium">Accepted</th>
                        <th class="px-3 py-2 font-medium">Already issued</th>
                        <th class="px-3 py-2 font-medium">This delivery</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr class="border-t border-line">
                            <td class="px-3 py-2">{{ $row['description'] }} <span class="text-muted">{{ $row['unit'] }}</span></td>
                            <td class="px-3 py-2">{{ $row['accepted'] }}</td>
                            <td class="px-3 py-2">{{ $row['delivered'] }}</td>
                            <td class="px-3 py-2">
                                <input type="hidden" name="lines[{{ $index }}][quotation_line_id]" value="{{ $row['id'] }}">
                                <input name="lines[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" inputmode="decimal" class="w-32 rounded border border-line px-3 py-2 text-sm" aria-label="Quantity for {{ $row['description'] }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
    </form>
</x-layouts.app>
