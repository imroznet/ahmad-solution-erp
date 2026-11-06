<x-layouts.app title="Tax rule">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ \App\Tax\TaxType::label($rule->tax_type) }} · version {{ $rule->version_number }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $rule->supply_classification }} · {{ \App\Tax\TaxRuleStatus::label($rule->approval_status) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canEdit)
                <a href="{{ route('tax-rules.edit', $rule) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
            @if ($canAmend)
                <a href="{{ route('tax-rules.amend-form', $rule) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Amend</a>
            @endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Rule code</dt><dd>{{ $rule->rule_code !== '' ? $rule->rule_code : 'None' }}</dd></div>
        <div><dt class="text-muted">Basis</dt><dd>{{ \App\Tax\RateBasis::label($rule->rate_basis) }}</dd></div>
        <div><dt class="text-muted">Rate</dt><dd>{{ $rule->nullableDecimal('rate_percent') ?? '—' }}</dd></div>
        <div><dt class="text-muted">Fixed amount</dt><dd>{{ $rule->nullableDecimal('fixed_amount') ?? '—' }}</dd></div>
        <div><dt class="text-muted">Applies</dt><dd>{{ $rule->dateString('effective_from') }} through {{ $rule->dateString('effective_to') ?? 'no end date' }}, both ends inclusive</dd></div>
        <div><dt class="text-muted">Instrument</dt><dd>{{ $rule->legal_instrument }} · {{ $rule->section_reference }}</dd></div>
        <div><dt class="text-muted">Publication date</dt><dd>{{ $rule->dateString('publication_date') ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Legal effective date</dt><dd>{{ $rule->dateString('legal_effective_date') ?? 'Not recorded' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Source</dt><dd><a class="break-all text-brand underline" href="{{ $rule->source_url }}">{{ $rule->source_url }}</a></dd></div>
    </dl>
    <form method="GET" action="{{ route('tax-rules.show', $rule) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-3">
        <div>
            <label for="on_date" class="block text-sm font-medium">Explain a date</label>
            <input id="on_date" name="on_date" type="date" value="{{ request('on_date', $rule->dateString('effective_from')) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="base" class="block text-sm font-medium">Base amount</label>
            <input id="base" name="base" value="{{ request('base') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="flex items-end"><button class="rounded border border-line px-3 py-2 text-sm" type="submit">Show the selected rule</button></div>
    </form>
    @if ($preview)
        <div class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Selection</h2>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($preview['selection']->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
            @if ($preview['assessment'])
                <p class="mt-3">Outcome: {{ $preview['assessment']->outcome }}. Amount: {{ $preview['assessment']->amount ?? 'not calculated' }}.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($preview['assessment']->reasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
    <div class="mt-4 flex flex-wrap gap-4">
        @if ($canApprove)
            <form method="POST" action="{{ route('tax-rules.approve', $rule) }}">@csrf<button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white" type="submit">Approve</button></form>
        @endif
        @if ($canRetire)
            <form method="POST" action="{{ route('tax-rules.retire', $rule) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label for="reason" class="block text-sm font-medium">Retirement reason</label>
                    <input id="reason" name="reason" required class="mt-1 rounded border border-line px-3 py-2 text-sm">
                </div>
                <button class="rounded border border-line px-3 py-2 text-sm" type="submit">Retire</button>
            </form>
        @endif
    </div>
    <h2 class="mt-6 text-sm font-medium">History</h2>
    <ul class="mt-2 space-y-1 text-sm">
        @foreach ($rule->events as $event)
            <li>{{ $event->created_at?->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ $event->event }} · {{ $event->actor?->name ?? 'System' }}</li>
        @endforeach
    </ul>
</x-layouts.app>
