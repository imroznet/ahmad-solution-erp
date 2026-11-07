<x-layouts.app :title="$record ? 'Edit record' : 'New record'">
    <h1 class="text-xl font-semibold">{{ $record ? 'Edit' : 'New' }} {{ \App\Tax\TaxType::label($taxType) }} record</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Choose the direction. Do not treat every supplier payment or customer receipt as subject to this tax. The preview, if you pin a rule, is not a certificate.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @php($route = $taxType === 'vds' ? 'vds' : 'ait')
    <form method="POST" action="{{ $record ? route($route.'.update', $record) : route($route.'.store') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($record) @method('PUT') @endif
        <div>
            <label for="direction" class="block text-sm font-medium">Direction</label>
            <select id="direction" name="direction" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="withheld_from_us" @selected(old('direction', $record?->direction ?? '') === 'withheld_from_us')>Withheld from Ahmad Solution Ltd. by a customer</option>
                <option value="deducted_by_us" @selected(old('direction', $record?->direction ?? '') === 'deducted_by_us')>Deducted by Ahmad Solution Ltd. from a supplier</option>
            </select>
        </div>
        <div>
            <label for="party_id" class="block text-sm font-medium">Other party</label>
            <select id="party_id" name="party_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                @foreach ($parties as $party)
                    <option value="{{ $party->id }}" @selected((string) old('party_id', $record?->party_id ?? '') === (string) $party->id)>{{ $party->legal_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="supply_classification" class="block text-sm font-medium">Classification</label>
            <input id="supply_classification" name="supply_classification" required value="{{ old('supply_classification', $record?->supply_classification ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="rule_code" class="block text-sm font-medium">Rule code</label>
            <input id="rule_code" name="rule_code" value="{{ old('rule_code', $record?->rule_code ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="basis_amount" class="block text-sm font-medium">Base amount (BDT)</label>
            <input id="basis_amount" name="basis_amount" required value="{{ old('basis_amount', $record?->basis_amount) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="record_date" class="block text-sm font-medium">Date</label>
            <input id="record_date" name="record_date" type="date" required value="{{ old('record_date', $record?->dateString('record_date')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="commercial_invoice_id" class="block text-sm font-medium">Issued commercial invoice, if any</label>
            <input id="commercial_invoice_id" name="commercial_invoice_id" value="{{ old('commercial_invoice_id', $record?->commercial_invoice_id ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="underlying_reference" class="block text-sm font-medium">Other underlying reference</label>
            <input id="underlying_reference" name="underlying_reference" value="{{ old('underlying_reference', $record?->underlying_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="certificate_reference" class="block text-sm font-medium">Certificate reference received</label>
            <input id="certificate_reference" name="certificate_reference" value="{{ old('certificate_reference', $record?->certificate_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="treasury_reference" class="block text-sm font-medium">Treasury or challan reference</label>
            <input id="treasury_reference" name="treasury_reference" value="{{ old('treasury_reference', $record?->treasury_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="pinned_tax_rule_version_id" class="block text-sm font-medium">Approved {{ \App\Tax\TaxType::label($taxType) }} rule</label>
            <select id="pinned_tax_rule_version_id" name="pinned_tax_rule_version_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="">No rule. Leave the amount for review.</option>
                @foreach ($rules as $rule)
                    <option value="{{ $rule->id }}" @selected((string) old('pinned_tax_rule_version_id', $record?->pinned_tax_rule_version_id ?? '') === (string) $rule->id)>{{ $rule->supply_classification }} · version {{ $rule->version_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $record?->internal_notes ?? '') }}</textarea>
        </div>
        <div class="sm:col-span-2"><button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white" type="submit">Save record</button></div>
    </form>
</x-layouts.app>
