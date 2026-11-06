<x-layouts.app :title="$amending ? 'Amend tax rule' : ($version ? 'Edit tax rule' : 'New tax rule')">
    <h1 class="text-xl font-semibold">{{ $amending ? 'Amend tax rule' : ($version ? 'Edit draft tax rule' : 'New tax rule') }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Leave the stored amount as "No stored amount" unless you are recording a source-backed figure for reference. Mushak 6.3 will not use it.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $amending ? route('tax-rules.amend.store', $version) : ($version ? route('tax-rules.update', $version) : route('tax-rules.store')) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($version && ! $amending)
            @method('PUT')
        @endif
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax type</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm" @disabled($version)>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('tax_type', $version?->rule?->tax_type) === $type)>{{ \App\Tax\TaxType::label($type) }}</option>
                @endforeach
            </select>
            @if ($version)<input type="hidden" name="tax_type" value="{{ $version->rule?->tax_type }}">@endif
        </div>
        <div>
            <label for="rule_code" class="block text-sm font-medium">Rule code</label>
            <input id="rule_code" name="rule_code" value="{{ old('rule_code', $version?->rule?->rule_code) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm" @readonly($version)>
        </div>
        <div class="sm:col-span-2">
            <label for="supply_classification" class="block text-sm font-medium">Supply classification</label>
            <input id="supply_classification" name="supply_classification" value="{{ old('supply_classification', $version?->rule?->supply_classification) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm" @readonly($version)>
            <p class="mt-1 text-xs text-muted">Blank is its own scope. It does not match every supply.</p>
        </div>
        <div>
            <label for="rate_kind" class="block text-sm font-medium">Stored amount</label>
            <select id="rate_kind" name="rate_kind" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                @foreach (['none' => 'No stored amount', 'percentage' => 'Percentage for reference only', 'fixed' => 'Fixed amount for reference only'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('rate_kind', $version?->rate_kind ?? 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rate_value" class="block text-sm font-medium">Percentage</label>
            <input id="rate_value" name="rate_value" value="{{ old('rate_value', $version?->rate_value) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="fixed_amount" class="block text-sm font-medium">Fixed amount</label>
            <input id="fixed_amount" name="fixed_amount" value="{{ old('fixed_amount', $version?->fixed_amount) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="legal_instrument" class="block text-sm font-medium">Legal instrument</label>
            <input id="legal_instrument" name="legal_instrument" required value="{{ old('legal_instrument', $version?->legal_instrument) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="section_reference" class="block text-sm font-medium">Section, rule, or paragraph</label>
            <input id="section_reference" name="section_reference" required value="{{ old('section_reference', $version?->section_reference) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label for="source_url" class="block text-sm font-medium">Source URL</label>
            <input id="source_url" name="source_url" required value="{{ old('source_url', $version?->source_url) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="publication_date" class="block text-sm font-medium">Publication date</label>
            <input id="publication_date" name="publication_date" type="date" value="{{ old('publication_date', $version?->publication_date?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="effective_from" class="block text-sm font-medium">Effective from</label>
            <input id="effective_from" name="effective_from" type="date" required value="{{ old('effective_from', $amending ? '' : $version?->effective_from?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="effective_to" class="block text-sm font-medium">Effective to</label>
            <input id="effective_to" name="effective_to" type="date" value="{{ old('effective_to', $amending ? '' : $version?->effective_to?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Both the start and the end date are included. The next version starts the following day.</p>
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('notes', $version?->notes) }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
        </div>
    </form>
</x-layouts.app>
