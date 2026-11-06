<x-layouts.app :title="$rule ? 'Edit tax rule' : 'New tax rule'">
    <h1 class="text-xl font-semibold">{{ $rule ? 'Edit draft tax rule' : 'New draft tax rule' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">An explanatory figure, if you enter one, is not copied onto a Mushak, VDS, or AIT record. Leave it blank unless a source has been checked.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $rule ? route('tax.rules.update', $rule) : route('tax.rules.store') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($rule) @method('PUT') @endif
        <label class="text-sm">Tax type
            <select name="tax_type" class="mt-1 w-full rounded border border-line px-2 py-1" @disabled($rule)>
                @foreach (['output_vat' => 'Output VAT', 'vds' => 'VDS', 'ait' => 'AIT'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('tax_type', $rule?->tax_type ?? 'output_vat') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($rule)<input type="hidden" name="tax_type" value="{{ $rule->tax_type }}">@endif
        <label class="text-sm">Rule code
            <input name="rule_code" value="{{ old('rule_code', $rule?->rule_code ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
        </label>
        <label class="text-sm">Supply classification <span class="text-muted">(blank is not a wildcard)</span>
            <input name="supply_classification" value="{{ old('supply_classification', $rule?->supply_classification ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Explanatory figure <span class="text-muted">(not applied)</span>
            <input name="documented_rate" value="{{ old('documented_rate', $rule?->documented_rate ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">How to read that figure
            <select name="rate_kind" class="mt-1 w-full rounded border border-line px-2 py-1">
                @foreach (['not_a_rate' => 'Not a rate', 'percentage' => 'Percentage, explanatory only', 'fixed_amount' => 'Fixed amount, explanatory only'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('rate_kind', $rule?->rate_kind ?? 'not_a_rate') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Price treatment
            <select name="price_treatment" class="mt-1 w-full rounded border border-line px-2 py-1">
                @foreach (['unspecified' => 'Unspecified', 'exclusive' => 'Exclusive', 'inclusive' => 'Inclusive'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('price_treatment', $rule?->price_treatment ?? 'unspecified') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Effective from
            <input type="date" name="effective_from" value="{{ old('effective_from', $rule?->dateString('effective_from')) }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
        </label>
        <label class="text-sm">Effective to
            <input type="date" name="effective_to" value="{{ old('effective_to', $rule?->dateString('effective_to')) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm sm:col-span-2">Legal instrument
            <input name="legal_instrument" value="{{ old('legal_instrument', $rule?->legal_instrument ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
        </label>
        <label class="text-sm">Section or rule
            <input name="section_reference" value="{{ old('section_reference', $rule?->section_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Source URL
            <input name="source_url" value="{{ old('source_url', $rule?->source_url ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Publication date
            <input type="date" name="publication_date" value="{{ old('publication_date', $rule?->dateString('publication_date')) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Instrument effective date
            <input type="date" name="instrument_effective_date" value="{{ old('instrument_effective_date', $rule?->dateString('instrument_effective_date')) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm sm:col-span-2">Calculation basis
            <textarea name="calculation_basis" class="mt-1 w-full rounded border border-line px-2 py-1" rows="2">{{ old('calculation_basis', $rule?->calculation_basis ?? '') }}</textarea>
        </label>
        <label class="text-sm sm:col-span-2">Notes
            <textarea name="notes" class="mt-1 w-full rounded border border-line px-2 py-1" rows="2">{{ old('notes', $rule?->notes ?? '') }}</textarea>
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
        </div>
    </form>
</x-layouts.app>
