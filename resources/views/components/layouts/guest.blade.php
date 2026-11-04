@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Sign in' }} — {{ config('asl.company.legal_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <div class="mb-8 flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded bg-brand text-sm font-semibold text-white">AS</span>
            <span>
                <span class="block text-base font-semibold">{{ config('asl.company.legal_name') }}</span>
                <span class="block text-sm text-muted">Business management</span>
            </span>
        </div>
        <div class="rounded border border-line bg-white p-6">
            @if (session('status'))
                <p class="mb-4 rounded border border-line bg-canvas px-3 py-2 text-sm" role="status">{{ session('status') }}</p>
            @endif
            {{ $slot }}
        </div>
    </main>
</body>
</html>
