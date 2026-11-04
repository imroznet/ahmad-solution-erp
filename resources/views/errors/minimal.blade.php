<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Roboto, Arial, sans-serif; background: #f4f6f5; color: #17211c; }
        main { max-width: 32rem; margin: 10vh auto; padding: 1.5rem; background: #fff; border: 1px solid #d5ddd8; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { margin: 0; line-height: 1.5; }
        a { color: #0b592f; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        @isset($action)
            <p style="margin-top: 1rem;"><a href="{{ $action['href'] }}">{{ $action['label'] }}</a></p>
        @endisset
    </main>
</body>
</html>
