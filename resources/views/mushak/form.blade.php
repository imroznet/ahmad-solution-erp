<x-layouts.app :title="$document ? 'Edit Mushak working paper' : 'Mushak working paper'">
    <h1 class="text-xl font-semibold">{{ $document ? 'Edit Mushak working paper' : 'Prepare a Mushak working paper' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Lines are copied from the issued commercial invoice. VAT is not written back to that invoice. Issue of a statutory Mushak 6.3 stays blocked.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $document ? route('mushak.update', $document) : route('mushak.store') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4">
        @csrf
        @if ($document)
            @method('PUT')
        @else
            <div>
                <label for="commercial_invoice_id" class="block text-sm font-medium">Issued commercial invoice</label>
                <select id="commercial_invoice_id" name="commercial_invoice_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($invoices as $choice)
                        <option value="{{ $choice->id }}" @selected((int) old('commercial_invoice_id', $invoice?->id) === (int) $choice->id)>{{ $choice->displayNumber() }} · {{ $choice->party_legal_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="supply_classification" class="block text-sm font-medium">Supply classification</label>
            <input id="supply_classification" name="supply_classification" required value="{{ old('supply_classification', $document?->supply_classification ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="rule_code" class="block text-sm font-medium">Rule code</label>
            <input id="rule_code" name="rule_code" value="{{ old('rule_code', $document?->rule_code ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="pinned_tax_rule_version_id" class="block text-sm font-medium">Approved output VAT rule</label>
            <select id="pinned_tax_rule_version_id" name="pinned_tax_rule_version_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="">No rule pinned. The paper stays in review.</option>
                @foreach ($rules as $rule)
                    <option value="{{ $rule->id }}" @selected((string) old('pinned_tax_rule_version_id', $document?->pinned_tax_rule_version_id ?? '') === (string) $rule->id)>{{ $rule->supply_classification }} · version {{ $rule->version_number }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $document?->internal_notes ?? '') }}</textarea>
        </div>
        <button class="w-fit rounded bg-brand px-3 py-2 text-sm font-medium text-white" type="submit">Save working paper</button>
    </form>
</x-layouts.app>
