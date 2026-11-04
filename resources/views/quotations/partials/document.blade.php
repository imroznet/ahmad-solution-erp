@php
    $money = $revision->totalMoney();
@endphp
<section class="rounded border border-line bg-white p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Quotation</p>
            <h2 class="text-lg font-semibold">{{ $quotation->number }} · Revision {{ $revision->revision_number }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $revision->statusLabel() }} · {{ $revision->currency_code }}</p>
        </div>
        <dl class="text-sm">
            <div class="flex justify-between gap-6"><dt class="text-muted">Date</dt><dd>{{ $revision->quotation_date?->timezone('Asia/Dhaka')->format('d M Y') }}</dd></div>
            <div class="mt-1 flex justify-between gap-6"><dt class="text-muted">Valid until</dt><dd>{{ $revision->valid_until?->timezone('Asia/Dhaka')->format('d M Y') }}</dd></div>
        </dl>
    </div>

    <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
        <div>
            <h3 class="font-medium">Customer</h3>
            <p class="mt-1">{{ $revision->party_legal_name }}</p>
            @if ($revision->party_trading_name)
                <p class="text-muted">{{ $revision->party_trading_name }}</p>
            @endif
            @if ($revision->party_tin)
                <p>TIN {{ $revision->party_tin }}</p>
            @endif
            @if ($revision->party_bin)
                <p>BIN {{ $revision->party_bin }}</p>
            @endif
            @if ($revision->contact_name)
                <p class="mt-2">{{ $revision->contact_name }}@if ($revision->contact_designation), {{ $revision->contact_designation }}@endif</p>
            @endif
            @if ($revision->contact_phone)
                <p>{{ $revision->contact_phone }}</p>
            @endif
            @if ($revision->contact_email)
                <p>{{ $revision->contact_email }}</p>
            @endif
        </div>
        <div>
            <h3 class="font-medium">Billing address</h3>
            @forelse ($revision->addressLines('billing_address') as $line)
                <p>{{ $line }}</p>
            @empty
                <p class="text-muted">No billing address was recorded.</p>
            @endforelse
            <h3 class="mt-3 font-medium">Delivery address</h3>
            @forelse ($revision->addressLines('shipping_address') as $line)
                <p>{{ $line }}</p>
            @empty
                <p class="text-muted">No delivery address was recorded.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="py-2 pr-3 font-medium">#</th>
                    <th class="py-2 pr-3 font-medium">Description</th>
                    <th class="py-2 pr-3 font-medium">Unit</th>
                    <th class="py-2 pr-3 text-right font-medium">Qty</th>
                    <th class="py-2 pr-3 text-right font-medium">Unit price</th>
                    <th class="py-2 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($revision->lines as $line)
                    <tr class="border-t border-line align-top">
                        <td class="py-2 pr-3">{{ $line->position }}</td>
                        <td class="py-2 pr-3 whitespace-pre-wrap">{{ $line->description }}</td>
                        <td class="py-2 pr-3">{{ $line->unit }}</td>
                        <td class="py-2 pr-3 text-right">{{ $line->quantityLabel() }}</td>
                        <td class="py-2 pr-3 text-right">{{ $line->unitPriceMoney()->format() }}</td>
                        <td class="py-2 text-right">{{ $line->lineTotalMoney()->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-line">
                    <td colspan="5" class="py-2 pr-3 text-right font-medium">Total ({{ $revision->currency_code }})</td>
                    <td class="py-2 text-right font-semibold">{{ $money->format() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="mt-4 text-sm">{{ $revision->tax_notice }}</p>
    @if ($revision->terms)
        <h3 class="mt-4 text-sm font-medium">Terms</h3>
        <p class="mt-1 whitespace-pre-wrap text-sm">{{ $revision->terms }}</p>
    @endif
    @if ($revision->customer_notes)
        <h3 class="mt-4 text-sm font-medium">Notes</h3>
        <p class="mt-1 whitespace-pre-wrap text-sm">{{ $revision->customer_notes }}</p>
    @endif
</section>
