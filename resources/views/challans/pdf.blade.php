<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $challan->displayNumber() }}</title>
    @include('pdf.base')
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 68%;">
                @include('pdf.letterhead')
            </td>
            <td class="right">
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
    @if ($challan->addressText('shipping_address') !== '')<p>{{ $challan->addressText('shipping_address') }}</p>@endif
    @if ($challan->delivery_location)<p>Location: {{ $challan->delivery_location }}</p>@endif
    @if ($challan->recipient_name)<p>Recipient: {{ $challan->recipient_name }}</p>@endif
    @if ($challan->vehicle_or_carrier)<p>Carrier: {{ $challan->vehicle_or_carrier }}</p>@endif

    <table class="sheet">
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
