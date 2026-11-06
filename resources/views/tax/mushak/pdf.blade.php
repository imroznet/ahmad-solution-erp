<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->displayNumber() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #17211c; font-size: 10px; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 12px; margin: 0 0 4px; }
        p { margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #c5d0c9; padding: 4px; vertical-align: top; text-align: left; }
        th { background: #f4f7f5; }
        .right { text-align: right; }
        .muted { color: #5d6b64; }
        .banner { font-size: 14px; }
    </style>
</head>
<body>
    <p class="muted">Government of the People's Republic of Bangladesh · National Board of Revenue</p>
    <h1>Mushak 6.3 — Tax invoice</h1>
    <p>Column order follows the official form checked on 6 November 2026. Labels are in English because this PDF font does not include Bengali. This rendering is not an NBR compliance certificate.</p>
    @if ($document->stringAttribute('status') === 'draft')
        <p class="banner"><strong>DRAFT</strong></p>
    @elseif ($document->stringAttribute('status') === 'voided')
        <p class="banner"><strong>VOIDED</strong></p>
    @endif

    <table>
        <tr>
            <td style="width: 50%;">
                <h2>Registered person</h2>
                <p>{{ $company['legal_name'] }}</p>
                @if (!empty($company['trading_name']))<p>{{ $company['trading_name'] }}</p>@endif
                @foreach (($company['address'] ?? []) as $line)<p>{{ $line }}</p>@endforeach
                <p>BIN: {{ $company['bin'] ?: '—' }}</p>
                @if (!empty($company['phone']))<p>{{ $company['phone'] }}</p>@endif
            </td>
            <td>
                <p><strong>Invoice number:</strong> {{ $document->displayNumber() }}</p>
                <p class="muted">Company series. Not claimed as an NBR-prescribed number format.</p>
                <p><strong>Date of issue:</strong> {{ $document->document_date?->format('d M Y') }}</p>
                <p><strong>Time of issue:</strong> {{ $issuedAt ?: 'Not issued' }}</p>
                <p><strong>Commercial invoice:</strong> {{ $document->invoice_number_snapshot ?: $document->invoice?->displayNumber() }}</p>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <h2>Buyer</h2>
                <p>{{ $document->buyer_legal_name }}</p>
                <p>BIN: {{ $document->buyer_bin ?: '—' }}</p>
                @foreach ($document->buyerLines() as $line)<p>{{ $line }}</p>@endforeach
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Sl</th>
                <th>Goods or service</th>
                <th>Unit</th>
                <th class="right">Quantity</th>
                <th class="right">Unit price</th>
                <th class="right">Total price</th>
                <th class="right">Supplementary duty</th>
                <th class="right">VAT rate</th>
                <th class="right">VAT amount</th>
                <th class="right">Value including duties and taxes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->lines as $line)
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="right">{{ $line->quantityLabel() }}</td>
                    <td class="right">{{ $line->money('unit_price')?->format() }}</td>
                    <td class="right">{{ $line->money('base_amount')?->format() }}</td>
                    <td class="right">{{ $line->money('supplementary_duty_amount')?->format() ?? '—' }}</td>
                    <td class="right">{{ ($rates[$line->id] ?? '') === '' ? '—' : $rates[$line->id].'%' }}</td>
                    <td class="right">{{ $line->money('vat_amount')?->format() ?? '—' }}</td>
                    <td class="right">{{ $line->money('duties_amount')?->format() ?? '—' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5" class="right">Total</td>
                <td class="right">{{ $document->money('base_total')?->format() }}</td>
                <td class="right">{{ $document->money('supplementary_duty_total')?->format() ?? '—' }}</td>
                <td></td>
                <td class="right">{{ $document->money('vat_total')?->format() ?? '—' }}</td>
                <td class="right">{{ $document->money('duties_total')?->format() ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 10px;">{{ $document->vat_notice }}</p>
    <p class="muted">Review: {{ $document->reviewLabel() }}. VAT percentage entered by the user. Calculated with BDT half-away-from-zero rounding to two decimal places. When the VAT-inclusive amount is edited, that amount is kept exactly and VAT is the residual after the base is rounded. VDS and AIT are not on this document.</p>
    <p class="muted">Form source checked 6 November 2026. SHA-256 {{ $document->form_sha256 }}.</p>
    @if ($document->stringAttribute('internal_notes') !== '')
        {{-- Internal notes are intentionally omitted. --}}
    @endif
</body>
</html>
