@php
    $title = $kind === 'vds' ? 'VDS record' : 'AIT record';
    $notice = $kind === 'vds'
        ? 'This is not output VAT. Mushak 6.6 is not generated from this record.'
        : 'This is not VAT and it is not VDS. No statutory withholding certificate is generated.';
@endphp
<x-layouts.app :title="$title">
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $record->directionLabel() }} · {{ $record->getAttribute('status') }} · {{ $record->reviewLabel() }}</p>
    <p class="mt-3 rounded border border-line bg-white px-3 py-2 text-sm">{{ $notice }}</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Counterparty</dt><dd>{{ $record->counterparty_name }}</dd></div>
        <div><dt class="text-muted">Source</dt><dd>{{ $record->counterparty_source === 'manual' ? 'Typed for this record. No party was created.' : 'Existing party' }}</dd></div>
        <div><dt class="text-muted">Deducted amount</dt><dd>{{ $record->nullableDecimal('deducted_amount') }}</dd></div>
        <div><dt class="text-muted">Basis</dt><dd>{{ $record->nullableDecimal('basis_amount') ?? 'Not entered' }}</dd></div>
        <div><dt class="text-muted">Date</dt><dd>{{ $record->record_date?->format('Y-m-d') }}</dd></div>
        <div><dt class="text-muted">Certificate</dt><dd>{{ $record->certificate_number ?: 'Not entered' }}</dd></div>
        <div><dt class="text-muted">Treasury or payment reference</dt><dd>{{ $record->treasury_reference ?: 'Not entered' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Underlying document</dt><dd>{{ $record->underlying_reference }}</dd></div>
        @if ($record->notes)
            <div class="sm:col-span-2"><dt class="text-muted">Notes</dt><dd>{{ $record->notes }}</dd></div>
        @endif
    </dl>
    @if ($canVoid)
        <form method="POST" action="{{ route($kind.'.void', $record) }}" class="mt-4 space-y-3 rounded border border-line bg-white p-4">
            @csrf
            <label for="void_reason" class="block text-sm font-medium">Void reason</label>
            <textarea id="void_reason" name="void_reason" rows="2" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button class="rounded border border-line bg-white px-3 py-2 text-sm">Void record</button>
        </form>
    @endif
</x-layouts.app>
