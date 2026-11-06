<x-layouts.app :title="$title.' record'">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $record->counterparty_name }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $title }} · {{ $record->directionLabel() }} · {{ $record->status }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('deductions.pdf', ['kind' => $kind, 'record' => $record->id]) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Internal PDF</a>
            @if ($canUpdate)
                <a href="{{ route('deductions.edit', ['kind' => $kind, 'record' => $record->id]) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">This amount is not output VAT and is not added to a Mushak total. Certificate form: unverified. Mushak 6.6 is not produced.</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Date</dt><dd>{{ $record->record_date?->format('d M Y') }}</dd></div>
        <div><dt class="text-muted">Basis</dt><dd>{{ $record->taxable_basis ?? '—' }}</dd></div>
        <div><dt class="text-muted">Amount</dt><dd>{{ $record->deducted_amount ?? '—' }}</dd></div>
        <div><dt class="text-muted">Treasury reference</dt><dd>{{ $record->treasury_reference ?: '—' }}</dd></div>
    </dl>
    <div class="mt-4 flex flex-wrap gap-4">
        @if ($canRecord)
            <form method="POST" action="{{ route('deductions.record', ['kind' => $kind, 'record' => $record->id]) }}">@csrf<button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Record</button></form>
        @endif
        @if ($canVoid)
            <form method="POST" action="{{ route('deductions.void', ['kind' => $kind, 'record' => $record->id]) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <label class="text-sm">Void reason <input name="void_reason" class="mt-1 rounded border border-line px-2 py-1" required></label>
                <button class="ml-2 rounded border border-line px-3 py-2 text-sm">Void</button>
            </form>
        @endif
    </div>
</x-layouts.app>
