<x-layouts.app :title="$rule->rule_code">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $rule->rule_code }} · version {{ $rule->version }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $rule->typeLabel() }} · {{ $rule->approval_status }} · {{ $rule->activity_status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($rule->approval_status === 'draft' && $currentUser?->hasPermission('tax_rules.update'))
                <a href="{{ route('tax.rules.edit', $rule) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">This register entry does not set the VAT percentage on a Mushak 6.3. The percentage is typed on the document.</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Classification</dt><dd>{{ $rule->supply_classification !== '' ? $rule->supply_classification : 'Unspecified (not a wildcard)' }}</dd></div>
        <div><dt class="text-muted">Window</dt><dd>{{ $rule->dateString('effective_from') }} – {{ $rule->dateString('effective_to') ?? 'open' }}</dd></div>
        <div><dt class="text-muted">Legal instrument</dt><dd>{{ $rule->legal_instrument }}</dd></div>
        <div><dt class="text-muted">Section</dt><dd>{{ $rule->section_reference }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Explanatory figure</dt><dd>{{ $rule->documented_rate ?? 'None' }} · {{ $rule->rate_kind }} · not applied to documents</dd></div>
        @if ($rule->source_url)
            <div class="sm:col-span-2"><dt class="text-muted">Source</dt><dd>{{ $rule->source_url }}</dd></div>
        @endif
    </dl>
    <form method="GET" action="{{ route('tax.rules.show', $rule) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-3">
        <label class="text-sm">Date
            <input type="date" name="on_date" value="{{ request('on_date', now()->timezone('Asia/Dhaka')->toDateString()) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Classification
            <input name="classification" value="{{ request('classification', $rule->supply_classification) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <div class="self-end"><button class="rounded border border-line px-3 py-2 text-sm">Explain match</button></div>
        @if ($explanation)
            <p class="sm:col-span-3 text-sm">{{ $explanation['message'] }}</p>
        @endif
    </form>
    <div class="mt-4 flex flex-wrap gap-4">
        @if ($rule->approval_status === 'draft' && $currentUser?->hasPermission('tax_rules.approve'))
            <form method="POST" action="{{ route('tax.rules.approve', $rule) }}">@csrf<button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Approve</button></form>
        @endif
        @if ($rule->approval_status === 'approved' && $rule->activity_status === 'active' && $currentUser?->hasPermission('tax_rules.retire'))
            <form method="POST" action="{{ route('tax.rules.retire', $rule) }}">@csrf<button class="rounded border border-line bg-white px-3 py-2 text-sm">Retire</button></form>
        @endif
        @if ($rule->approval_status === 'approved' && $currentUser?->hasPermission('tax_rules.update'))
            <form method="POST" action="{{ route('tax.rules.amend', $rule) }}">
                @csrf
                <input type="hidden" name="rule_code" value="{{ $rule->rule_code }}">
                <input type="hidden" name="supply_classification" value="{{ $rule->supply_classification }}">
                <input type="hidden" name="documented_rate" value="{{ $rule->documented_rate }}">
                <input type="hidden" name="rate_kind" value="{{ $rule->rate_kind }}">
                <input type="hidden" name="price_treatment" value="{{ $rule->price_treatment }}">
                <input type="hidden" name="effective_from" value="{{ $rule->dateString('effective_from') }}">
                <input type="hidden" name="effective_to" value="{{ $rule->dateString('effective_to') }}">
                <input type="hidden" name="legal_instrument" value="{{ $rule->legal_instrument }}">
                <input type="hidden" name="section_reference" value="{{ $rule->section_reference }}">
                <input type="hidden" name="source_url" value="{{ $rule->source_url }}">
                <input type="hidden" name="publication_date" value="{{ $rule->dateString('publication_date') }}">
                <input type="hidden" name="instrument_effective_date" value="{{ $rule->dateString('instrument_effective_date') }}">
                <input type="hidden" name="calculation_basis" value="{{ $rule->calculation_basis }}">
                <input type="hidden" name="notes" value="{{ $rule->notes }}">
                <button class="rounded border border-line bg-white px-3 py-2 text-sm">Amend as a new version</button>
            </form>
        @endif
    </div>
</x-layouts.app>
