<x-layouts.app :title="$title">
    <h1 class="text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $record->stringAttribute('status') === 'voided' ? 'Voided' : 'Recorded' }} · {{ $record->directionLabel() }}</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">{{ $separation }}</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Date</dt><dd>{{ $record->record_date?->format('d M Y') }}</dd></div>
        <div><dt class="text-muted">Party</dt><dd>{{ $record->counterparty_name }}</dd></div>
        <div><dt class="text-muted">Basis</dt><dd>{{ $record->basisMoney()->format() }}</dd></div>
        <div><dt class="text-muted">{{ $kindLabel }} amount</dt><dd>{{ $record->taxMoney()->format() }}</dd></div>
        <div><dt class="text-muted">Certificate</dt><dd>{{ $record->certificate_reference ?: '—' }}</dd></div>
        <div><dt class="text-muted">Payment reference</dt><dd>{{ $record->payment_reference ?: '—' }}</dd></div>
    </dl>
    @if ($canVoid)
        <form method="POST" action="{{ route($voidRoute, $record) }}" class="mt-4 max-w-xl space-y-2">
            @csrf
            <label for="void_reason" class="block text-sm font-medium">Void reason</label>
            <textarea id="void_reason" name="void_reason" rows="2" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button class="rounded border border-line bg-white px-3 py-2 text-sm">Void record</button>
        </form>
    @endif
</x-layouts.app>
