<x-layouts.app :title="$rule->code">
    <h1 class="text-xl font-semibold">{{ $rule->code }}</h1>
    <p class="mt-1 text-sm text-muted">{{ \App\Tax\TaxType::label($rule->tax_type) }} · {{ $rule->name }} · {{ $rule->status }}</p>
    <p class="mt-3 max-w-3xl text-sm text-muted">Approved windows cannot overlap. The end date is inclusive. A later amendment is a new version. Approving a version does not put its percentage on a Mushak line.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2 font-medium">Version</th><th class="px-3 py-2 font-medium">Window</th><th class="px-3 py-2 font-medium">Status</th><th class="px-3 py-2 font-medium">Instrument</th><th class="px-3 py-2 font-medium">Recorded %</th><th class="px-3 py-2 font-medium"></th></tr></thead>
            <tbody>
                @foreach ($rule->versions as $version)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $version->version_number }}</td>
                        <td class="px-3 py-2">{{ $version->effective_from?->toDateString() }} – {{ $version->effective_to?->toDateString() ?? 'open' }}</td>
                        <td class="px-3 py-2">{{ $version->approval_status }}</td>
                        <td class="px-3 py-2">{{ $version->legal_instrument }}</td>
                        <td class="px-3 py-2">{{ $version->nullableDecimal('rate_percent') ?? 'None' }}</td>
                        <td class="px-3 py-2">
                            @if ($canApprove && $version->approval_status === 'draft')
                                <form method="POST" action="{{ route('tax-rules.versions.approve', [$rule, $version]) }}">@csrf<button class="text-brand">Approve</button></form>
                            @endif
                            @if ($canRetire && $version->approval_status === 'approved')
                                <form method="POST" action="{{ route('tax-rules.versions.retire', [$rule, $version]) }}">@csrf<button class="text-brand">Retire version</button></form>
                            @endif
                            @if ($canUpdate && $version->approval_status === 'approved' && $version->effective_to === null)
                                <form method="POST" action="{{ route('tax-rules.versions.close', [$rule, $version]) }}" class="mt-1 flex gap-2">
                                    @csrf
                                    <input name="effective_to" type="date" required class="rounded border border-line px-2 py-1" aria-label="End date">
                                    <button class="text-brand">Close window</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($canUpdate)
        <h2 class="mt-6 text-base font-semibold">New version</h2>
        <form method="POST" action="{{ route('tax-rules.versions.store', $rule) }}" class="mt-3 grid max-w-3xl gap-3">
            @csrf
            @include('tax.rules.fields', ['versionOnly' => true, 'taxTypes' => []])
            <button class="w-fit rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save new draft version</button>
        </form>
    @endif
    @if ($canRetire && $rule->status === 'active')
        <form method="POST" action="{{ route('tax-rules.retire', $rule) }}" class="mt-4">@csrf<button class="rounded border border-line bg-white px-3 py-2 text-sm">Retire rule</button></form>
    @endif
</x-layouts.app>
