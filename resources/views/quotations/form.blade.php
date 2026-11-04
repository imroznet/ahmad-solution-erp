<x-layouts.app :title="$mode === 'create' ? 'New quotation' : ($mode === 'revise' ? 'New revision' : 'Edit draft')">
    @php
        $initialLines = old('lines', $lines);
        if (! is_array($initialLines) || $initialLines === []) {
            $initialLines = [['description' => '', 'unit' => '', 'quantity' => '1', 'unit_price' => '0.00']];
        }
        $action = $mode === 'create'
            ? route('quotations.store')
            : ($mode === 'revise' ? route('quotations.revisions.store', $quotation) : route('quotations.update', $quotation));
        $heading = $mode === 'create' ? 'New quotation' : ($mode === 'revise' ? 'New revision' : 'Edit draft');
        $date = old('quotation_date', $revision?->quotation_date?->format('Y-m-d') ?? now()->timezone('Asia/Dhaka')->toDateString());
        $valid = old('valid_until', $revision?->valid_until?->format('Y-m-d') ?? now()->timezone('Asia/Dhaka')->addDays(30)->toDateString());
    @endphp
    <h1 class="text-xl font-semibold">{{ $heading }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">
        @if ($mode === 'revise')
            Saving creates revision {{ ($revision->revision_number ?? 0) + 1 }} and leaves revision {{ $revision->revision_number ?? 1 }} unchanged. The customer snapshot is taken again from the current party record.
        @else
            Lines are typed by hand. Tax is excluded. Saving a draft refreshes the customer snapshot from the current party record.
        @endif
    </p>

    <form method="POST" action="{{ $action }}" class="mt-6 max-w-5xl space-y-4">
        @csrf
        @if ($mode === 'draft') @method('PUT') @endif

        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The quotation could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="party_id" class="block text-sm font-medium">Customer</label>
                @if ($mode === 'revise')
                    <p class="mt-1 text-sm">{{ $party->legal_name }}</p>
                    <input type="hidden" name="party_id" value="{{ $party->id }}">
                @else
                    <select id="party_id" name="party_id" required class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm" @if ($mode === 'create') onchange="window.location='{{ route('quotations.create') }}?party=' + this.value" @else onchange="window.location='{{ route('quotations.edit', $quotation) }}?party=' + this.value" @endif>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('party_id', $party->id) === (int) $customer->id)>{{ $customer->legal_name }}</option>
                        @endforeach
                    </select>
                    @if ($mode === 'draft')
                        <p class="mt-1 text-xs text-muted">Changing the customer reloads this form.</p>
                    @endif
                @endif
            </div>
            <div>
                <label for="quotation_date" class="block text-sm font-medium">Quotation date</label>
                <input id="quotation_date" name="quotation_date" type="date" required value="{{ $date }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="valid_until" class="block text-sm font-medium">Valid until</label>
                <input id="valid_until" name="valid_until" type="date" required value="{{ $valid }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
        </div>

        <fieldset class="rounded border border-line bg-white p-3">
            <legend class="px-1 text-sm font-medium">Contact snapshot</legend>
            @forelse ($party->contacts as $contact)
                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="radio" name="contact_id" value="{{ $contact->id }}" @checked((string) old('contact_id', $party->contacts->firstWhere('is_primary', true)?->id) === (string) $contact->id)>
                    <span>{{ $contact->name }}@if ($contact->designation), {{ $contact->designation }}@endif</span>
                </label>
            @empty
                <p class="mt-2 text-sm text-muted">No active contact. The party phone and email will be copied.</p>
            @endforelse
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <fieldset class="rounded border border-line bg-white p-3">
                <legend class="px-1 text-sm font-medium">Billing address</legend>
                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="radio" name="billing_address_id" value="" @checked(old('billing_address_id', '') === '')>
                    <span>Default billing address, if one is active</span>
                </label>
                @foreach ($party->addresses as $address)
                    <label class="mt-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="billing_address_id" value="{{ $address->id }}" @checked((string) old('billing_address_id') === (string) $address->id)>
                        <span>{{ $address->address_line_1 }}, {{ $address->district }}</span>
                    </label>
                @endforeach
            </fieldset>
            <fieldset class="rounded border border-line bg-white p-3">
                <legend class="px-1 text-sm font-medium">Delivery address</legend>
                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="radio" name="shipping_address_id" value="" @checked(old('shipping_address_id', '') === '')>
                    <span>Default delivery address, if one is active</span>
                </label>
                @foreach ($party->addresses as $address)
                    <label class="mt-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="shipping_address_id" value="{{ $address->id }}" @checked((string) old('shipping_address_id') === (string) $address->id)>
                        <span>{{ $address->address_line_1 }}, {{ $address->district }}</span>
                    </label>
                @endforeach
            </fieldset>
        </div>

        <div x-data="{ lines: {{ \Illuminate\Support\Js::from($initialLines) }}, max: {{ (int) config('asl.quotations.max_lines', 50) }} }" class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium">Lines</h2>
                <button type="button" class="rounded border border-line px-3 py-2 text-sm" @click="if (lines.length < max) lines.push({description: '', unit: '', quantity: '1', unit_price: '0.00'})">Add line</button>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-12">
                    <div class="sm:col-span-5">
                        <label class="block text-sm font-medium" :for="'description-' + index">Description</label>
                        <textarea :id="'description-' + index" :name="'lines[' + index + '][description]'" x-model="line.description" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium" :for="'unit-' + index">Unit</label>
                        <input :id="'unit-' + index" :name="'lines[' + index + '][unit]'" x-model="line.unit" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium" :for="'quantity-' + index">Quantity</label>
                        <input :id="'quantity-' + index" :name="'lines[' + index + '][quantity]'" x-model="line.quantity" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium" :for="'price-' + index">Unit price</label>
                        <input :id="'price-' + index" :name="'lines[' + index + '][unit_price]'" x-model="line.unit_price" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end sm:col-span-1">
                        <button type="button" class="text-sm text-muted" @click="if (lines.length > 1) lines.splice(index, 1)">Remove</button>
                    </div>
                </div>
            </template>
        </div>

        <div>
            <label for="terms" class="block text-sm font-medium">Terms</label>
            <textarea id="terms" name="terms" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('terms', $revision?->terms) }}</textarea>
        </div>
        <div>
            <label for="customer_notes" class="block text-sm font-medium">Notes for the customer</label>
            <textarea id="customer_notes" name="customer_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('customer_notes', $revision?->customer_notes) }}</textarea>
        </div>
        <div>
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $revision?->internal_notes) }}</textarea>
            <p class="mt-1 text-xs text-muted">Internal notes stay off the PDF and the customer link.</p>
        </div>

        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save</button>
    </form>
</x-layouts.app>
