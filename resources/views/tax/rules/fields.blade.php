<div class="grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
    @if (!isset($versionOnly))
        <div>
            <label for="code" class="block text-sm font-medium">Code</label>
            <input id="code" name="code" value="{{ old('code') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax type</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                @foreach ($taxTypes as $taxType)
                    <option value="{{ $taxType }}">{{ \App\Tax\TaxType::label($taxType) }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium">Name</label>
        <input id="name" name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="effective_from" class="block text-sm font-medium">Effective from</label>
        <input id="effective_from" name="effective_from" type="date" required value="{{ old('effective_from') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="effective_to" class="block text-sm font-medium">Effective to, inclusive</label>
        <input id="effective_to" name="effective_to" type="date" value="{{ old('effective_to') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="rate_percent" class="block text-sm font-medium">Informational percentage</label>
        <input id="rate_percent" name="rate_percent" value="{{ old('rate_percent') }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        <p class="mt-1 text-xs text-muted">Leave blank. A stored percentage is not applied.</p>
    </div>
    <div>
        <label for="legal_instrument" class="block text-sm font-medium">Legal instrument</label>
        <input id="legal_instrument" name="legal_instrument" required value="{{ old('legal_instrument') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="section_reference" class="block text-sm font-medium">Section or rule</label>
        <input id="section_reference" name="section_reference" value="{{ old('section_reference') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="source_url" class="block text-sm font-medium">Source URL</label>
        <input id="source_url" name="source_url" value="{{ old('source_url') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="publication_date" class="block text-sm font-medium">Publication date</label>
        <input id="publication_date" name="publication_date" type="date" value="{{ old('publication_date') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
    </div>
    <div>
        <label for="legally_effective_on" class="block text-sm font-medium">Legally effective date</label>
        <input id="legally_effective_on" name="legally_effective_on" type="date" value="{{ old('legally_effective_on') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        <p class="mt-1 text-xs text-muted">This can differ from the operational window above.</p>
    </div>
    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-medium">Notes</label>
        <textarea id="notes" name="notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('notes') }}</textarea>
    </div>
</div>
