<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2933; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .notice { border: 1px solid #92400e; background: #fffbeb; padding: 8px; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d9e2ec; padding: 4px 6px; text-align: left; vertical-align: top; }
        dt { color: #627d98; }
        dd { margin: 0 0 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>Ahmad Solution Ltd. Internal working paper. Not for use as a filed tax document.</p>
    <p class="notice">{{ $notice }}</p>
    <dl>
        @foreach ($lines as $label => $value)
            <dt>{{ $label }}</dt>
            <dd>{{ $value !== '' ? $value : '—' }}</dd>
        @endforeach
    </dl>
    @if ($table !== [])
        <table>
            <thead>
                <tr>
                    <th>Line</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Tax-exclusive total (BDT)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($table as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p>Supplementary duty and VAT columns from the linked form file are blank. No statutory rate is applied on this paper.</p>
    @endif
    <h2>Why this cannot be issued</h2>
    <ul>
        @foreach ($blockers as $blocker)
            <li>{{ $blocker }}</li>
        @endforeach
    </ul>
</body>
</html>
