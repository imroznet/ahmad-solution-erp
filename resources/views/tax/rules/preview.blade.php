<x-layouts.app title="Tax rule preview">
    <h1 class="text-xl font-semibold">Which rule matches?</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">The explanation names the approved rule whose inclusive dates cover the day. It does not supply a rate for a document. No match means tax review is required.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="GET" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected($filters['tax_type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="on" class="block text-sm font-medium">Date</label>
            <input id="on" name="on" value="{{ $filters['on'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="rule_key" class="block text-sm font-medium">Rule key</label>
            <input id="rule_key" name="rule_key" value="{{ $filters['rule_key'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="supply_classification" class="block text-sm font-medium">Supply classification</label>
            <input id="supply_classification" name="supply_classification" value="{{ $filters['supply_classification'] }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div><button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Explain</button></div>
    </form>
    @if (is_array($preview))
        <div class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <p>{{ $preview['reason'] }}</p>
            <p class="mt-2 text-muted">Outcome: {{ $preview['outcome'] }}.</p>
        </div>
    @endif
</x-layouts.app>
