<x-layouts.app :title="$contact ? 'Edit contact' : 'New contact'">
    <h1 class="text-xl font-semibold">{{ $contact ? 'Edit contact' : 'New contact' }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $party->legal_name }}</p>

    <form method="POST" action="{{ $contact ? route('parties.contacts.update', [$party, $contact]) : route('parties.contacts.store', $party) }}" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($contact) @method('PUT') @endif
        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The contact could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium">Contact name</label>
                <input id="name" name="name" required value="{{ old('name', $contact?->name) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="designation" class="block text-sm font-medium">Designation</label>
                <input id="designation" name="designation" value="{{ old('designation', $contact?->designation) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="department" class="block text-sm font-medium">Department</label>
                <input id="department" name="department" value="{{ old('department', $contact?->department) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $contact?->email) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $contact?->phone) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="alternative_phone" class="block text-sm font-medium">Alternative phone</label>
                <input id="alternative_phone" name="alternative_phone" value="{{ old('alternative_phone', $contact?->alternative_phone) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="whatsapp_number" class="block text-sm font-medium">WhatsApp number</label>
                <input id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $contact?->whatsapp_number) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="block text-sm font-medium">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('notes', $contact?->notes) }}</textarea>
            </div>
        </div>
        @php
            $active = (bool) old('is_active', $contact?->is_active ?? true);
            $primary = (bool) old('is_primary', $contact?->is_primary ?? $suggestPrimary);
        @endphp
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Use</legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($active)> Active</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_primary" value="1" @checked($primary)> Primary contact</label>
        </fieldset>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save contact</button>
    </form>

    @if ($contact?->is_active)
        <form method="POST" action="{{ route('parties.contacts.archive', [$party, $contact]) }}" class="mt-8 max-w-3xl space-y-3 rounded border border-line bg-white px-4 py-3">
            @csrf
            <h2 class="text-sm font-medium">Archive this contact</h2>
            <p class="text-sm text-muted">Archiving keeps the contact for history and removes it as the primary contact.</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_archive" value="1" required> I understand this contact will be archived</label>
            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Archive contact</button>
        </form>
    @endif
</x-layouts.app>
