<x-layouts.app title="Mushak working paper">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3 working paper {{ $document->id }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $document->party_legal_name }} · {{ $document->invoice_number }} · {{ \App\Tax\TaxDocumentStatus::label($document->status) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPdf)
                <a href="{{ route('mushak.pdf', $document) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('mushak.edit', $document) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit</a>
            @endif
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm">This paper is not an issued Mushak 6.3. The commercial invoice remains tax-excluded.</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Classification</dt><dd>{{ $document->supply_classification }}</dd></div>
        <div><dt class="text-muted">Invoice total</dt><dd>{{ $document->invoice_total }} {{ $document->currency_code }}</dd></div>
        <div><dt class="text-muted">Challans</dt><dd>{{ implode(', ', $document->challan_numbers ?? []) ?: '—' }}</dd></div>
        <div><dt class="text-muted">Pinned preview</dt><dd>{{ is_array($document->pinned_rule_snapshot) ? ('BDT '.($document->pinned_rule_snapshot['amount'] ?? 'not calculated').' from rule '.$document->pinned_rule_snapshot['version_id']) : 'No rule pinned. Review required.' }}</dd></div>
    </dl>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Description</th><th class="px-3 py-2">Quantity</th><th class="px-3 py-2">Tax-exclusive total</th></tr></thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr class="border-t border-line"><td class="px-3 py-2">{{ $line->description }}</td><td class="px-3 py-2">{{ $line->quantity }}</td><td class="px-3 py-2">{{ $line->line_total }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($canIssue)
        <form method="POST" action="{{ route('mushak.issue', $document) }}" class="mt-4">@csrf<button class="rounded border border-line px-3 py-2 text-sm" type="submit">Attempt issue</button></form>
    @endif
    @if ($canVoid)
        <form method="POST" action="{{ route('mushak.void', $document) }}" class="mt-4 flex flex-wrap items-end gap-2">
            @csrf
            <div><label for="reason" class="block text-sm font-medium">Void reason</label><input id="reason" name="reason" required class="mt-1 rounded border border-line px-3 py-2 text-sm"></div>
            <button class="rounded border border-line px-3 py-2 text-sm" type="submit">Void paper</button>
        </form>
    @endif
</x-layouts.app>
