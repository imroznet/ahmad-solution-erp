@php
    $map = \App\Tax\MushakFieldMap::class;
    $references = is_array($document->source_references) ? $document->source_references : [];
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>{{ $document->displayNumber() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 14px; text-align: center; margin: 0; }
        h2 { font-size: 12px; text-align: center; margin: 2px 0; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #222; padding: 3px; vertical-align: top; }
        th { font-size: 8px; }
        .right { text-align: right; }
        .muted { color: #333; }
        .box { margin-top: 10px; border-top: 1px solid #222; padding-top: 6px; }
        .en { display: block; color: #333; font-size: 8px; }
    </style>
</head>
<body>
    <p class="right"><strong>{{ $map::FORM_CODE }}</strong><span class="en">form_code</span></p>
    <h1>{{ $map::FORM_AUTHORITY }}</h1>
    <h2>{{ $map::FORM_BOARD }}</h2>
    <h2>{{ $map::FORM_TITLE }}</h2>
    <p style="text-align: center;">{{ $map::LEGAL_CITATION }}</p>
    @if ($document->stringAttribute('status') === 'draft')
        <p style="text-align: center;"><strong>DRAFT</strong></p>
    @elseif ($document->stringAttribute('status') === 'voided')
        <p style="text-align: center;"><strong>VOIDED</strong></p>
    @endif

    <p><strong>{{ $map::SELLER_NAME }}</strong> <span class="en">seller_name</span><br>{{ $company['legal_name'] }}</p>
    <p><strong>{{ $map::SELLER_BIN }}</strong> <span class="en">seller_bin</span><br>{{ $company['bin'] ?: '—' }}</p>
    <p><strong>{{ $map::SELLER_ADDRESS }}</strong> <span class="en">seller_issue_address</span><br>
        @forelse ($company['address'] as $line)
            {{ $line }}<br>
        @empty
            —
        @endforelse
        @if ($company['phone'] || $company['email'])
            <span class="en">seller_contact</span><br>
            {{ $company['phone'] }} {{ $company['email'] }}
        @endif
    </p>

    <table>
        <tr>
            <td>
                <strong>{{ $map::BUYER_NAME }}</strong> <span class="en">buyer_name</span><br>{{ $document->buyer_name }}<br>
                <strong>{{ $map::BUYER_BIN }}</strong> <span class="en">buyer_bin</span><br>{{ $document->buyer_bin ?: '—' }}<br>
                <strong>{{ $map::DESTINATION }}</strong> <span class="en">supply_destination</span><br>{{ $document->destination }}
            </td>
            <td>
                <strong>{{ $map::CHALLAN_NUMBER }}</strong> <span class="en">challan_number</span><br>{{ $document->number ?: '—' }}<br>
                <strong>{{ $map::ISSUE_DATE }}</strong> <span class="en">issue_date</span><br>{{ $document->issue_date?->format('Y-m-d') ?: '—' }}<br>
                <strong>{{ $map::ISSUE_TIME }}</strong> <span class="en">issue_time</span><br>{{ $document->issue_time ?: '—' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>{{ $map::COLUMN_SERIAL }}<span class="en">line.serial</span></th>
                <th>{{ $map::COLUMN_DESCRIPTION }}<span class="en">line.description</span></th>
                <th>{{ $map::COLUMN_UNIT }}<span class="en">line.unit</span></th>
                <th>{{ $map::COLUMN_QUANTITY }}<span class="en">line.quantity</span></th>
                <th>{{ $map::COLUMN_UNIT_PRICE }}<span class="en">line.unit_price</span></th>
                <th>{{ $map::COLUMN_LINE_VALUE }}<span class="en">line.line_value</span></th>
                <th>{{ $map::COLUMN_SD }}<span class="en">line.supplementary_duty</span></th>
                <th>{{ $map::COLUMN_VAT_RATE }}<span class="en">line.vat_rate</span></th>
                <th>{{ $map::COLUMN_VAT_AMOUNT }}<span class="en">line.vat_amount</span></th>
                <th>{{ $map::COLUMN_INCLUSIVE }}<span class="en">line.inclusive_value</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->lines as $line)
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->text('description') }}@if ($line->text('specific_tax_note') !== '')<br>{{ $line->text('specific_tax_note') }}@endif</td>
                    <td>{{ $line->text('unit') }}</td>
                    <td class="right">{{ $line->nullableDecimal('quantity') }}</td>
                    <td class="right">{{ $line->nullableDecimal('unit_price') }}</td>
                    <td class="right">{{ $line->nullableDecimal('line_value') }}</td>
                    <td class="right">{{ $line->nullableDecimal('supplementary_duty') }}</td>
                    <td class="right">{{ $line->nullableDecimal('vat_rate') }}</td>
                    <td class="right">{{ $line->nullableDecimal('vat_amount') }}</td>
                    <td class="right">{{ $line->nullableDecimal('inclusive_value') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5">{{ $map::TOTAL_LABEL }}</td>
                <td class="right">{{ $document->nullableDecimal('total_value') }}</td>
                <td class="right">{{ $document->nullableDecimal('total_supplementary_duty') }}</td>
                <td></td>
                <td class="right">{{ $document->nullableDecimal('total_vat') }}</td>
                <td class="right">{{ $document->nullableDecimal('total_inclusive') }}</td>
            </tr>
        </tbody>
    </table>
    <p>{{ $map::VALUE_FOOTNOTE }}</p>

    <p><strong>{{ $map::RESPONSIBLE_NAME }}</strong> <span class="en">responsible_name</span><br>{{ $document->responsible_name }}</p>
    <p><strong>{{ $map::RESPONSIBLE_DESIGNATION }}</strong> <span class="en">responsible_designation</span><br>{{ $document->responsible_designation }}</p>
    <p><strong>{{ $map::SIGNATURE }}</strong> <span class="en">signature</span><br>______________________________</p>
    <p><strong>{{ $map::SEAL }}</strong> <span class="en">seal</span><br>______________________________</p>
    <p>{{ $map::SOURCE_DEDUCTION_NOTE }}</p>

    <div class="box">
        <p><strong>ERP reference</strong></p>
        <p>Copy: {{ $copy }}</p>
        <p>Tax review: {{ $document->reviewLabel() }}</p>
        <p>{{ $document->manual_vat_notice }}</p>
        <p>Commercial invoice: {{ $references['commercial_invoice_number'] ?? '—' }}</p>
        <p>Quotation: {{ $references['quotation_number'] ?? '—' }}</p>
        <p>Delivery challans: {{ isset($references['challan_numbers']) && is_array($references['challan_numbers']) && $references['challan_numbers'] !== [] ? implode(', ', $references['challan_numbers']) : '—' }}</p>
        <p>Layout file checked 6 November 2026: {{ $map::SOURCE_URL }}</p>
        <p>SHA-256 {{ $map::SOURCE_SHA256 }}</p>
        <p>SRO No-125-Ain/2026/330-Mushak's effect on this layout was not verified from the gazette text. VDS and AIT are not included in the VAT amount.</p>
    </div>
</body>
</html>
