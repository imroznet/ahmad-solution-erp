<x-layouts.app :title="($amending ?? false) ? 'Amend tax rule' : ($rule ? 'Edit tax rule' : 'Tax rule')">
    <h1 class="text-xl font-semibold">{{ ($amending ?? false) ? 'Amend approved rule' : ($rule ? 'Edit draft rule' : 'New draft rule') }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Record the legal instrument, its publication date, and its effective date separately. The version dates are the dates this record applies. Do not enter a universal rate.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ ($amending ?? false) ? route('tax-rules.amend', $rule) : ($rule ? route('tax-rules.update', $rule) : route('tax-rules.store')) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($rule && ! ($amending ?? false))
            @method('PUT')
        @endif
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax type</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                @foreach (['output_vat' => 'Output VAT', 'vds' => 'VDS', 'ait' => 'AIT'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('tax_type', $rule?->tax_type ?? 'output_vat') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="supply_classification" class="block text-sm font-medium">Supply classification</label>
            <input id="supply_classification" name="supply_classification" required value="{{ old('supply_classification', $rule?->supply_classification ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="rule_code" class="block text-sm font-medium">Rule code</label>
            <input id="rule_code" name="rule_code" value="{{ old('rule_code', $rule?->rule_code ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Leave blank only when the source has no code. A blank code does not cover a coded supply.</p>
        </div>
        <div>
            <label for="rate_basis" class="block text-sm font-medium">Basis</label>
            <select id="rate_basis" name="rate_basis" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                @foreach (['percentage' => 'Percentage', 'fixed_amount' => 'Fixed amount', 'exempt' => 'Exempt', 'zero_rated' => 'Zero-rated', 'non_taxable' => 'Non-taxable'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('rate_basis', $rule?->rate_basis ?? 'percentage') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rate_percent" class="block text-sm font-medium">Rate percent</label>
            <input id="rate_percent" name="rate_percent" value="{{ old('rate_percent', $rule?->nullableDecimal('rate_percent')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="fixed_amount" class="block text-sm font-medium">Fixed amount (BDT)</label>
            <input id="fixed_amount" name="fixed_amount" value="{{ old('fixed_amount', $rule?->nullableDecimal('fixed_amount')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="calculation_basis" class="block text-sm font-medium">Calculation</label>
            <select id="calculation_basis" name="calculation_basis" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                @foreach (['exclusive' => 'Tax exclusive', 'inclusive' => 'Tax inclusive', 'not_applicable' => 'Not applicable'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('calculation_basis', $rule?->calculation_basis ?? 'exclusive') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rounding_rule" class="block text-sm font-medium">Rounding</label>
            <select id="rounding_rule" name="rounding_rule" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="half_away_from_zero" @selected(old('rounding_rule', $rule?->rounding_rule ?? 'half_away_from_zero') === 'half_away_from_zero')>Half away from zero (application rule, not an NBR rule)</option>
                <option value="not_applicable" @selected(old('rounding_rule', $rule?->rounding_rule ?? '') === 'not_applicable')>Not applicable</option>
            </select>
        </div>
        <div>
            <label for="threshold_amount" class="block text-sm font-medium">Threshold amount</label>
            <input id="threshold_amount" name="threshold_amount" value="{{ old('threshold_amount', $rule?->nullableDecimal('threshold_amount')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="threshold_treatment" class="block text-sm font-medium">Threshold treatment</label>
            <select id="threshold_treatment" name="threshold_treatment" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <option value="not_applicable" @selected(old('threshold_treatment', $rule?->threshold_treatment ?? 'not_applicable') === 'not_applicable')>No threshold</option>
                <option value="below_is_zero_full_base" @selected(old('threshold_treatment', $rule?->threshold_treatment ?? '') === 'below_is_zero_full_base')>Below threshold is zero; at or above, tax the full base</option>
            </select>
        </div>
        <div>
            <label for="effective_from" class="block text-sm font-medium">Applies from (inclusive)</label>
            <input id="effective_from" name="effective_from" type="date" required value="{{ old('effective_from', $rule?->dateString('effective_from')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="effective_to" class="block text-sm font-medium">Applies through (inclusive)</label>
            <input id="effective_to" name="effective_to" type="date" value="{{ old('effective_to', $rule?->dateString('effective_to')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="legal_instrument" class="block text-sm font-medium">Legal instrument</label>
            <input id="legal_instrument" name="legal_instrument" required value="{{ old('legal_instrument', $rule?->legal_instrument ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="section_reference" class="block text-sm font-medium">Section, rule, or paragraph</label>
            <input id="section_reference" name="section_reference" required value="{{ old('section_reference', $rule?->section_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="source_url" class="block text-sm font-medium">Source URL</label>
            <input id="source_url" name="source_url" type="url" required value="{{ old('source_url', $rule?->source_url ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="publication_date" class="block text-sm font-medium">Publication date of the instrument</label>
            <input id="publication_date" name="publication_date" type="date" value="{{ old('publication_date', $rule?->dateString('publication_date')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="legal_effective_date" class="block text-sm font-medium">Legal effective date of the instrument</label>
            <input id="legal_effective_date" name="legal_effective_date" type="date" value="{{ old('legal_effective_date', $rule?->dateString('legal_effective_date')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="required_supporting_data" class="block text-sm font-medium">Supporting data the source requires</label>
            <textarea id="required_supporting_data" name="required_supporting_data" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('required_supporting_data', $rule?->required_supporting_data ?? '') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label for="exemption_note" class="block text-sm font-medium">Exemption or threshold note</label>
            <textarea id="exemption_note" name="exemption_note" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('exemption_note', $rule?->exemption_note ?? '') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $rule?->internal_notes ?? '') }}</textarea>
            <p class="mt-1 text-xs text-muted">Internal notes stay off customer-facing output.</p>
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
        </div>
    </form>
</x-layouts.app>
