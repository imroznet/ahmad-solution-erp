<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }} revision {{ $revision->revision_number }}</title>
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
        .logo { width: 72px; height: auto; }
        .notice { margin-top: 12px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="border: 0; width: 75%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="logo">
                @endif
                <h1>{{ $company['legal_name'] }}</h1>
                @if ($company['trading_name'])
                    <p>{{ $company['trading_name'] }}</p>
                @endif
                @foreach ($company['address'] as $line)
                    <p>{{ $line }}</p>
                @endforeach
                @if ($company['phone'])<p>{{ $company['phone'] }}</p>@endif
                @if ($company['email'])<p>{{ $company['email'] }}</p>@endif
                @if ($company['website'])<p>{{ $company['website'] }}</p>@endif
                @if ($company['tin'])<p>TIN {{ $company['tin'] }}</p>@endif
                @if ($company['bin'])<p>BIN {{ $company['bin'] }}</p>@endif
            </td>
            <td style="border: 0;" class="right">
                <h2>QUOTATION</h2>
                <p>{{ $quotation->number }}</p>
                <p>Revision {{ $revision->revision_number }}</p>
                <p>{{ $revision->statusLabel() }}</p>
                <p>Date {{ $revision->quotation_date?->format('d M Y') }}</p>
                <p>Valid until {{ $revision->valid_until?->format('d M Y') }}</p>
                <p>Currency {{ $revision->currency_code }}</p>
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

    <table>
        <tr>
            <td style="border: 0; width: 50%;">
                <h2>Billing address</h2>
                @forelse ($revision->addressLines('billing_address') as $line)
                    <p>{{ $line }}</p>
                @empty
                    <p class="muted">No billing address was recorded.</p>
                @endforelse
            </td>
            <td style="border: 0;">
                <h2>Delivery address</h2>
                @forelse ($revision->addressLines('shipping_address') as $line)
                    <p>{{ $line }}</p>
                @empty
                    <p class="muted">No delivery address was recorded.</p>
                @endforelse
            </td>
        </tr>
    </table>

    <table>
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
                    <td class="right">{{ $line->unitPriceMoney()->format() }}</td>
                    <td class="right">{{ $line->lineTotalMoney()->format() }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5" class="right"><strong>Total ({{ $revision->currency_code }})</strong></td>
                <td class="right"><strong>{{ $revision->totalMoney()->format() }}</strong></td>
            </tr>
        </tbody>
    </table>

    <p class="notice">{{ $revision->tax_notice }}</p>
    @if ($revision->terms)
        <h2 style="margin-top: 14px;">Terms</h2>
        <p>{{ $revision->terms }}</p>
    @endif
    @if ($revision->customer_notes)
        <h2 style="margin-top: 14px;">Notes</h2>
        <p>{{ $revision->customer_notes }}</p>
    @endif
</body>
</html>
