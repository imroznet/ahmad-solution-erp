<x-layouts.app title="Roles">
    <h1 class="text-xl font-semibold">Roles</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Permission names are registered in code. A super administrator can change which permissions each role grants, except the super administrator role itself.</p>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Role</th>
                    <th class="px-3 py-2 font-medium">Permissions</th>
                    <th class="px-3 py-2 font-medium"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2">
                            <span class="font-medium">{{ $role->name }}</span>
                            <span class="block text-muted">{{ $role->description }}</span>
                        </td>
                        <td class="px-3 py-2">{{ $role->permissions->pluck('name')->join(', ') }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($currentUser?->hasPermission('roles.manage'))
                                <a href="{{ route('roles.edit', $role) }}" class="font-medium text-brand hover:text-brand-dark">{{ $role->slug === 'super-administrator' ? 'View' : 'Edit' }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
