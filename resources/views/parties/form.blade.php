<x-layouts.app :title="$party ? 'Edit party' : ($mode === 'supplier' ? 'New supplier' : ($mode === 'customer' ? 'New customer' : 'New party'))">
    @php
        $selectedRoles = old('roles', $party ? $party->roles->pluck('role')->all() : ($mode === 'supplier' ? ['supplier'] : ['customer']));
        if (! is_array($selectedRoles)) {
            $selectedRoles = [];
        }
        $heading = $party ? 'Edit party' : ($mode === 'supplier' ? 'New supplier' : ($mode === 'customer' ? 'New customer' : 'New party'));
    @endphp
    <h1 class="text-xl font-semibold">{{ $heading }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">TIN and BIN are stored as entered, including leading zeros. Either identifier can be left blank. The default currency is BDT.</p>

    <form method="POST" action="{{ $party ? route('parties.update', $party) : ($mode === 'supplier' ? route('suppliers.store') : ($mode === 'customer' ? route('customers.store') : route('parties.store'))) }}" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($party) @method('PUT') @endif

        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The party could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="legal_name" class="block text-sm font-medium">Legal name</label>
                <input id="legal_name" name="legal_name" required value="{{ old('legal_name', $party?->legal_name) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="trading_name" class="block text-sm font-medium">Trading name</label>
                <input id="trading_name" name="trading_name" value="{{ old('trading_name', $party?->trading_name) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="legal_name_bn" class="block text-sm font-medium">Legal name in Bangla</label>
                <input id="legal_name_bn" name="legal_name_bn" lang="bn" value="{{ old('legal_name_bn', $party?->legal_name_bn) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="trading_name_bn" class="block text-sm font-medium">Trading name in Bangla</label>
                <input id="trading_name_bn" name="trading_name_bn" lang="bn" value="{{ old('trading_name_bn', $party?->trading_name_bn) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="party_kind" class="block text-sm font-medium">Business type</label>
                <select id="party_kind" name="party_kind" required class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}" @selected(old('party_kind', $party?->party_kind ?? 'company') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="registration_number" class="block text-sm font-medium">Registration number</label>
                <input id="registration_number" name="registration_number" value="{{ old('registration_number', $party?->registration_number) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="tin" class="block text-sm font-medium">TIN</label>
                <input id="tin" name="tin" value="{{ old('tin', $party?->tin) }}" inputmode="text" autocomplete="off" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="bin" class="block text-sm font-medium">BIN</label>
                <input id="bin" name="bin" value="{{ old('bin', $party?->bin) }}" inputmode="text" autocomplete="off" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="vat_registration_number" class="block text-sm font-medium">VAT registration number</label>
                <input id="vat_registration_number" name="vat_registration_number" value="{{ old('vat_registration_number', $party?->vat_registration_number) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $party?->phone) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $party?->email) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="website" class="block text-sm font-medium">Website</label>
                <input id="website" name="website" type="url" value="{{ old('website', $party?->website) }}" placeholder="https://" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            @if ($party)
                <div>
                    <label for="status" class="block text-sm font-medium">Status</label>
                    <select id="status" name="status" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', $party->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $party->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
            @endif
            <div class="sm:col-span-2">
                <label for="notes" class="block text-sm font-medium">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('notes', $party?->notes) }}</textarea>
            </div>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Roles</legend>
            @if (! $party && $mode === 'customer')
                <input type="hidden" name="roles[]" value="customer">
                <p class="text-sm">Customer</p>
                @if ($canAssignSupplier)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="supplier" @checked(in_array('supplier', $selectedRoles, true))> This organisation is also a supplier</label>
                @endif
            @elseif (! $party && $mode === 'supplier')
                <input type="hidden" name="roles[]" value="supplier">
                <p class="text-sm">Supplier</p>
                @if ($canAssignCustomer)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="customer" @checked(in_array('customer', $selectedRoles, true))> This organisation is also a customer</label>
                @endif
            @else
                @if ($canAssignCustomer)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="customer" @checked(in_array('customer', $selectedRoles, true))> Customer</label>
                @elseif ($party?->hasRole('customer'))
                    <input type="hidden" name="roles[]" value="customer">
                @endif
                @if ($canAssignSupplier)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="supplier" @checked(in_array('supplier', $selectedRoles, true))> Supplier</label>
                @elseif ($party?->hasRole('supplier'))
                    <input type="hidden" name="roles[]" value="supplier">
                @endif
            @endif
        </fieldset>

        @if ($errors->has('confirm_duplicate'))
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="confirm_duplicate" value="1" class="mt-1" @checked(old('confirm_duplicate'))>
                <span>I have reviewed the possible duplicate and want to save a separate record.</span>
            </label>
        @endif

        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save party</button>
    </form>
</x-layouts.app>
