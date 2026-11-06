<x-layouts.app :title="$version->rule?->rule_code">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $version->rule?->rule_code }} · version {{ $version->version_number }}</h1>
            <p class="mt-1 text-sm text-muted">{{ \App\Tax\TaxType::label($version->rule?->tax_type ?? '') }} · {{ $version->statusLabel() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canUpdate)<a href="{{ route('tax-rules.edit', $version) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>@endif
            @if ($canAmend)<a href="{{ route('tax-rules.amend', $version) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Amend</a>@endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">This version does not set the VAT percentage on a Mushak 6.3. A percentage stored here is reference data only.</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Classification</dt><dd>{{ $version->rule?->supply_classification !== '' ? $version->rule?->supply_classification : 'No classification' }}</dd></div>
        <div><dt class="text-muted">Stored amount</dt><dd>{{ $version->rate_kind }} @if ($version->rate_value){{ $version->rate_value }}%@endif @if ($version->fixed_amount){{ $version->fixed_amount }}@endif</dd></div>
        <div><dt class="text-muted">Instrument</dt><dd>{{ $version->legal_instrument }}</dd></div>
        <div><dt class="text-muted">Section</dt><dd>{{ $version->section_reference }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Source</dt><dd><a class="text-brand" href="{{ $version->source_url }}">{{ $version->source_url }}</a></dd></div>
        <div><dt class="text-muted">Publication date</dt><dd>{{ $version->publication_date?->toDateString() ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Effective</dt><dd>{{ $version->effective_from?->toDateString() }} @if ($version->effective_to) through {{ $version->effective_to->toDateString() }} @else with no end date @endif</dd></div>
    </dl>
    @if ($canApprove)
        <form method="POST" action="{{ route('tax-rules.approve', $version) }}" class="mt-4">
            @csrf
            <button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Approve</button>
        </form>
    @endif
    @if ($canRetire)
        <form method="POST" action="{{ route('tax-rules.retire', $version) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
            @csrf
            <div>
                <label for="effective_to" class="block text-sm font-medium">Close on</label>
                <input id="effective_to" name="effective_to" type="date" required value="{{ old('effective_to', $version->effective_to?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="retirement_reason" class="block text-sm font-medium">Retirement reason</label>
                <input id="retirement_reason" name="retirement_reason" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div><button class="rounded border border-line px-3 py-2 text-sm">Retire</button></div>
        </form>
    @endif
</x-layouts.app>
