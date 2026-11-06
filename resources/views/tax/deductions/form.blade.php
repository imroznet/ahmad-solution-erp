<x-layouts.app :title="$record ? 'Edit '.$title : 'New '.$title">
    <h1 class="text-xl font-semibold">{{ $record ? 'Edit' : 'New' }} {{ $title }} record</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Enter the basis and the amount. The system does not calculate either from a rate. Recording this does not create an NBR certificate.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $record ? route('deductions.update', ['kind' => $kind, 'record' => $record->id]) : route('deductions.store', ['kind' => $kind]) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        @csrf
        @if ($record) @method('PUT') @endif
        <label class="text-sm">Direction
            <select name="direction" class="mt-1 w-full rounded border border-line px-2 py-1">
                <option value="withheld_from_us" @selected(old('direction', $record?->direction ?? '') === 'withheld_from_us')>Withheld from Ahmad Solution Ltd.</option>
                <option value="deducted_by_us" @selected(old('direction', $record?->direction ?? '') === 'deducted_by_us')>Deducted by Ahmad Solution Ltd.</option>
            </select>
        </label>
        <label class="text-sm">Date
            <input type="date" name="record_date" value="{{ old('record_date', $record?->record_date?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
        </label>
        <label class="text-sm">Other party
            <input name="counterparty_name" value="{{ old('counterparty_name', $record?->counterparty_name ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
        </label>
        <label class="text-sm">BIN
            <input name="counterparty_bin" value="{{ old('counterparty_bin', $record?->counterparty_bin ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">TIN
            <input name="counterparty_tin" value="{{ old('counterparty_tin', $record?->counterparty_tin ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Taxable basis
            <input name="taxable_basis" value="{{ old('taxable_basis', $record?->taxable_basis ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Deducted amount
            <input name="deducted_amount" value="{{ old('deducted_amount', $record?->deducted_amount ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Source reference
            <input name="source_reference" value="{{ old('source_reference', $record?->source_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Certificate reference
            <input name="certificate_reference" value="{{ old('certificate_reference', $record?->certificate_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm">Treasury or challan reference
            <input name="treasury_reference" value="{{ old('treasury_reference', $record?->treasury_reference ?? '') }}" class="mt-1 w-full rounded border border-line px-2 py-1">
        </label>
        <label class="text-sm sm:col-span-2">Internal note <span class="text-muted">(kept off the record PDF)</span>
            <textarea name="internal_notes" class="mt-1 w-full rounded border border-line px-2 py-1" rows="2">{{ old('internal_notes', $record?->internal_notes ?? '') }}</textarea>
        </label>
        <div class="sm:col-span-2"><button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button></div>
    </form>
</x-layouts.app>
