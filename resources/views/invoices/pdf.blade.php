<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->displayNumber() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #17211c; font-size: 12px; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 14px; margin: 0 0 6px; }
        p { margin: 0 0 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #d5ddd8; padding: 6px 4px; vertical-align: top; text-align: left; }
        th { color: #5d6b64; font-weight: 600; }
        .right { text-align: right; }
        .muted { color: #5d6b64; }
        .header { width: 100%; margin-bottom: 16px; }
        .banner { font-size: 16px; letter-spacing: 1px; }
        .notice { margin-top: 16px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="border: 0; width: 70%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" style="width: 72px; height: auto;">
                @endif
                <h1>{{ $company['legal_name'] }}</h1>
                @if ($company['trading_name'])<p>{{ $company['trading_name'] }}</p>@endif
                @foreach ($company['address'] as $line)<p>{{ $line }}</p>@endforeach
                @if ($company['phone'])<p>{{ $company['phone'] }}</p>@endif
                @if ($company['email'])<p>{{ $company['email'] }}</p>@endif
                @if ($company['tin'])<p>TIN {{ $company['tin'] }}</p>@endif
                @if ($company['bin'])<p>BIN {{ $company['bin'] }}</p>@endif
            </td>
            <td style="border: 0; text-align: right;">
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
    @foreach ($invoice->addressLines('billing_address') as $line)<p>{{ $line }}</p>@endforeach

    <table>
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
                    <td class="right">{{ $line->unitPriceMoney()->format() }}</td>
                    <td class="right">{{ $line->lineTotalMoney()->format() }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="right">Total</td>
                <td class="right">{{ $invoice->totalMoney()->format() }}</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->customer_notes)<p class="notice">{{ $invoice->customer_notes }}</p>@endif
    <p class="notice">{{ $notice }}</p>
</body>
</html>
