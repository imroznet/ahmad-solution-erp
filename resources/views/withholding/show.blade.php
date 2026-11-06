<x-layouts.app :title="strtoupper($kind).' record'">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ strtoupper($kind) }} · {{ $record->party_name }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $record->record_date?->toDateString() }} · Certificate status: form not verified, not issued</p>
        </div>
        @if ($canRecord)
            <a href="{{ route($kind.'.edit', $record) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Edit</a>
        @endif
    </div>
    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">This amount is not output VAT. It is not included in a Mushak 6.3 total.</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Direction</dt><dd>{{ \App\Tax\WithholdingDirection::label($record->direction) }}</dd></div>
        <div><dt class="text-muted">Amount</dt><dd>{{ $record->amount }}</dd></div>
        <div><dt class="text-muted">Basis</dt><dd>{{ $record->taxable_basis ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">BIN</dt><dd>{{ $record->party_bin ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Reference</dt><dd>{{ $record->certificate_reference ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Payment reference</dt><dd>{{ $record->payment_reference ?: 'Not recorded' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Supply</dt><dd>{{ $record->supply_description }}</dd></div>
        @if ($record->internal_notes)
            <div class="sm:col-span-2"><dt class="text-muted">Internal</dt><dd>{{ $record->internal_notes }}</dd></div>
        @endif
    </dl>
</x-layouts.app>
