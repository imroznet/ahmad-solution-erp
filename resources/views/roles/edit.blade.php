<x-layouts.app :title="$role->name">
    <h1 class="text-xl font-semibold">{{ $role->name }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $role->description }}</p>
    @if ($locked)
        <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">This role always has every permission. It cannot be reduced.</p>
    @endif
    <form method="POST" action="{{ route('roles.update', $role) }}" class="mt-6 max-w-2xl space-y-6">
        @csrf
        @method('PUT')
        @foreach ($permissionGroups as $group => $permissions)
            <fieldset>
                <legend class="text-sm font-medium">{{ $group }}</legend>
                <div class="mt-2 space-y-2">
                    @foreach ($permissions as $permission)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) @disabled($locked) class="mt-0.5">
                            <span>
                                <span class="font-medium">{{ $permission->name }}</span>
                                <span class="block text-muted">{{ $permission->description }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        @error('permissions')<p class="text-sm text-red-800">{{ $message }}</p>@enderror
        @unless ($locked)
            <button type="submit" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save permissions</button>
        @endunless
        <a href="{{ route('roles.index') }}" class="ml-3 text-sm text-brand hover:text-brand-dark">Back to roles</a>
    </form>
</x-layouts.app>
