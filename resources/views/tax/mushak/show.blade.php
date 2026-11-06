<x-layouts.app :title="$document->displayNumber()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $document->displayNumber() }}</h1>
            <p class="mt-1 text-sm text-muted">Mushak 6.3 · {{ $document->statusLabel() }} · {{ $document->reviewLabel() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPdf)
                <a href="{{ route('mushak.pdf', $document) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('mushak.edit', $document) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">{{ $document->vat_notice }}</p>

    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Commercial invoice</dt><dd>{{ $document->invoice?->displayNumber() }}</dd></div>
        <div><dt class="text-muted">Document date</dt><dd>{{ $document->document_date?->format('d M Y') }}</dd></div>
        <div><dt class="text-muted">Buyer</dt><dd>{{ $document->buyer_legal_name }} @if ($document->buyer_bin)<span class="text-muted">BIN {{ $document->buyer_bin }}</span>@endif</dd></div>
        <div><dt class="text-muted">Buyer source</dt><dd>{{ $document->buyer_source === 'manual' ? 'Entered manually. No customer was created.' : 'Saved customer' }}</dd></div>
        @foreach ($document->buyerLines() as $line)
            <div class="sm:col-span-2"><dt class="text-muted">Buyer address</dt><dd>{{ $line }}</dd></div>
        @endforeach
        <div><dt class="text-muted">Base total</dt><dd>{{ $document->money('base_total')?->format() }}</dd></div>
        <div><dt class="text-muted">VAT total</dt><dd>{{ $document->money('vat_total')?->format() ?? 'Not calculated' }}</dd></div>
        <div><dt class="text-muted">VAT-inclusive total</dt><dd>{{ $document->money('inclusive_total')?->format() ?? 'Not calculated' }}</dd></div>
        <div><dt class="text-muted">Supplementary duty</dt><dd>{{ $document->money('supplementary_duty_total')?->format() ?? 'Not entered' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Value including duties and taxes</dt><dd>{{ $document->money('duties_total')?->format() ?? 'Not calculated' }}</dd></div>
    </dl>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Description</th>
                    <th class="px-3 py-2 text-right font-medium">Base</th>
                    <th class="px-3 py-2 text-right font-medium">VAT %</th>
                    <th class="px-3 py-2 text-right font-medium">VAT</th>
                    <th class="px-3 py-2 text-right font-medium">VAT-inclusive</th>
                    <th class="px-3 py-2 font-medium">Entered by</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $line->description }} <span class="text-muted">{{ $line->quantityLabel() }} {{ $line->unit }}</span></td>
                        <td class="px-3 py-2 text-right">{{ $line->money('base_amount')?->format() }}</td>
                        <td class="px-3 py-2 text-right">{{ ($rates[$line->id] ?? '') === '' ? 'Blank' : $rates[$line->id].'%' }}</td>
                        <td class="px-3 py-2 text-right">{{ $line->money('vat_amount')?->format() ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ $line->money('inclusive_amount')?->format() ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $line->rateEnteredBy?->name ?? '—' }} @if ($line->rate_entered_at)<span class="text-muted">{{ $line->rate_entered_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="mt-6">
        <h2 class="text-base font-semibold">VDS and AIT</h2>
        <p class="mt-1 max-w-3xl text-sm text-muted">These records are separate from the VAT amounts above. They are not added to the VAT total.</p>
        @if ($vdsRecords->isEmpty() && $aitRecords->isEmpty())
            <p class="mt-2 text-sm text-muted">No VDS or AIT record is linked to this document.</p>
        @endif
        @foreach ($vdsRecords as $record)
            <p class="mt-2 text-sm"><a class="text-brand" href="{{ route('vds.show', $record) }}">VDS {{ $record->taxMoney()->format() }}</a> <span class="text-muted">not included in VAT</span></p>
        @endforeach
        @foreach ($aitRecords as $record)
            <p class="mt-2 text-sm"><a class="text-brand" href="{{ route('ait.show', $record) }}">AIT {{ $record->taxMoney()->format() }}</a> <span class="text-muted">not included in VAT</span></p>
        @endforeach
    </section>

    @if ($canIssue)
        <form method="POST" action="{{ route('mushak.issue', $document) }}" class="mt-6">
            @csrf
            <button class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Issue Mushak 6.3</button>
        </form>
    @endif
    @if ($canVoid)
        <form method="POST" action="{{ route('mushak.void', $document) }}" class="mt-4 max-w-xl space-y-2">
            @csrf
            <label for="void_reason" class="block text-sm font-medium">Void reason</label>
            <textarea id="void_reason" name="void_reason" rows="2" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button class="rounded border border-line bg-white px-3 py-2 text-sm">Void document</button>
        </form>
    @endif
</x-layouts.app>
