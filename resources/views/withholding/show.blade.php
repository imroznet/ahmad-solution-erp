<x-layouts.app :title="\App\Tax\TaxType::label($taxType).' record'">
    @php($route = $taxType === 'vds' ? 'vds' : 'ait')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ \App\Tax\TaxType::label($taxType) }} record {{ $record->id }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $record->party_legal_name }} · {{ \App\Tax\TaxDocumentStatus::label($record->status) }}</p>
        </div>
        <div class="flex gap-2">
            @if ($canPdf)<a href="{{ route($route.'.pdf', $record) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>@endif
            @if ($canUpdate)<a href="{{ route($route.'.edit', $record) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit</a>@endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm">This record is not a certificate. {{ $taxType === 'vds' ? 'VDS is not output VAT.' : 'AIT is not VAT or VDS.' }}</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Direction</dt><dd>{{ \App\Tax\TaxDirection::label($record->direction) }}</dd></div>
        <div><dt class="text-muted">Date</dt><dd>{{ $record->dateString('record_date') }}</dd></div>
        <div><dt class="text-muted">Base</dt><dd>{{ $record->basis_amount }} BDT</dd></div>
        <div><dt class="text-muted">Pinned preview</dt><dd>{{ is_array($record->pinned_rule_snapshot) ? ('BDT '.($record->pinned_rule_snapshot['amount'] ?? 'not calculated')) : 'Review required. No amount is stored.' }}</dd></div>
        <div><dt class="text-muted">Certificate reference received</dt><dd>{{ $record->certificate_reference ?: '—' }}</dd></div>
        <div><dt class="text-muted">Treasury reference</dt><dd>{{ $record->treasury_reference ?: '—' }}</dd></div>
    </dl>
    @if ($canUpdate)
        <form method="POST" action="{{ route($route.'.void', $record) }}" class="mt-4 flex flex-wrap items-end gap-2">
            @csrf
            <div><label for="reason" class="block text-sm font-medium">Void reason</label><input id="reason" name="reason" required class="mt-1 rounded border border-line px-3 py-2 text-sm"></div>
            <button class="rounded border border-line px-3 py-2 text-sm" type="submit">Void record</button>
        </form>
    @endif
</x-layouts.app>
