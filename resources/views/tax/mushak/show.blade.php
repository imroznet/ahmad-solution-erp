@php
    $map = \App\Tax\MushakFieldMap::class;
@endphp
<x-layouts.app :title="$document->displayNumber()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $document->displayNumber() }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $document->buyer_name ?: 'Buyer not entered' }} · {{ $document->statusLabel() }} · {{ $document->reviewLabel() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPdf)
                <a href="{{ route('mushak.pdf', $document) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Original PDF</a>
                <a href="{{ route('mushak.pdf', ['mushak' => $document, 'copy' => 'duplicate']) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Duplicate PDF</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('mushak.edit', $document) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">{{ $document->manual_vat_notice }}</p>

    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Buyer source</dt><dd>{{ $document->buyer_source === 'manual' ? 'One-off buyer, not a customer record' : 'Existing customer' }}</dd></div>
        <div><dt class="text-muted">{{ $map::BUYER_BIN }}</dt><dd>{{ $document->buyer_bin ?: 'Not entered' }}</dd></div>
        <div><dt class="text-muted">{{ $map::DESTINATION }}</dt><dd>{{ $document->destination ?: 'Not entered' }}</dd></div>
        <div><dt class="text-muted">{{ $map::ISSUE_DATE }}</dt><dd>{{ $document->issue_date?->format('d M Y') ?: 'Not entered' }} {{ $document->issue_time }}</dd></div>
        <div><dt class="text-muted">Total VAT</dt><dd>{{ $document->nullableDecimal('total_vat') ?? 'Not entered' }}</dd></div>
        <div><dt class="text-muted">ERP determined the rate</dt><dd>No</dd></div>
        @if ($document->invoice)
            <div class="sm:col-span-2"><dt class="text-muted">Commercial invoice</dt><dd><a href="{{ route('invoices.show', $document->invoice) }}" class="text-brand hover:underline">{{ $document->invoice->displayNumber() }}</a></dd></div>
        @endif
    </dl>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Description</th>
                    <th class="px-3 py-2 font-medium">Qty</th>
                    <th class="px-3 py-2 font-medium">Value</th>
                    <th class="px-3 py-2 font-medium">VAT %</th>
                    <th class="px-3 py-2 font-medium">VAT amount</th>
                    <th class="px-3 py-2 font-medium">Inclusive</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $line->text('description') }}</td>
                        <td class="px-3 py-2">{{ $line->nullableDecimal('quantity') }}</td>
                        <td class="px-3 py-2">{{ $line->nullableDecimal('line_value') ?? 'Not entered' }}</td>
                        <td class="px-3 py-2">{{ $line->nullableDecimal('vat_rate') ?? 'Not entered' }}</td>
                        <td class="px-3 py-2">{{ $line->nullableDecimal('vat_amount') ?? 'Not entered' }}</td>
                        <td class="px-3 py-2">{{ $line->nullableDecimal('inclusive_value') ?? 'Not entered' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($document->vdsRecords->isNotEmpty() || $document->aitRecords->isNotEmpty())
        <div class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Separate records</h2>
            <p class="mt-1 text-muted">These amounts are not part of the VAT total above.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($document->vdsRecords as $record)
                    <li>VDS {{ $record->nullableDecimal('deducted_amount') }} — <a href="{{ route('vds.show', $record) }}" class="text-brand hover:underline">view</a></li>
                @endforeach
                @foreach ($document->aitRecords as $record)
                    <li>AIT {{ $record->nullableDecimal('deducted_amount') }} — <a href="{{ route('ait.show', $record) }}" class="text-brand hover:underline">view</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($canIssue)
        <form method="POST" action="{{ route('mushak.issue', $document) }}" class="mt-4">
            @csrf
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Issue Mushak 6.3</button>
        </form>
    @endif

    @if ($canRecordTreatment)
        <form method="POST" action="{{ route('mushak.treatment', $document) }}" class="mt-4 space-y-3 rounded border border-line bg-white p-4">
            @csrf
            <h2 class="text-sm font-medium">Record a tax treatment</h2>
            <p class="text-sm text-muted">This does not change the typed VAT percentage or VAT amount, and it does not mean the ERP calculated them.</p>
            <div>
                <label for="tax_rule_id" class="block text-sm font-medium">Approved output VAT rule</label>
                <select id="tax_rule_id" name="tax_rule_id" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    <option value="">None</option>
                    @foreach ($rules as $rule)
                        <option value="{{ $rule->id }}">{{ $rule->rule_key }} version {{ $rule->version }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="treatment_note" class="block text-sm font-medium">What was confirmed</label>
                <textarea id="treatment_note" name="treatment_note" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            </div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmed" value="1"> I am recording a treatment. The typed figures stay as entered.</label>
            <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Record treatment</button>
        </form>
    @endif

    @if ($document->treatment_note)
        <div class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Recorded treatment</h2>
            <p class="mt-2">{{ $document->treatment_note }}</p>
            @if ($document->treatmentRule)
                <p class="mt-2 text-muted">Rule {{ $document->treatmentRule->rule_key }} version {{ $document->treatmentRule->version }}. Its rate was not copied onto the lines.</p>
            @endif
        </div>
    @endif

    @if ($canVoid)
        <form method="POST" action="{{ route('mushak.void', $document) }}" class="mt-4 space-y-3 rounded border border-line bg-white p-4">
            @csrf
            <h2 class="text-sm font-medium">Void</h2>
            <p class="text-sm text-muted">Void keeps the document. It does not edit the figures and it does not create a statutory adjustment form.</p>
            <label for="void_reason" class="block text-sm font-medium">Reason</label>
            <textarea id="void_reason" name="void_reason" rows="2" class="w-full rounded border border-line px-3 py-2 text-sm"></textarea>
            <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Void document</button>
        </form>
    @endif
</x-layouts.app>
