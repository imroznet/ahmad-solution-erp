<x-layouts.app title="Addresses">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Addresses</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Registered office and corporate/head office are stored separately. Closing an address keeps it for later documents. Only one correspondence default and one billing default can be active.</p>
        </div>
        <a href="{{ route('company.addresses.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New address</a>
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
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
                @forelse ($addresses as $address)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2">{{ $address->typeLabel() }}</td>
                        <td class="px-3 py-2">{{ implode(', ', $address->lines()) }}</td>
                        <td class="px-3 py-2">{{ $address->is_active ? 'Active' : 'Closed' }}</td>
                        <td class="px-3 py-2">
                            {{ $address->is_default_correspondence ? 'Correspondence' : '' }}
                            {{ $address->is_default_billing ? 'Billing' : '' }}
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('company.addresses.edit', $address) }}" class="font-medium text-brand hover:text-brand-dark">Edit</a>
                            @if ($address->is_active)
                                <a href="{{ route('company.addresses.create', ['supersedes' => $address->id]) }}" class="ml-3 font-medium text-brand hover:text-brand-dark">Replace</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-muted">No addresses have been saved.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
