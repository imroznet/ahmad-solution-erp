@php
    $field = function (string $key, mixed $fallback = null) use ($rule): mixed {
        $old = old($key);
        if ($old !== null) {
            return $old;
        }
        if (! $rule) {
            return $fallback;
        }
        $value = $rule->getAttribute($key);
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return $value;
    };
@endphp
<x-layouts.app :title="$rule ? 'Edit tax rule' : 'New tax rule'">
    <h1 class="text-xl font-semibold">{{ $rule ? 'Edit draft tax rule' : 'New draft tax rule' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Leave the rate blank unless you are recording one on purpose. Saving this draft does not apply it to a Mushak 6.3, a VDS record, or an AIT record.</p>
    @if ($rule && $rule->supersedes_rule_id)
        <p class="mt-2 text-sm">This draft amends version {{ $rule->supersededRule?->version }}. A copied rate is still not applied to a document. Clear it if this version should not keep it.</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $rule ? route('tax-rules.update', $rule) : route('tax-rules.store') }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($rule) @method('PUT') @endif
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">Choose</option>
                @foreach (\App\Tax\TaxType::options() as $value => $label)
                    <option value="{{ $value }}" @selected($field('tax_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rule_key" class="block text-sm font-medium">Rule key</label>
            <input id="rule_key" name="rule_key" value="{{ $field('rule_key') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="name" class="block text-sm font-medium">Name</label>
            <input id="name" name="name" value="{{ $field('name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="supply_classification" class="block text-sm font-medium">Supply classification</label>
            <input id="supply_classification" name="supply_classification" value="{{ $field('supply_classification') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="rate_kind" class="block text-sm font-medium">Rate kind</label>
            <select id="rate_kind" name="rate_kind" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">No rate</option>
                @foreach (['percentage' => 'Percentage', 'fixed_amount' => 'Fixed amount', 'none' => 'None'] as $value => $label)
                    <option value="{{ $value }}" @selected($field('rate_kind') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rate" class="block text-sm font-medium">Rate</label>
            <input id="rate" name="rate" value="{{ $field('rate') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Blank until you type one. This is not a statutory default.</p>
        </div>
        <div>
            <label for="price_treatment" class="block text-sm font-medium">Price treatment</label>
            <select id="price_treatment" name="price_treatment" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">Unspecified</option>
                @foreach (['exclusive' => 'Exclusive', 'inclusive' => 'Inclusive', 'unspecified' => 'Unspecified'] as $value => $label)
                    <option value="{{ $value }}" @selected($field('price_treatment') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="calculation_basis" class="block text-sm font-medium">Calculation basis</label>
            <input id="calculation_basis" name="calculation_basis" value="{{ $field('calculation_basis') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="rounding_rule" class="block text-sm font-medium">Rounding note</label>
            <input id="rounding_rule" name="rounding_rule" value="{{ $field('rounding_rule') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="threshold_amount" class="block text-sm font-medium">Threshold</label>
            <input id="threshold_amount" name="threshold_amount" value="{{ $field('threshold_amount') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="effective_from" class="block text-sm font-medium">Effective from</label>
            <input id="effective_from" name="effective_from" value="{{ $field('effective_from') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Inclusive.</p>
        </div>
        <div>
            <label for="effective_to" class="block text-sm font-medium">Effective to</label>
            <input id="effective_to" name="effective_to" value="{{ $field('effective_to') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Inclusive. Leave blank for an open end.</p>
        </div>
        <div>
            <label for="publication_date" class="block text-sm font-medium">Publication date</label>
            <input id="publication_date" name="publication_date" value="{{ $field('publication_date') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Not the same as the effective date.</p>
        </div>
        <div class="sm:col-span-2">
            <label for="legal_instrument" class="block text-sm font-medium">Legal instrument</label>
            <input id="legal_instrument" name="legal_instrument" value="{{ $field('legal_instrument') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="section_reference" class="block text-sm font-medium">Section or rule</label>
            <input id="section_reference" name="section_reference" value="{{ $field('section_reference') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="source_url" class="block text-sm font-medium">Source URL</label>
            <input id="source_url" name="source_url" value="{{ $field('source_url') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="exemption_note" class="block text-sm font-medium">Exemption note</label>
            <textarea id="exemption_note" name="exemption_note" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ $field('exemption_note') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label for="required_supporting_data" class="block text-sm font-medium">Supporting data</label>
            <textarea id="required_supporting_data" name="required_supporting_data" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ $field('required_supporting_data') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ $field('notes') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
        </div>
    </form>
</x-layouts.app>
