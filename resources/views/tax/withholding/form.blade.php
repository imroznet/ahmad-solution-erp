<x-layouts.app :title="$title">
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Enter the basis and the tax amount. Nothing is calculated from a VAT percentage, and this amount is not added to output VAT. Choosing a saved party does not create a second organisation. Leaving the party blank keeps the typed name off the party register.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $action }}" class="mt-4 grid max-w-3xl gap-3 rounded border border-line bg-white p-4">
        @csrf
        <div>
            <label for="direction" class="block text-sm font-medium">Direction</label>
            <select id="direction" name="direction" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                @foreach ($directions as $direction)
                    <option value="{{ $direction }}" @selected(old('direction') === $direction)>{{ \App\Tax\WithholdingDirection::label($direction) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="record_date" class="block text-sm font-medium">Date</label>
            <input id="record_date" name="record_date" type="date" required value="{{ old('record_date', now()->timezone('Asia/Dhaka')->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="party_id" class="block text-sm font-medium">Saved party</label>
            <select id="party_id" name="party_id" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">Type a name instead</option>
                @foreach ($parties as $party)
                    <option value="{{ $party->id }}" @selected((string) old('party_id') === (string) $party->id)>{{ $party->legal_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="counterparty_name" class="block text-sm font-medium">Name, if not saved</label>
            <input id="counterparty_name" name="counterparty_name" value="{{ old('counterparty_name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="taxable_basis" class="block text-sm font-medium">Taxable basis</label>
            <input id="taxable_basis" name="taxable_basis" value="{{ old('taxable_basis') }}" inputmode="decimal" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="tax_amount" class="block text-sm font-medium">Tax amount</label>
            <input id="tax_amount" name="tax_amount" value="{{ old('tax_amount') }}" inputmode="decimal" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="certificate_reference" class="block text-sm font-medium">Certificate reference</label>
            <input id="certificate_reference" name="certificate_reference" value="{{ old('certificate_reference') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="payment_reference" class="block text-sm font-medium">Challan or payment reference</label>
            <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="notes" class="block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('notes') }}</textarea>
        </div>
        <button class="w-fit rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save record</button>
    </form>
</x-layouts.app>
