@php
    $title = $kind === 'vds' ? 'New VDS record' : 'New AIT record';
@endphp
<x-layouts.app :title="$title">
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Type the deducted amount. It is not calculated from a rate, and it is not added to output VAT. A typed counterparty is not saved as a party.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route($kind.'.store') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        <div>
            <label for="direction" class="block text-sm font-medium">Direction</label>
            <select id="direction" name="direction" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                @foreach (\App\Tax\WithholdingDirection::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('direction') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="counterparty_source" class="block text-sm font-medium">Counterparty</label>
            <select id="counterparty_source" name="counterparty_source" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="party" @selected(old('counterparty_source', 'party') === 'party')>Existing party</option>
                <option value="manual" @selected(old('counterparty_source') === 'manual')>Typed for this record only</option>
            </select>
        </div>
        <div>
            <label for="party_id" class="block text-sm font-medium">Customer or supplier</label>
            <select id="party_id" name="party_id" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">None</option>
                <optgroup label="Customers">
                    @foreach ($customers as $party)
                        <option value="{{ $party->id }}" @selected((string) old('party_id') === (string) $party->id)>{{ $party->legal_name }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Suppliers">
                    @foreach ($suppliers as $party)
                        <option value="{{ $party->id }}" @selected((string) old('party_id') === (string) $party->id)>{{ $party->legal_name }}</option>
                    @endforeach
                </optgroup>
            </select>
        </div>
        <div>
            <label for="counterparty_name" class="block text-sm font-medium">Typed name</label>
            <input id="counterparty_name" name="counterparty_name" value="{{ old('counterparty_name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="counterparty_bin" class="block text-sm font-medium">BIN</label>
            <input id="counterparty_bin" name="counterparty_bin" value="{{ old('counterparty_bin') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="counterparty_tin" class="block text-sm font-medium">TIN</label>
            <input id="counterparty_tin" name="counterparty_tin" value="{{ old('counterparty_tin') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="underlying_reference" class="block text-sm font-medium">Underlying document</label>
            <input id="underlying_reference" name="underlying_reference" value="{{ old('underlying_reference') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="basis_amount" class="block text-sm font-medium">Basis, if typed</label>
            <input id="basis_amount" name="basis_amount" value="{{ old('basis_amount') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="deducted_amount" class="block text-sm font-medium">Deducted amount</label>
            <input id="deducted_amount" name="deducted_amount" value="{{ old('deducted_amount') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="record_date" class="block text-sm font-medium">Date</label>
            <input id="record_date" name="record_date" value="{{ old('record_date') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="certificate_number" class="block text-sm font-medium">Certificate or reference</label>
            <input id="certificate_number" name="certificate_number" value="{{ old('certificate_number') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="treasury_reference" class="block text-sm font-medium">Treasury, challan, or payment reference</label>
            <input id="treasury_reference" name="treasury_reference" value="{{ old('treasury_reference') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="mushak_document_id" class="block text-sm font-medium">Mushak 6.3 id, optional</label>
            <input id="mushak_document_id" name="mushak_document_id" value="{{ old('mushak_document_id') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('notes') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes') }}</textarea>
        </div>
        <label class="sm:col-span-2 flex items-start gap-2 text-sm"><input type="checkbox" name="confirmed" value="1"> An approved rule was selected below and I am recording that treatment. Otherwise this stays marked for review.</label>
        <div>
            <label for="tax_rule_id" class="block text-sm font-medium">Approved rule id, optional</label>
            <input id="tax_rule_id" name="tax_rule_id" value="{{ old('tax_rule_id') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2"><button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save record</button></div>
    </form>
</x-layouts.app>
