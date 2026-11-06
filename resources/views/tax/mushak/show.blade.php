<x-layouts.app :title="$invoice->displayNumber()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $invoice->displayNumber() }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $invoice->buyer_name }} · {{ $invoice->statusLabel() }} · {{ $invoice->reviewLabel() }}</p>
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
    <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">The VAT percentage was entered by a user. Calculating from it is not a decision that the percentage is legally applicable. VDS and AIT are not included. Commercial invoice {{ $invoice->commercialInvoice?->displayNumber() }} is unchanged.</p>
    <dl class="mt-4 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Seller</dt><dd>{{ $invoice->seller_legal_name ?: 'Captured when issued' }}</dd></div>
        <div><dt class="text-muted">Seller BIN</dt><dd>{{ $invoice->seller_bin ?: 'Required before issue' }}</dd></div>
        <div><dt class="text-muted">Buyer BIN</dt><dd>{{ $invoice->buyer_bin ?: 'Not entered' }}</dd></div>
        <div><dt class="text-muted">Precision / rounding</dt><dd>{{ $invoice->calculation_precision }} / {{ \App\Tax\VatRounding::label($invoice->rounding_method) }}</dd></div>
        <div><dt class="text-muted">Base</dt><dd>{{ $invoice->subtotal_base ?? '—' }}</dd></div>
        <div><dt class="text-muted">VAT</dt><dd>{{ $invoice->total_vat ?? '—' }}</dd></div>
        <div><dt class="text-muted">Rounding / decimal adjustment</dt><dd>{{ $invoice->total_adjustment }}</dd></div>
        <div><dt class="text-muted">Final</dt><dd>{{ $invoice->total_final ?? '—' }}</dd></div>
        <div><dt class="text-muted">Supplementary duty</dt><dd>{{ $invoice->total_supplementary_duty ?? 'Not entered' }}</dd></div>
    </dl>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2">Description</th>
                    <th class="px-3 py-2">Base</th>
                    <th class="px-3 py-2">Rate</th>
                    <th class="px-3 py-2">VAT</th>
                    <th class="px-3 py-2">Adjustment</th>
                    <th class="px-3 py-2">Final</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2">{{ $line->description }}</td>
                        <td class="px-3 py-2">{{ $line->base_amount }}</td>
                        <td class="px-3 py-2">{{ $line->vat_rate_percent ?? 'Blank' }}</td>
                        <td class="px-3 py-2">{{ $line->vat_amount ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $line->adjustment_amount }} @if ($line->adjustment_reason)<span class="text-muted">({{ $line->adjustment_reason }})</span>@endif</td>
                        <td class="px-3 py-2">{{ $line->final_amount ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @if ($canIssue)
            <form method="POST" action="{{ route('mushak.issue', $invoice) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <p class="text-sm">Issuing freezes this document and assigns M63-year-sequence. The company BIN must already be on the company profile.</p>
                <button class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Issue Mushak 6.3</button>
            </form>
        @endif
        @if ($canVoid)
            <form method="POST" action="{{ route('mushak.void', $invoice) }}" class="rounded border border-line bg-white p-4">
                @csrf
                <label class="text-sm">Void reason
                    <input name="void_reason" class="mt-1 w-full rounded border border-line px-2 py-1" required>
                </label>
                <button class="mt-3 rounded border border-line px-3 py-2 text-sm">Void</button>
            </form>
        @endif
    </div>
</x-layouts.app>
