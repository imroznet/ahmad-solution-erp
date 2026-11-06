<x-layouts.app :title="$invoice->displayNumber().' Mushak 6.3'">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Mushak 6.3 {{ $invoice->displayNumber() }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $invoice->buyer_name }} · {{ $invoice->statusLabel() }} · Commercial invoice {{ $invoice->commercialInvoice?->displayNumber() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPdf)
                <a href="{{ route('mushak.pdf', $invoice) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('mushak.edit', $invoice) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">The VAT percentage was entered by a user. This system calculated amounts from that percentage and did not decide whether it applies. Rounding / Decimal Adjustment is not VAT. VDS and AIT are not included.</p>

    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Calculation precision</dt><dd>{{ $invoice->calculation_precision }} decimal places</dd></div>
        <div><dt class="text-muted">Rounding method</dt><dd>{{ \App\Tax\RoundingMethod::label($invoice->rounding_method) }}</dd></div>
        <div><dt class="text-muted">Base</dt><dd>{{ $invoice->base_total ?? '—' }}</dd></div>
        <div><dt class="text-muted">VAT</dt><dd>{{ $invoice->vat_total ?? 'Not calculated' }}</dd></div>
        <div><dt class="text-muted">Rounding / Decimal Adjustment</dt><dd>{{ $invoice->adjustment_total ?? '0' }}</dd></div>
        <div><dt class="text-muted">Final</dt><dd>{{ $invoice->final_total ?? 'Not calculated' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-muted">Rule check</dt><dd>{{ $invoice->rule_explanation ?: $match->explanation }}</dd></div>
        <div><dt class="text-muted">Tax review</dt><dd>{{ str_replace('_', ' ', $invoice->tax_review_status) }}</dd></div>
        <div><dt class="text-muted">Buyer BIN</dt><dd>{{ $invoice->buyer_bin ?: 'Not recorded' }}</dd></div>
    </dl>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Description</th>
                    <th class="px-3 py-2 font-medium">Base</th>
                    <th class="px-3 py-2 font-medium">VAT %</th>
                    <th class="px-3 py-2 font-medium">VAT</th>
                    <th class="px-3 py-2 font-medium">Adjustment</th>
                    <th class="px-3 py-2 font-medium">Final</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $line->description }}</td>
                        <td class="px-3 py-2">{{ $line->base_amount }}</td>
                        <td class="px-3 py-2">{{ $line->rateValue() ?? 'Blank' }}</td>
                        <td class="px-3 py-2">{{ $line->vat_amount ?? 'Not calculated' }}</td>
                        <td class="px-3 py-2">{{ $line->adjustment_amount }} @if ($line->adjustment_reason)<span class="block text-xs text-muted">{{ $line->adjustment_reason }}</span>@endif</td>
                        <td class="px-3 py-2">{{ $line->final_amount ?? 'Not calculated' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($invoice->internal_notes)
        <p class="mt-4 text-sm text-muted">Internal: {{ $invoice->internal_notes }}</p>
    @endif

    @if ($canIssue)
        <form method="POST" action="{{ route('mushak.issue', $invoice) }}" class="mt-4 space-y-3 rounded border border-line bg-white p-4">
            @csrf
            <h2 class="text-sm font-medium">Issue</h2>
            <p class="text-sm text-muted">Issuing freezes the lines, the company BIN, and the buyer. The company profile must already contain a BIN.</p>
            <label class="block text-sm"><input type="checkbox" name="legal_treatment_confirmed" value="1"> I confirm this manual treatment. The system has not certified the percentage.</label>
            <div>
                <label for="legal_treatment_note" class="block text-sm font-medium">Confirmation note</label>
                <textarea id="legal_treatment_note" name="legal_treatment_note" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('legal_treatment_note') }}</textarea>
                <p class="mt-1 text-xs text-muted">This note stays off the customer PDF.</p>
            </div>
            <div>
                <label for="review_reason" class="block text-sm font-medium">Review reason if no rule matches</label>
                <textarea id="review_reason" name="review_reason" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('review_reason') }}</textarea>
            </div>
            <div>
                <label for="prepared_by_designation" class="block text-sm font-medium">Designation on the signature block</label>
                <input id="prepared_by_designation" name="prepared_by_designation" value="{{ old('prepared_by_designation') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Issue Mushak 6.3</button>
        </form>
    @endif

    @if ($canVoid)
        <form method="POST" action="{{ route('mushak.void', $invoice) }}" class="mt-4 rounded border border-line bg-white p-4">
            @csrf
            <label for="void_reason" class="block text-sm font-medium">Void reason</label>
            <input id="void_reason" name="void_reason" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Void keeps the record. It is not a Mushak credit note.</p>
            <button type="submit" class="mt-3 rounded border border-line px-3 py-2 text-sm">Void</button>
        </form>
    @endif
</x-layouts.app>
