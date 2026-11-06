<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        .box { border: 1px solid #222; padding: 8px; margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Internal {{ $title }} record</h1>
    <p>This is not an NBR certificate, not Mushak 6.6, and not a withholding-tax certificate. The amount was entered. It was not calculated from a rate and it is not output VAT.</p>
    <p>{{ $record->directionLabel() }}</p>
    <p>Other party: {{ $record->counterparty_name }}</p>
    <p>BIN: {{ $record->counterparty_bin }} · TIN: {{ $record->counterparty_tin }}</p>
    <p>Date: {{ $record->record_date?->format('d M Y') }}</p>
    <p>Taxable basis: {{ $record->taxable_basis }}</p>
    <p>Deducted amount: {{ $record->deducted_amount }}</p>
    <p>Certificate reference: {{ $record->certificate_reference }}</p>
    <p>Treasury reference: {{ $record->treasury_reference }}</p>
    <div class="box">Certificate form status: unverified. Do not file this page as a statutory certificate.</div>
</body>
</html>
