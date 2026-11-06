<x-layouts.app title="Tax rules">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Tax rules</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Output VAT, VDS, and AIT are separate types. A matching rule explains coverage. It does not apply a percentage to a document. No statutory rate is seeded.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('tax-rules.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New rule</a>
        @endif
    </div>
    <form method="GET" class="mt-4 grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-3">
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax type</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                @foreach ($taxTypes as $taxType)
                    <option value="{{ $taxType }}" @selected($type === $taxType)>{{ \App\Tax\TaxType::label($taxType) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="on" class="block text-sm font-medium">On date</label>
            <input id="on" name="on" type="date" value="{{ $on }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="flex items-end"><button class="rounded border border-line bg-white px-3 py-2 text-sm">Preview match</button></div>
    </form>
    @if ($explanationError)
        <p class="mt-3 text-sm text-red-800">{{ $explanationError }}</p>
    @endif
    @if (is_array($explanation))
        <p class="mt-3 rounded border border-line bg-white px-3 py-2 text-sm">{{ $explanation['message'] }}</p>
    @endif
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2 font-medium">Code</th><th class="px-3 py-2 font-medium">Type</th><th class="px-3 py-2 font-medium">Name</th><th class="px-3 py-2 font-medium">Status</th></tr></thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="font-medium text-brand" href="{{ route('tax-rules.show', $rule) }}">{{ $rule->code }}</a></td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxType::label($rule->tax_type) }}</td>
                        <td class="px-3 py-2">{{ $rule->name }}</td>
                        <td class="px-3 py-2">{{ $rule->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-muted">No tax rules. None are created automatically.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rules->links() }}</div>
</x-layouts.app>
