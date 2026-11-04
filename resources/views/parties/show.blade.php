<x-layouts.app :title="$party->legal_name">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $party->legal_name }}</h1>
            @if ($party->trading_name)
                <p class="mt-1 text-sm text-muted">{{ $party->trading_name }}</p>
            @endif
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($visibleRoles as $role)
                    <span class="rounded bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand">{{ $role === 'supplier' ? 'Supplier' : 'Customer' }}</span>
                @endforeach
                <span class="rounded bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">{{ $party->statusLabel() }}</span>
                <span class="rounded bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">{{ $party->kindLabel() }}</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (in_array('customer', $visibleRoles, true) && $currentUser?->hasPermission('quotations.view'))
                <a href="{{ route('quotations.index', ['party' => $party->id]) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Quotations</a>
            @endif
            @if ($canUpdate)
                <a href="{{ route('parties.edit', $party) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Edit</a>
            @endif
        </div>
    </div>

    <dl class="mt-6 grid gap-4 rounded border border-line bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Legal name in Bangla</dt><dd>{{ $party->legal_name_bn ?: '—' }}</dd></div>
        <div><dt class="text-muted">Trading name in Bangla</dt><dd>{{ $party->trading_name_bn ?: '—' }}</dd></div>
        <div><dt class="text-muted">Registration number</dt><dd>{{ $party->registration_number ?: '—' }}</dd></div>
        <div><dt class="text-muted">TIN</dt><dd>{{ $party->tin ?: '—' }}</dd></div>
        <div><dt class="text-muted">BIN</dt><dd>{{ $party->bin ?: '—' }}</dd></div>
        <div><dt class="text-muted">VAT registration number</dt><dd>{{ $party->vat_registration_number ?: '—' }}</dd></div>
        <div><dt class="text-muted">Phone</dt><dd>{{ $party->phone ?: '—' }}</dd></div>
        <div><dt class="text-muted">Email</dt><dd>{{ $party->email ?: '—' }}</dd></div>
        <div><dt class="text-muted">Website</dt><dd>{{ $party->website ?: '—' }}</dd></div>
        <div><dt class="text-muted">Currency</dt><dd>{{ $party->currency_code }}</dd></div>
        @if ($party->notes)
            <div class="sm:col-span-2"><dt class="text-muted">Notes</dt><dd class="whitespace-pre-wrap">{{ $party->notes }}</dd></div>
        @endif
    </dl>

    <section class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-base font-semibold">Contacts</h2>
            @if ($canManageContacts)
                <a href="{{ route('parties.contacts.create', $party) }}" class="text-sm font-medium text-brand hover:text-brand-dark">Add contact</a>
            @endif
        </div>
        <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Designation</th>
                        <th class="px-3 py-2 font-medium">Phone</th>
                        <th class="px-3 py-2 font-medium">Email</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($party->contacts as $contact)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-3 py-2">{{ $contact->name }} @if ($contact->is_primary && $contact->is_active)<span class="text-xs text-brand">Primary</span>@endif</td>
                            <td class="px-3 py-2">{{ $contact->designation ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $contact->phone ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $contact->email ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $contact->is_active ? 'Active' : 'Archived' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($canManageContacts && $contact->is_active)
                                    <a href="{{ route('parties.contacts.edit', [$party, $contact]) }}" class="font-medium text-brand hover:text-brand-dark">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-muted">No contacts have been saved.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Addresses</h2>
                <p class="mt-1 text-sm text-muted">Closing an address keeps the row. One active address can be the billing default, and one can be the shipping default.</p>
            </div>
            @if ($canManageAddresses)
                <a href="{{ route('parties.addresses.create', $party) }}" class="text-sm font-medium text-brand hover:text-brand-dark">Add address</a>
            @endif
        </div>
        <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Address</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Defaults</th>
                        <th class="px-3 py-2 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($party->addresses as $address)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-3 py-2">{{ $address->typeLabel() }}</td>
                            <td class="px-3 py-2">{{ implode(', ', $address->lines()) }}</td>
                            <td class="px-3 py-2">{{ $address->is_active ? 'Active' : 'Closed' }}</td>
                            <td class="px-3 py-2">
                                {{ $address->is_default_billing ? 'Billing' : '' }}
                                {{ $address->is_default_shipping ? 'Shipping' : '' }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if ($canManageAddresses)
                                    <a href="{{ route('parties.addresses.edit', [$party, $address]) }}" class="font-medium text-brand hover:text-brand-dark">Edit</a>
                                    @if ($address->is_active)
                                        <a href="{{ route('parties.addresses.create', [$party, 'supersedes' => $address->id]) }}" class="ml-3 font-medium text-brand hover:text-brand-dark">Replace</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-muted">No addresses have been saved.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canArchive)
        <form method="POST" action="{{ route('parties.archive', $party) }}" class="mt-8 max-w-3xl space-y-3 rounded border border-line bg-white px-4 py-3">
            @csrf
            @if ($party->isArchived())
                <h2 class="text-sm font-medium">Reactivate this party</h2>
                <p class="text-sm text-muted">Reactivation makes the record editable again. It does not create a new party.</p>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_reactivate" value="1" required> I understand this party will become active</label>
                <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Reactivate party</button>
            @else
                <h2 class="text-sm font-medium">Archive this party</h2>
                <p class="text-sm text-muted">Archiving keeps the record for later documents and blocks edits until it is reactivated.</p>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_archive" value="1" required> I understand this party will be archived</label>
                <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Archive party</button>
            @endif
        </form>
    @endif
</x-layouts.app>
