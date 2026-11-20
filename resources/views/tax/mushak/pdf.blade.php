<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number ?: 'Mushak 6.3 draft' }}</title>
    @include('pdf.base')
    <style>
        body { font-size: 8px; }
        .center { text-align: center; }
        .gov { font-size: 9px; font-weight: 700; margin: 0; }
        .title { font-size: 11px; font-weight: 700; margin: 4px 0 0; }
        .cite { margin: 1px 0 4px; }
        .form-no { display: inline-block; border: 1.5px solid #111; padding: 4px 8px; font-size: 11px; font-weight: 700; letter-spacing: 0.4px; }
        table.form { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.form th, table.form td { border: 1px solid #111; padding: 2px; vertical-align: top; font-size: 7px; }
        table.form th { font-weight: 700; text-align: center; }
        table.parties td { border: 0; padding: 1px 8px 1px 0; vertical-align: top; font-size: 8px; }
        .seller { margin: 4px 0; }
        .sign { margin-top: 8px; }
        .footnote { margin-top: 6px; }
        .note { margin-top: 8px; border: 1px solid #222; padding: 6px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 18%;"></td>
            <td class="center">
                <img src="{{ \App\Documents\PdfFactory::fileUrl(resource_path('images/bangladesh-government-emblem.png')) }}" alt="" style="width: 42px; height: auto;">
                <p class="gov">Government of the People's Republic of Bangladesh</p>
                <p class="gov">National Board of Revenue</p>
            </td>
            <td style="width: 22%; text-align: right; vertical-align: top;">
                <span class="form-no">MUSAK-6.3</span>
            </td>
        </tr>
    </table>
    <p class="center title" style="white-space: nowrap;">Tax Invoice &nbsp;|&nbsp; কর চালানপত্র</p>
    <p class="center cite">[Rule 40, sub-rule (1), clauses (ga) and (cha)]</p>
    <p class="center cite">[ বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য ]</p>

    <div class="seller center">
        @if ($invoice->stringAttribute('status') === 'draft' && is_array($draftCompany ?? null))
            <p><strong>{{ $draftCompany['legal_name'] }}</strong></p>
            <p>BIN: {{ $draftCompany['bin'] ?: 'Missing' }}</p>
            <p>Address of issue:</p>
            @foreach ($draftCompany['address'] as $line)
                <p class="letterhead-meta">{{ $line }}</p>
            @endforeach
        @else
            <p><strong>{{ $invoice->seller_legal_name ?: 'Captured from the company profile at issue' }}</strong></p>
            <p>BIN: {{ $invoice->seller_bin ?: 'Missing' }}</p>
            <p>Address of issue:</p>
            <p class="letterhead-meta">{!! nl2br(e($invoice->seller_address ?: '')) !!}</p>
        @endif
    </div>

    <table class="parties">
        <tr>
            <td style="width: 58%;">
                <p><strong>Buyer name:</strong> {{ $invoice->buyer_name }}</p>
                <p><strong>Buyer BIN:</strong> {{ $invoice->buyer_bin ?: 'Not entered' }}</p>
                <p><strong>Destination of supply:</strong> {{ $invoice->destination }}</p>
            </td>
            <td>
                <p><strong>Challan number:</strong> {{ $invoice->number ?: 'Draft' }}</p>
                <p><strong>Date of issue:</strong> {{ $invoice->document_date?->format('d M Y') }}</p>
                <p><strong>Time of issue:</strong> {{ $invoice->issued_at?->timezone('Asia/Dhaka')->format('H:i') ?? 'Not issued' }}</p>
            </td>
        </tr>
    </table>

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
    <table class="form">
        <thead>
            <tr>
                <th>Sl</th>
                <th>Goods or service</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Unit value excl. taxes</th>
                <th>Total value excl. taxes</th>
                <th>SD</th>
                <th>VAT rate</th>
                <th>VAT amount</th>
                <th>Value incl. duties and taxes</th>
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
    <p class="footnote">Amounts in the table are BDT.</p>

    <div class="sign">
        <p><strong>Authorised person</strong></p>
        <p>Name: {{ $invoice->responsible_name }}</p>
        <p>Designation: {{ $invoice->responsible_designation }}</p>
        <p>Signature: ____________________ &nbsp;&nbsp; Seal: ____________________</p>
    </div>
    <p class="footnote">* Source tax on this supply is not calculated on this form. VDS and AIT are separate records and are not included in the VAT amount or in the value including duties and taxes.</p>
    <div class="note">
        <strong>Supplementary calculation — not a Mushak 6.3 field.</strong>
        <p>Formula used by this system: final = base + VAT + adjustment. The percentage was typed by {{ $invoice->status === 'issued' ? 'a user and stored with the document' : 'the user on this draft' }}. The system did not decide whether it applies.</p>
        <p>Stored precision {{ $invoice->calculation_precision }}, rounding {{ \App\Tax\VatRounding::label($invoice->rounding_method) }}. BDT columns above are half-up to 2 decimal places for presentation and are not written back over the stored calculation.</p>
        <p>Base {{ $invoice->subtotal_base ?? '—' }} · VAT {{ $invoice->total_vat ?? '—' }} · Rounding / decimal adjustment {{ $invoice->total_adjustment }} · Final {{ $invoice->total_final ?? '—' }}.</p>
        <p>Review: {{ $invoice->reviewLabel() }}. Supplementary duty is separate from VAT. VDS and AIT are not on this form.</p>
    </div>
</body>
</html>
