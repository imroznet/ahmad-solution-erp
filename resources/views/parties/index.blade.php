<x-layouts.app :title="$title">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $title }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">
                @if ($fixedRole === 'customer')
                    Customers are party records with the customer role. The same organisation can also be a supplier without a second identity.
                @elseif ($fixedRole === 'supplier')
                    Suppliers are party records with the supplier role. Adding a customer role keeps this record.
                @else
                    Each organisation is listed once. Role badges show whether it is a customer, a supplier, or both.
                @endif
            </p>
        </div>
        @if ($fixedRole === 'customer' && ($currentUser?->hasPermission('customers.manage') || $currentUser?->hasPermission('parties.create')))
            <a href="{{ route('customers.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New customer</a>
        @elseif ($fixedRole === 'supplier' && ($currentUser?->hasPermission('suppliers.manage') || $currentUser?->hasPermission('parties.create')))
            <a href="{{ route('suppliers.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New supplier</a>
        @elseif ($fixedRole === null && $currentUser?->hasPermission('parties.create'))
            <a href="{{ route('parties.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New party</a>
        @endif
    </div>

    <form method="GET" action="{{ route($listRoute) }}" class="mt-4 grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2">
            <label for="search" class="block text-sm font-medium">Search</label>
            <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Name, phone, email, TIN, or BIN" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium">Status</label>
            <select id="status" name="status" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived', 'all' => 'All'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($fixedRole === null)
            <div>
                <label for="role" class="block text-sm font-medium">Role</label>
                <select id="role" name="role" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    <option value="" @selected($filters['role'] === '')>All roles</option>
                    <option value="customer" @selected($filters['role'] === 'customer')>Customer</option>
                    <option value="supplier" @selected($filters['role'] === 'supplier')>Supplier</option>
                </select>
            </div>
        @endif
        <div>
            <label for="district" class="block text-sm font-medium">District</label>
            <input id="district" name="district" value="{{ $filters['district'] }}" list="district-options" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <datalist id="district-options">
                @foreach ($districts as $district)
                    <option value="{{ $district }}"></option>
                @endforeach
            </datalist>
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Apply</button>
        </div>
    </form>

    @php
        $sortLink = function (string $column) use ($filters, $listRoute): string {
            $direction = $filters['sort'] === $column && $filters['direction'] === 'asc' ? 'desc' : 'asc';

            return route($listRoute, array_merge($filters, ['sort' => $column, 'direction' => $direction]));
        };
        $nameHeading = $fixedRole === 'supplier' ? 'Supplier name' : ($fixedRole === 'customer' ? 'Customer name' : 'Name');
    @endphp

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium"><a href="{{ $sortLink('legal_name') }}" class="hover:text-ink">{{ $nameHeading }}</a></th>
                    <th class="px-3 py-2 font-medium">Primary contact</th>
                    <th class="px-3 py-2 font-medium">Phone</th>
                    <th class="px-3 py-2 font-medium">Email</th>
                    <th class="px-3 py-2 font-medium">BIN</th>
                    <th class="px-3 py-2 font-medium"><a href="{{ $sortLink('district') }}" class="hover:text-ink">District</a></th>
                    <th class="px-3 py-2 font-medium"><a href="{{ $sortLink('status') }}" class="hover:text-ink">Status</a></th>
                    <th class="px-3 py-2 font-medium"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($parties as $party)
                    @php
                        $contact = $party->primaryContact;
                        $phone = $contact?->phone ?: $party->phone;
                        $email = $contact?->email ?: $party->email;
                    @endphp
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2">
                            <a href="{{ route('parties.show', $party) }}" class="font-medium text-brand hover:text-brand-dark">{{ $party->legal_name }}</a>
                            @if ($party->trading_name && $party->trading_name !== $party->legal_name)
                                <span class="mt-0.5 block text-muted">{{ $party->trading_name }}</span>
                            @endif
                            @if ($fixedRole === null)
                                <span class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($party->roles as $role)
                                        <span class="rounded bg-brand/10 px-1.5 py-0.5 text-xs font-medium text-brand">{{ $role->role === 'supplier' ? 'Supplier' : 'Customer' }}</span>
                                    @endforeach
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $contact?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $phone ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $email ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $party->bin ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $party->list_district ?: '—' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'rounded px-1.5 py-0.5 text-xs font-medium',
                                'bg-brand/10 text-brand' => $party->status === 'active',
                                'bg-amber-50 text-amber-800' => $party->status === 'inactive',
                                'bg-stone-100 text-stone-700' => $party->status === 'archived',
                            ])>{{ $party->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('parties.show', $party) }}" class="font-medium text-brand hover:text-brand-dark">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-muted">No parties match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $parties->links() }}</div>
</x-layouts.app>
