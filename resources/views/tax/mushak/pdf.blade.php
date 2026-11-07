<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #222; padding: 4px; vertical-align: top; }
        .note { margin-top: 12px; border: 1px solid #222; padding: 8px; }
        .muted { color: #333; }
    </style>
</head>
<body>
    <p class="muted">Government of the People's Republic of Bangladesh · National Board of Revenue</p>
    <h1>Mushak 6.3 · Tax invoice (kor challanpatra)</h1>
    <p>Rule 40, sub-rule (1), clauses (ga) and (cha), as printed on the owner-confirmed form. This PDF uses English labels because the PDF font does not draw Bengali. It is not an NBR certification.</p>
    <p><strong>{{ $invoice->number ?: 'Draft' }}</strong> · Date {{ $invoice->document_date?->format('d M Y') }} · Time {{ $invoice->issued_at?->timezone('Asia/Dhaka')->format('H:i') ?? 'Not issued' }}</p>
    <p><strong>Registered person:</strong> {{ $invoice->seller_legal_name ?: 'Captured from the company profile at issue' }}<br>
        BIN: {{ $invoice->seller_bin ?: 'Missing' }}<br>
        Address of issue: {!! nl2br(e($invoice->seller_address ?: '')) !!}</p>
    <p><strong>Buyer:</strong> {{ $invoice->buyer_name }}<br>
        BIN: {{ $invoice->buyer_bin ?: 'Not entered' }}<br>
        Destination of supply: {{ $invoice->destination }}</p>
    @php
        // The official form's total row. Column 10 is base + VAT + SD, as the
        // field map records; the rounding adjustment is not a column, so it
        // is not in this total. The final amount including the adjustment is
        // in the supplementary note below.
        $totalInclusive = null;
        if (is_string($invoice->subtotal_base)) {
            $totalInclusive = \App\Support\Decimal::of($invoice->subtotal_base);
            if (is_string($invoice->total_vat)) {
                $totalInclusive = $totalInclusive->plus(\App\Support\Decimal::of($invoice->total_vat));
            }
            if (is_string($invoice->total_supplementary_duty)) {
                $totalInclusive = $totalInclusive->plus(\App\Support\Decimal::of($invoice->total_supplementary_duty));
            }
            $totalInclusive = \App\Tax\VatPresentation::poisha((string) $totalInclusive);
        }
    @endphp
    <table>
        <thead>
            <tr>
                <th>Sl</th>
                <th>Goods or service</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Unit value excl. taxes (BDT)</th>
                <th>Total value excl. taxes (BDT)</th>
                <th>SD (BDT)</th>
                <th>VAT rate typed by user</th>
                <th>VAT amount (BDT)</th>
                <th>Value incl. duties and taxes (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                @php
                    $base = $line->base_amount;
                    $vat = $line->vat_amount;
                    $sd = $line->supplementary_duty_amount;
                    $inclusive = null;
                    if (is_string($base) && is_string($vat)) {
                        $inclusive = \App\Support\Decimal::of($base)->plus(\App\Support\Decimal::of($vat));
                        if (is_string($sd) && $sd !== '') {
                            $inclusive = $inclusive->plus(\App\Support\Decimal::of($sd));
                        }
                        $inclusive = \App\Tax\VatPresentation::poisha((string) $inclusive);
                    }
                @endphp
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->unit }}</td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ $line->source_unit_price }}</td>
                    <td>{{ is_string($base) ? \App\Tax\VatPresentation::poisha($base) : '' }}</td>
                    <td>{{ is_string($sd) && $sd !== '' ? \App\Tax\VatPresentation::poisha($sd) : '' }}</td>
                    <td>{{ $line->vat_rate_percent === null ? '' : \App\Tax\VatPresentation::rate($line->vat_rate_percent).'%' }}</td>
                    <td>{{ is_string($vat) ? \App\Tax\VatPresentation::poisha($vat) : '' }}</td>
                    <td>{{ $inclusive }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5"><strong>Total</strong></td>
                <td>{{ is_string($invoice->subtotal_base) ? \App\Tax\VatPresentation::poisha($invoice->subtotal_base) : '' }}</td>
                <td>{{ is_string($invoice->total_supplementary_duty) ? \App\Tax\VatPresentation::poisha($invoice->total_supplementary_duty) : '' }}</td>
                <td></td>
                <td>{{ is_string($invoice->total_vat) ? \App\Tax\VatPresentation::poisha($invoice->total_vat) : '' }}</td>
                <td>{{ $totalInclusive }}</td>
            </tr>
        </tfoot>
    </table>
    <p>Responsible person: {{ $invoice->responsible_name }} {{ $invoice->responsible_designation }}</p>
    <p>Signature: ____________________ &nbsp; Seal: ____________________</p>
    <div class="note">
        <strong>Supplementary calculation — not a Mushak 6.3 field.</strong>
        <p>Formula used by this system: final = base + VAT + adjustment. The percentage was typed by {{ $invoice->status === 'issued' ? 'a user and stored with the document' : 'the user on this draft' }}. The system did not decide whether it applies.</p>
        <p>Stored precision {{ $invoice->calculation_precision }}, rounding {{ \App\Tax\VatRounding::label($invoice->rounding_method) }}. BDT columns above are half-up to 2 decimal places for presentation and are not written back over the stored calculation.</p>
        <p>Base {{ $invoice->subtotal_base ?? '—' }} · VAT {{ $invoice->total_vat ?? '—' }} · Rounding / decimal adjustment {{ $invoice->total_adjustment }} · Final {{ $invoice->total_final ?? '—' }}.</p>
        <p>Review: {{ $invoice->reviewLabel() }}. Supplementary duty is separate from VAT. VDS and AIT are not on this form.</p>
    </div>
</body>
</html>
