<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }} revision {{ $revision->revision_number }}</title>
    @include('pdf.base')
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 68%;">
                @include('pdf.letterhead')
            </td>
            <td class="right">
                <h2>QUOTATION</h2>
                <p>{{ $quotation->number }}</p>
                <p>Revision {{ $revision->revision_number }}</p>
                <p>{{ $revision->statusLabel() }}</p>
                <p>Date {{ $revision->quotation_date?->format('d M Y') }}</p>
                <p>Valid until {{ $revision->valid_until?->format('d M Y') }}</p>
                <p>{{ $revision->taxTreatmentLabel() }}</p>
            </td>
        </tr>
    </table>

    <h2>Customer</h2>
    <p>{{ $revision->party_legal_name }}</p>
    @if ($revision->party_trading_name)<p class="muted">{{ $revision->party_trading_name }}</p>@endif
    @if ($revision->party_tin)<p>TIN {{ $revision->party_tin }}</p>@endif
    @if ($revision->party_bin)<p>BIN {{ $revision->party_bin }}</p>@endif
    @if ($revision->contact_name)
        <p>{{ $revision->contact_name }}@if ($revision->contact_designation), {{ $revision->contact_designation }}@endif</p>
    @endif
    @if ($revision->contact_phone)<p>{{ $revision->contact_phone }}</p>@endif
    @if ($revision->contact_email)<p>{{ $revision->contact_email }}</p>@endif

    <table class="head">
        <tr>
            <td style="width: 50%;">
                <h2>Billing address</h2>
                @forelse ($revision->addressLines('billing_address') as $line)
                    <p>{{ $line }}</p>
                @empty
                    <p class="muted">No billing address was recorded.</p>
                @endforelse
            </td>
            <td>
                <h2>Delivery address</h2>
                @forelse ($revision->addressLines('shipping_address') as $line)
                    <p>{{ $line }}</p>
                @empty
                    <p class="muted">No delivery address was recorded.</p>
                @endforelse
            </td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>Unit</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($revision->lines as $line)
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="right">{{ $line->quantityLabel() }}</td>
                    <td class="right">{!! \App\Support\PdfMoney::html($line->unitPriceMoney()) !!}</td>
                    <td class="right">{!! \App\Support\PdfMoney::html($line->lineTotalMoney()) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sheet">
        <tbody>
            <tr>
                <td class="right">Base</td>
                <td class="right">{!! \App\Support\PdfMoney::html($revision->subtotalMoney()) !!}</td>
            </tr>
            <tr>
                <td class="right">VAT{{ $revision->typedRate('vat_rate_percent') ? ' '.$revision->typedRate('vat_rate_percent').'%' : '' }}</td>
                <td class="right">
                    @if ($revision->nullableDecimal('vat_amount') === null)
                        Not calculated
                    @else
                        {!! \App\Support\PdfMoney::html(\App\Support\Money::of($revision->nullableDecimal('vat_amount'), $revision->stringAttribute('currency_code') ?: \App\Support\Money::DEFAULT_CURRENCY)) !!}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="right">Tax{{ $revision->typedRate('tax_rate_percent') ? ' '.$revision->typedRate('tax_rate_percent').'%' : '' }}</td>
                <td class="right">
                    @if ($revision->nullableDecimal('tax_amount') === null)
                        Not calculated
                    @else
                        {!! \App\Support\PdfMoney::html(\App\Support\Money::of($revision->nullableDecimal('tax_amount'), $revision->stringAttribute('currency_code') ?: \App\Support\Money::DEFAULT_CURRENCY)) !!}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="right">Adjustment</td>
                <td class="right">
                    @if ($revision->stringAttribute('tax_treatment') !== 'included')
                        None
                    @else
                        {!! \App\Support\PdfMoney::html(\App\Support\Money::of($revision->decimalAttribute('adjustment_amount'), $revision->stringAttribute('currency_code') ?: \App\Support\Money::DEFAULT_CURRENCY)) !!}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="right"><strong>Total ({{ $revision->taxTreatmentLabel() }})</strong></td>
                <td class="right total">{!! \App\Support\PdfMoney::html($revision->totalMoney()) !!}</td>
            </tr>
        </tbody>
    </table>

    <p class="notice">{{ $revision->tax_notice }}</p>
    @if ($revision->adjustment_reason)
        <p>Adjustment reason: {{ $revision->adjustment_reason }}</p>
    @endif
    @if ($revision->terms)
        <h2 style="margin-top: 10px;">Terms</h2>
        <p>{{ $revision->terms }}</p>
    @endif
    @if ($revision->customer_notes)
        <h2 style="margin-top: 10px;">Notes</h2>
        <p>{{ $revision->customer_notes }}</p>
    @endif
</body>
</html>
