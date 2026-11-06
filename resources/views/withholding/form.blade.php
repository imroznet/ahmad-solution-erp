<x-layouts.app :title="'Record '.strtoupper($kind)">
    <h1 class="text-xl font-semibold">{{ $record ? 'Update' : 'Record' }} {{ strtoupper($kind) }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Enter the amount yourself. Choosing a party does not create a second organisation, and it does not calculate VAT.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $record ? route($kind.'.update', $record) : route($kind.'.store') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($record) @method('PUT') @endif
        <div class="sm:col-span-2">
            <label for="direction" class="block text-sm font-medium">Direction</label>
            <select id="direction" name="direction" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                @foreach ($directions as $direction)
                    <option value="{{ $direction }}" @selected(old('direction', $record?->direction) === $direction)>{{ \App\Tax\WithholdingDirection::label($direction) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="party_id" class="block text-sm font-medium">Existing party number</label>
            <input id="party_id" name="party_id" value="{{ old('party_id', $record?->party_id) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="party_name" class="block text-sm font-medium">Name if the party is not saved</label>
            <input id="party_name" name="party_name" value="{{ old('party_name', $record?->party_name) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="supply_description" class="block text-sm font-medium">Underlying supply or payment</label>
            <input id="supply_description" name="supply_description" required value="{{ old('supply_description', $record?->supply_description) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="taxable_basis" class="block text-sm font-medium">Basis, if you have it</label>
            <input id="taxable_basis" name="taxable_basis" value="{{ old('taxable_basis', $record?->taxable_basis) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="amount" class="block text-sm font-medium">Amount</label>
            <input id="amount" name="amount" required value="{{ old('amount', $record?->amount) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="record_date" class="block text-sm font-medium">Date</label>
            <input id="record_date" name="record_date" type="date" required value="{{ old('record_date', $record?->record_date?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="certificate_reference" class="block text-sm font-medium">Certificate or reference, if you have one</label>
            <input id="certificate_reference" name="certificate_reference" value="{{ old('certificate_reference', $record?->certificate_reference) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="payment_reference" class="block text-sm font-medium">Challan or payment reference</label>
            <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference', $record?->payment_reference) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="mushak_invoice_id" class="block text-sm font-medium">Linked Mushak number in the system</label>
            <input id="mushak_invoice_id" name="mushak_invoice_id" value="{{ old('mushak_invoice_id', $record?->mushak_invoice_id) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $record?->internal_notes) }}</textarea>
        </div>
        <div><button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save</button></div>
    </form>
</x-layouts.app>
