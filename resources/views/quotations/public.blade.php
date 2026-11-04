<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Quotation {{ $quotation->number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <main class="mx-auto w-full max-w-4xl px-4 py-8">
        <header class="mb-6 flex items-start gap-4">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="h-12 w-auto">
            @endif
            <div>
                <p class="text-base font-semibold">{{ $company['legal_name'] }}</p>
                @if ($company['phone'] || $company['email'])
                    <p class="text-sm text-muted">{{ $company['phone'] }} {{ $company['email'] }}</p>
                @endif
            </div>
        </header>

        @if (session('status'))
            <p class="mb-4 rounded border border-line bg-white px-3 py-2 text-sm" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($superseded)
            <p class="mb-4 rounded border border-line bg-white px-3 py-2 text-sm">This revision has been superseded. It is kept for reference and can no longer be accepted from this link.</p>
        @endif

        @include('quotations.partials.document', ['quotation' => $quotation, 'revision' => $revision])

        @if ($canRespond)
            <section class="mt-6 rounded border border-line bg-white p-4">
                <h2 class="text-base font-semibold">Respond</h2>
                <p class="mt-1 text-sm text-muted">Acceptance or rejection is recorded against revision {{ $revision->revision_number }} only.</p>
                <form method="POST" action="{{ route('quotations.public.respond', ['token' => $token]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="respondent_name" class="block text-sm font-medium">Your name</label>
                        <input id="respondent_name" name="respondent_name" required value="{{ old('respondent_name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="respondent_designation" class="block text-sm font-medium">Designation <span class="font-normal text-muted">(optional)</span></label>
                        <input id="respondent_designation" name="respondent_designation" value="{{ old('respondent_designation') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="remarks" class="block text-sm font-medium">Remarks <span class="font-normal text-muted">(optional)</span></label>
                        <textarea id="remarks" name="remarks" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="confirmed" value="1" class="mt-1" @checked(old('confirmed'))>
                            <span>I confirm this response to revision {{ $revision->revision_number }}.</span>
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <button type="submit" name="decision" value="accepted" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Accept</button>
                        <button type="submit" name="decision" value="rejected" class="rounded border border-line px-3 py-2 text-sm">Reject</button>
                    </div>
                </form>
            </section>
        @elseif ($revision->response)
            <p class="mt-4 text-sm">This revision is {{ strtolower($revision->response->decisionLabel()) }}.</p>
        @endif

        <p class="mt-6 text-sm"><a href="{{ route('quotations.public.pdf', ['token' => $token]) }}" class="font-medium text-brand">Download PDF</a></p>
    </main>
</body>
</html>
