<x-layouts.app :title="$address ? 'Edit address' : 'New address'">
    <h1 class="text-xl font-semibold">{{ $address ? 'Edit address' : ($supersedes ? 'Replace address' : 'New address') }}</h1>
    @if ($supersedes)
        <p class="mt-1 max-w-3xl text-sm text-muted">Saving closes {{ $supersedes->typeLabel() }} at {{ $supersedes->line_1 }} and keeps that row. The new row is a separate address.</p>
    @endif

    <form method="POST" action="{{ $address ? route('company.addresses.update', $address) : route('company.addresses.store') }}" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($address) @method('PUT') @endif
        @if ($supersedes)
            <input type="hidden" name="supersedes_address_id" value="{{ $supersedes->id }}">
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The address could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="address_type" class="block text-sm font-medium">Address type</label>
                <select id="address_type" name="address_type" required class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('address_type', $address->address_type ?? $supersedes->address_type ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="country" class="block text-sm font-medium">Country</label>
                <input id="country" name="country" required value="{{ old('country', $address->country ?? $supersedes->country ?? 'Bangladesh') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="line_1" class="block text-sm font-medium">Address line 1</label>
                <input id="line_1" name="line_1" required value="{{ old('line_1', $address?->line_1 ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="line_2" class="block text-sm font-medium">Address line 2</label>
                <input id="line_2" name="line_2" value="{{ old('line_2', $address?->line_2 ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="area" class="block text-sm font-medium">Area / locality</label>
                <input id="area" name="area" value="{{ old('area', $address?->area ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="upazila" class="block text-sm font-medium">Upazila</label>
                <input id="upazila" name="upazila" value="{{ old('upazila', $address?->upazila ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="district" class="block text-sm font-medium">District</label>
                <input id="district" name="district" required value="{{ old('district', $address?->district ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="division" class="block text-sm font-medium">Division</label>
                <input id="division" name="division" value="{{ old('division', $address?->division ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="postcode" class="block text-sm font-medium">Postcode</label>
                <input id="postcode" name="postcode" value="{{ old('postcode', $address?->postcode ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="effective_from" class="block text-sm font-medium">Effective from</label>
                <input id="effective_from" name="effective_from" type="date" value="{{ old('effective_from', $address?->effective_from?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="effective_until" class="block text-sm font-medium">Effective until</label>
                <input id="effective_until" name="effective_until" type="date" value="{{ old('effective_until', $address?->effective_until?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="remarks" class="block text-sm font-medium">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('remarks', $address?->remarks ?? '') }}</textarea>
            </div>
        </div>

        @php
            $active = (bool) old('is_active', $address?->is_active ?? true);
            $correspondence = (bool) old('is_default_correspondence', $address?->is_default_correspondence ?? false);
            $billing = (bool) old('is_default_billing', $address?->is_default_billing ?? false);
        @endphp
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Use</legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($active)> Active</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default_correspondence" value="1" @checked($correspondence)> Default correspondence address</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default_billing" value="1" @checked($billing)> Default billing address</label>
        </fieldset>

        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save address</button>
    </form>

    @if ($address?->is_active)
        <form method="POST" action="{{ route('company.addresses.close', $address) }}" class="mt-8 max-w-3xl space-y-3 rounded border border-line bg-white px-4 py-3">
            @csrf
            <h2 class="text-sm font-medium">Close this address</h2>
            <p class="text-sm text-muted">Closing keeps the row and removes it as a default. It does not delete the history.</p>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="confirm_close" value="1" required>
                I understand this address will be closed
            </label>
            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Close address</button>
        </form>
    @endif
</x-layouts.app>
