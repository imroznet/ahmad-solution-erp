<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $challan->displayNumber() }}</title>
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
                @if ($challan->stringAttribute('status') === 'draft')
                    <p class="banner"><strong>DRAFT</strong></p>
                @elseif ($challan->stringAttribute('status') === 'voided')
                    <p class="banner"><strong>VOIDED</strong></p>
                @endif
                <h2>Delivery challan</h2>
                <p>{{ $challan->displayNumber() }}</p>
                <p>{{ $challan->challan_date?->format('d M Y') }}</p>
                <p class="muted">{{ $challan->quotation?->number }}</p>
            </td>
        </tr>
    </table>

    <h2>{{ $challan->party_legal_name }}</h2>
    @if ($challan->party_trading_name)<p>{{ $challan->party_trading_name }}</p>@endif
    @if ($challan->contact_name)<p>{{ $challan->contact_name }}</p>@endif
    @foreach ($challan->addressLines('shipping_address') as $line)<p>{{ $line }}</p>@endforeach
    @if ($challan->delivery_location)<p>Location: {{ $challan->delivery_location }}</p>@endif
    @if ($challan->recipient_name)<p>Recipient: {{ $challan->recipient_name }}</p>@endif
    @if ($challan->vehicle_or_carrier)<p>Carrier: {{ $challan->vehicle_or_carrier }}</p>@endif

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Unit</th>
                <th class="right">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($challan->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="right">{{ $line->quantityLabel() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="notice">Dispatched: {{ $challan->dispatched_at ? $challan->dispatched_at->timezone('Asia/Dhaka')->format('d M Y H:i') : 'not recorded' }}</p>
    <p>Acknowledged: {{ $challan->acknowledged_at ? $challan->acknowledged_at->timezone('Asia/Dhaka')->format('d M Y H:i').' · '.$challan->acknowledgment_name : 'not recorded' }}</p>
    @if ($challan->customer_notes)<p class="notice">{{ $challan->customer_notes }}</p>@endif
    <p class="notice">{{ $notice }}</p>
</body>
</html>
