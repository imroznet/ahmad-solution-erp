<x-layouts.app title="Tax rules">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Tax rules</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Output VAT, VDS, and AIT are separate. No rate is seeded. An approved rate is not copied onto a Mushak 6.3.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tax-rules.preview') }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Preview a match</a>
            @if ($canManage)
                <a href="{{ route('tax-rules.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New draft rule</a>
            @endif
        </div>
    </div>
    <form method="GET" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-3">
        <div>
            <label for="tax_type" class="block text-sm font-medium">Tax</label>
            <select id="tax_type" name="tax_type" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (\App\Tax\TaxType::options() as $value => $label)
                    <option value="{{ $value }}" @selected($filters['tax_type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium">Status</label>
            <select id="status" name="status" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (\App\Tax\TaxRuleStatus::options() as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end"><button class="rounded border border-line bg-white px-3 py-2 text-sm">Apply</button></div>
    </form>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Rule</th>
                    <th class="px-3 py-2 font-medium">Tax</th>
                    <th class="px-3 py-2 font-medium">Version</th>
                    <th class="px-3 py-2 font-medium">Rate</th>
                    <th class="px-3 py-2 font-medium">Effective</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route('tax-rules.show', $rule) }}" class="font-medium text-brand hover:underline">{{ $rule->rule_key }}</a></td>
                        <td class="px-3 py-2">{{ $rule->typeLabel() }}</td>
                        <td class="px-3 py-2">{{ $rule->version }}</td>
                        <td class="px-3 py-2">{{ $rule->nullableDecimal('rate') ?? 'None' }}</td>
                        <td class="px-3 py-2">{{ $rule->effective_from?->format('Y-m-d') }} – {{ $rule->effective_to?->format('Y-m-d') ?? 'open' }}</td>
                        <td class="px-3 py-2">{{ $rule->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-muted">No tax rules. None will be created automatically.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rules->links() }}</div>
</x-layouts.app>
