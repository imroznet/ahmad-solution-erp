<x-layouts.app title="Users">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Users</h1>
            <p class="mt-1 text-sm text-muted">Accounts are created by an administrator. Public registration is closed.</p>
        </div>
        @if ($currentUser?->hasPermission('users.create'))
            <a href="{{ route('users.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New user</a>
        @endif
    </div>
    <form method="GET" action="{{ route('users.index') }}" class="mt-4 flex gap-2">
        <label for="search" class="sr-only">Search users</label>
        <input id="search" name="search" value="{{ $search }}" placeholder="Search name or email" class="w-full max-w-sm rounded border border-line bg-white px-3 py-2 text-sm">
        <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Search</button>
    </form>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Name</th>
                    <th class="px-3 py-2 font-medium">Email</th>
                    <th class="px-3 py-2 font-medium">Roles</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2">{{ $user->name }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td class="px-3 py-2">{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($currentUser?->hasPermission('users.update'))
                                <a href="{{ route('users.edit', $user) }}" class="font-medium text-brand hover:text-brand-dark">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-muted">No users match this search.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
