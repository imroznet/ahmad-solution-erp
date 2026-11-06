<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: "Noto Sans Bengali";
            src: url("{{ $font }}") format("truetype");
        }
        body { font-family: "Noto Sans Bengali", DejaVu Sans, sans-serif; color: #17211c; font-size: 10px; }
        h1 { font-size: 16px; margin: 0; text-align: center; }
        p { margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #9aa89f; padding: 3px; vertical-align: top; }
        th { font-weight: 600; text-align: center; }
        .right { text-align: right; }
        .plain td { border: 0; }
        .note { margin-top: 8px; }
    </style>
</head>
<body>
    <p style="text-align: center;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</p>
    <p style="text-align: center;">জাতীয় রাজস্ব বোর্ড</p>
    <h1>কর চালানপত্র / Tax invoice</h1>
    <p style="text-align: center;">মূসক-৬.৩ / Mushak 6.3</p>
    <p style="text-align: center;">[বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও (চ) দ্রষ্টব্য]</p>
    @if ($invoice->stringAttribute('status') === 'draft')
        <p style="text-align: center;"><strong>DRAFT</strong></p>
    @elseif ($invoice->stringAttribute('status') === 'voided')
        <p style="text-align: center;"><strong>VOIDED</strong></p>
    @endif

    <table class="plain">
        <tr>
            <td style="width: 60%;">
                <p><strong>Registered person / নিবন্ধিত ব্যক্তি:</strong> {{ $company['legal_name'] }}</p>
                @if (!empty($company['trading_name']))<p>{{ $company['trading_name'] }}</p>@endif
                <p><strong>BIN / বিআইএন:</strong> {{ $company['bin'] ?: 'Missing' }}</p>
                @foreach ($company['address'] as $line)<p>{{ $line }}</p>@endforeach
                @if (!empty($company['phone']))<p>{{ $company['phone'] }}</p>@endif
                @if (!empty($company['email']))<p>{{ $company['email'] }}</p>@endif
            </td>
            <td>
                <p><strong>Invoice no. / চালানপত্র নম্বর:</strong> {{ $invoice->displayNumber() }}</p>
                <p><strong>Date of issue / ইস্যুর তারিখ:</strong> {{ $invoice->document_date?->format('d M Y') }}</p>
                <p><strong>Time of issue / ইস্যুর সময়:</strong> {{ $invoice->issued_at?->timezone('Asia/Dhaka')->format('H:i') ?? 'Not issued' }}</p>
                <p><strong>Buyer / ক্রেতা:</strong> {{ $invoice->buyer_name }}</p>
                <p><strong>Buyer BIN / ক্রেতার বিআইএন:</strong> {{ $invoice->buyer_bin ?: '—' }}</p>
                <p><strong>Buyer address / ক্রেতার ঠিকানা:</strong> {{ $invoice->buyer_address }}</p>
                <p><strong>Place of supply / সরবরাহের গন্তব্যস্থল:</strong> {{ $invoice->supply_destination ?: '—' }}</p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>ক্রমিক<br>Serial</th>
                <th>Goods or service / পণ্য বা সেবার বিবরণ</th>
                <th>Unit / সরবরাহের একক</th>
                <th>Qty / পরিমাণ</th>
                <th>Unit price excl. tax / একক মূল্য</th>
                <th>Total excl. tax / মোট মূল্য</th>
                <th>SD / সম্পূরক শুল্ক</th>
                <th>VAT % / মূসকের হার</th>
                <th>VAT amount / মূসকের পরিমাণ</th>
                <th>Value incl. VAT / মূল্য</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $index => $row)
                @php $line = $row['line']; @endphp
                <tr>
                    <td class="right">{{ $index + 1 }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="right">{{ $line->quantity }}</td>
                    <td class="right">{{ $row['display_unit'] ?? '—' }}</td>
                    <td class="right">{{ $row['display_base'] }}</td>
                    <td class="right">—</td>
                    <td class="right">{{ $line->rateValue() === null ? '' : rtrim(rtrim($line->rateValue(), '0'), '.') }}%</td>
                    <td class="right">{{ $row['display_vat'] ?? '' }}</td>
                    <td class="right">{{ $row['display_vat'] === null ? '' : $prices->display((string) \App\Support\Decimal::of($line->decimalAttribute('base_amount'))->plus(\App\Support\Decimal::of($line->decimalAttribute('vat_amount'))), $method) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="note">Supplementary duty is not calculated and was not entered. Column amounts are BDT at 2 decimal places. The unit price is the stored base divided by quantity, then shown at 2 decimal places. It is not a second calculation of VAT.</p>

    <h2 style="font-size: 12px; margin: 10px 0 4px;">Rounding / Decimal Adjustment</h2>
    <p>This is not a column on Mushak 6.3. It is not VAT and it is not supplementary duty. Stored calculation uses {{ $invoice->calculation_precision }} decimal places and {{ \App\Tax\RoundingMethod::label($method) }}. Invariant: final = base + VAT + adjustment.</p>
    <table>
        <thead>
            <tr>
                <th>Line</th>
                <th>Stored base</th>
                <th>Entered VAT %</th>
                <th>Stored VAT</th>
                <th>Adjustment</th>
                <th>Reason</th>
                <th>Stored final</th>
                <th>Residual</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $index => $row)
                @php $line = $row['line']; @endphp
                <tr>
                    <td class="right">{{ $index + 1 }}</td>
                    <td class="right">{{ $line->base_amount }}</td>
                    <td class="right">{{ $line->rateValue() ?? 'Blank' }}</td>
                    <td class="right">{{ $line->vat_amount ?? 'Not calculated' }}</td>
                    <td class="right">{{ $line->adjustment_amount }}</td>
                    <td>{{ $line->adjustment_reason }}</td>
                    <td class="right">{{ $line->final_amount ?? 'Not calculated' }}</td>
                    <td class="right">{{ $line->residual_amount }}</td>
                </tr>
            @endforeach
            <tr>
                <td>Total</td>
                <td class="right">{{ $invoice->base_total }}</td>
                <td></td>
                <td class="right">{{ $invoice->vat_total ?? 'Not calculated' }}</td>
                <td class="right">{{ $invoice->adjustment_total }}</td>
                <td></td>
                <td class="right">{{ $invoice->final_total ?? 'Not calculated' }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <p class="note">{{ $invoice->rate_notice ?: 'The VAT percentage on this document was entered by the issuer. It was not selected or certified by this system.' }}</p>
    @if ($invoice->customer_notes)
        <p class="note">{{ $invoice->customer_notes }}</p>
    @endif
    @php
        $source = is_array($invoice->source_snapshot) ? $invoice->source_snapshot : [];
        $sourceNumber = is_string($source['commercial_invoice_number'] ?? null) ? $source['commercial_invoice_number'] : $invoice->commercialInvoice?->displayNumber();
        $challanNumbers = is_array($source['challan_numbers'] ?? null) ? $source['challan_numbers'] : [];
    @endphp
    <p class="note">Source: commercial invoice {{ $sourceNumber }}@if ($challanNumbers !== []) · challans {{ implode(', ', $challanNumbers) }}@endif.</p>
    <p class="note" style="margin-top: 16px;">Name: {{ $invoice->issuedBy?->name }}<br>Designation: {{ $invoice->prepared_by_designation ?: '—' }}<br>Signature and seal: ____________________</p>
    <p class="note">This print follows the field arrangement of the owner-confirmed Mushak 6.3 file. It is not a certification that the percentage or the treatment is the one required by law.</p>
</body>
</html>
