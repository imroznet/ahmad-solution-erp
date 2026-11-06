<x-layouts.app :title="$rule->rule_key">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $rule->rule_key }} · version {{ $rule->version }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $rule->typeLabel() }} · {{ $rule->statusLabel() }} · {{ $rule->name }}</p>
        </div>
        @if ($canManage && $rule->stringAttribute('status') === 'draft')
            <a href="{{ route('tax-rules.edit', $rule) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
        @endif
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Rate</dt><dd>{{ $rule->nullableDecimal('rate') ?? 'None recorded' }} {{ $rule->rate_kind }}</dd></div>
        <div><dt class="text-muted">Applied to documents</dt><dd>No. A Mushak 6.3 keeps the figures a person types.</dd></div>
        <div><dt class="text-muted">Effective</dt><dd>{{ $rule->effective_from?->format('Y-m-d') }} to {{ $rule->effective_to?->format('Y-m-d') ?? 'open' }}. Both ends are inclusive.</dd></div>
        <div><dt class="text-muted">Publication date</dt><dd>{{ $rule->publication_date?->format('Y-m-d') ?? 'Not recorded' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Legal instrument</dt><dd>{{ $rule->legal_instrument }} {{ $rule->section_reference }}</dd></div>
        @if ($rule->source_url)
            <div class="sm:col-span-2"><dt class="text-muted">Source</dt><dd>{{ $rule->source_url }}</dd></div>
        @endif
        @if ($rule->notes)
            <div class="sm:col-span-2"><dt class="text-muted">Notes</dt><dd>{{ $rule->notes }}</dd></div>
        @endif
    </dl>

    @if ($canApprove && $rule->stringAttribute('status') === 'draft')
        <form method="POST" action="{{ route('tax-rules.approve', $rule) }}" class="mt-4">
            @csrf
            <button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Approve rule</button>
        </form>
    @endif

    @if ($canManage && $rule->stringAttribute('status') === 'approved')
        <form method="POST" action="{{ route('tax-rules.amend', $rule) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-3">
            @csrf
            <div class="sm:col-span-3 text-sm">Open the next version as a draft. The approved rate on this version stays as it is.</div>
            <div>
                <label for="effective_from" class="block text-sm font-medium">New start</label>
                <input id="effective_from" name="effective_from" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="effective_to" class="block text-sm font-medium">New end</label>
                <input id="effective_to" name="effective_to" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="flex items-end"><button class="rounded border border-line bg-white px-3 py-2 text-sm">Open amendment</button></div>
        </form>
    @endif

    @if ($canApprove && $rule->stringAttribute('status') === 'approved')
        <form method="POST" action="{{ route('tax-rules.retire', $rule) }}" class="mt-4 space-y-3 rounded border border-line bg-white p-4">
            @csrf
            <label for="retire_reason" class="block text-sm font-medium">Retire this version</label>
            <textarea id="retire_reason" name="retire_reason" rows="2" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button class="rounded border border-line bg-white px-3 py-2 text-sm">Retire</button>
        </form>
    @endif

    <form method="GET" action="{{ route('tax-rules.show', $rule) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-4">
        <div class="sm:col-span-4 text-sm font-medium">Which rule would be explained on a date?</div>
        <input type="hidden" name="tax_type" value="{{ $rule->tax_type }}">
        <input type="hidden" name="rule_key" value="{{ $rule->rule_key }}">
        <div>
            <label for="on" class="block text-sm font-medium">Date</label>
            <input id="on" name="on" value="{{ request('on') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="flex items-end"><button class="rounded border border-line bg-white px-3 py-2 text-sm">Explain</button></div>
        @if (is_array($preview))
            <div class="sm:col-span-4 text-sm">
                <p>{{ $preview['reason'] }}</p>
                <p class="mt-1 text-muted">Outcome: {{ $preview['outcome'] }}. No rate was applied.</p>
            </div>
        @endif
    </form>
</x-layouts.app>
