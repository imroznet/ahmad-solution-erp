<x-layouts.app title="Edit company">
    <h1 class="text-xl font-semibold">{{ $profile ? 'Edit company profile' : 'Create company profile' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">TIN and BIN are stored as printed on the certificate. This form does not check an NBR checksum. The financial year suggestion is the common July–June period and can be changed.</p>

    <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The profile could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="legal_name" class="block text-sm font-medium">Legal company name</label>
                <input id="legal_name" name="legal_name" value="{{ old('legal_name', $profile->legal_name ?? config('asl.company.legal_name')) }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="trading_name" class="block text-sm font-medium">Brand / trading name</label>
                <input id="trading_name" name="trading_name" value="{{ old('trading_name', $profile?->trading_name ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="business_type" class="block text-sm font-medium">Business type</label>
                <input id="business_type" name="business_type" value="{{ old('business_type', $profile?->business_type ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="rjsc_registration_number" class="block text-sm font-medium">RJSC registration number</label>
                <input id="rjsc_registration_number" name="rjsc_registration_number" value="{{ old('rjsc_registration_number', $profile?->rjsc_registration_number ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="incorporation_date" class="block text-sm font-medium">Incorporation date</label>
                <input id="incorporation_date" name="incorporation_date" type="date" value="{{ old('incorporation_date', $profile?->incorporation_date?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="tin" class="block text-sm font-medium">TIN</label>
                <input id="tin" name="tin" value="{{ old('tin', $profile?->tin ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="bin" class="block text-sm font-medium">BIN</label>
                <input id="bin" name="bin" value="{{ old('bin', $profile?->bin ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="trade_licence_number" class="block text-sm font-medium">Trade licence number</label>
                <input id="trade_licence_number" name="trade_licence_number" value="{{ old('trade_licence_number', $profile?->trade_licence_number ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="trade_licence_issuing_authority" class="block text-sm font-medium">Trade licence issuing authority</label>
                <input id="trade_licence_issuing_authority" name="trade_licence_issuing_authority" value="{{ old('trade_licence_issuing_authority', $profile?->trade_licence_issuing_authority ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="trade_licence_issued_on" class="block text-sm font-medium">Trade licence issue date</label>
                <input id="trade_licence_issued_on" name="trade_licence_issued_on" type="date" value="{{ old('trade_licence_issued_on', $profile?->trade_licence_issued_on?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="trade_licence_expires_on" class="block text-sm font-medium">Trade licence expiry date</label>
                <input id="trade_licence_expires_on" name="trade_licence_expires_on" type="date" value="{{ old('trade_licence_expires_on', $profile?->trade_licence_expires_on?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="financial_year_starts_on" class="block text-sm font-medium">Financial year start</label>
                <input id="financial_year_starts_on" name="financial_year_starts_on" type="date" required value="{{ old('financial_year_starts_on', $profile?->financial_year_starts_on?->toDateString() ?? $suggestedYear['starts']) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="financial_year_ends_on" class="block text-sm font-medium">Financial year end</label>
                <input id="financial_year_ends_on" name="financial_year_ends_on" type="date" required value="{{ old('financial_year_ends_on', $profile?->financial_year_ends_on?->toDateString() ?? $suggestedYear['ends']) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="currency_code" class="block text-sm font-medium">Default currency</label>
                <select id="currency_code" name="currency_code" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    <option value="BDT" @selected(old('currency_code', $profile?->currency_code ?? 'BDT') === 'BDT')>BDT</option>
                </select>
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium">Official phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $profile?->phone ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium">Official email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $profile?->email ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="website" class="block text-sm font-medium">Website</label>
                <input id="website" name="website" type="url" value="{{ old('website', $profile?->website ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="block text-sm font-medium">Company description</label>
                <textarea id="description" name="description" rows="4" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('description', $profile?->description ?? '') }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="logo" class="block text-sm font-medium">Company logo</label>
                <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="mt-1 block w-full text-sm">
                <p class="mt-1 text-xs text-muted">JPG or PNG. Stored privately and shown only to signed-in users who can view the company.</p>
                @if ($profile?->logo_path)
                    <label class="mt-2 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))>
                        Remove the current logo
                    </label>
                @endif
            </div>
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save profile</button>
    </form>
</x-layouts.app>
