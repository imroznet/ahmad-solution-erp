<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->displayNumber() }}</title>
    @include('pdf.base')
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 68%;">
                @include('pdf.letterhead')
            </td>
            <td class="right">
                @if ($invoice->stringAttribute('status') === 'draft')
                    <p class="banner"><strong>DRAFT</strong></p>
                @elseif ($invoice->stringAttribute('status') === 'voided')
                    <p class="banner"><strong>VOIDED</strong></p>
                @endif
                <h2>Commercial invoice</h2>
                <p>{{ $invoice->displayNumber() }}</p>
                <p>{{ $invoice->invoice_date?->format('d M Y') }}</p>
                <p class="muted">{{ $invoice->quotation?->number }}</p>
            </td>
        </tr>
    </table>

    <h2>{{ $invoice->party_legal_name }}</h2>
    @if ($invoice->party_trading_name)<p>{{ $invoice->party_trading_name }}</p>@endif
    @if ($invoice->contact_name)<p>{{ $invoice->contact_name }}</p>@endif
    @if ($invoice->addressText('billing_address') !== '')<p>{{ $invoice->addressText('billing_address') }}</p>@endif

    <table class="sheet">
        <thead>
            <tr>
                <th>Description</th>
                <th>Challan</th>
                <th class="right">Quantity</th>
                <th class="right">Rate</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}<br><span class="muted">{{ $line->unit }}</span></td>
                    <td>{{ $line->challanLine?->challan?->displayNumber() }}</td>
                    <td class="right">{{ $line->quantityLabel() }}</td>
                    <td class="right">{!! \App\Support\PdfMoney::html($line->unitPriceMoney()) !!}</td>
                    <td class="right">{!! \App\Support\PdfMoney::html($line->lineTotalMoney()) !!}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="right">Total</td>
                <td class="right">{!! \App\Support\PdfMoney::html($invoice->totalMoney()) !!}</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->customer_notes)<p class="notice">{{ $invoice->customer_notes }}</p>@endif
    <p class="notice">{{ $notice }}</p>
</body>
</html>
