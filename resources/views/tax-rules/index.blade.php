<x-layouts.app title="Tax rules">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Tax rules</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Output VAT, VDS, and AIT are separate rules. A rule applies only for its classification, rule code, and inclusive dates. No rate is installed with the application.</p>
        </div>
        @if ($currentUser?->hasPermission('tax_rules.manage'))
            <a href="{{ route('tax-rules.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New draft rule</a>
        @endif
    </div>
    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
        <p class="font-medium">Statutory issue is blocked</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach ($blockers as $blocker)
                <li>{{ $blocker }}</li>
            @endforeach
        </ul>
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2">Rule</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Classification</th>
                    <th class="px-3 py-2">Dates</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="text-brand underline" href="{{ route('tax-rules.show', $rule) }}">Version {{ $rule->version_number }}</a></td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxType::label($rule->tax_type) }}</td>
                        <td class="px-3 py-2">{{ $rule->supply_classification }}</td>
                        <td class="px-3 py-2">{{ $rule->dateString('effective_from') }} – {{ $rule->dateString('effective_to') ?? 'open' }}</td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxRuleStatus::label($rule->approval_status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-muted">No tax rules. A transaction with no approved rule stays in review and is not taxed by default.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
