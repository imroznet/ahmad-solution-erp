<x-layouts.app title="Edit user">
    <h1 class="text-xl font-semibold">Edit {{ $user->name }}</h1>
    @if ($editingSelf)
        <p class="mt-1 text-sm text-muted">You can change your own name, email, and password. Another super administrator must change your roles or status.</p>
    @endif
    <form method="POST" action="{{ route('users.update', $user) }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="block text-sm font-medium">Name</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('name')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('email')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">Leave blank to keep the current password.</p>
            @error('password')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <fieldset>
            <legend class="text-sm font-medium">Roles</legend>
            <div class="mt-2 space-y-2">
                @if ($editingSelf)
                    @foreach ($user->roles as $role)
                        <input type="hidden" name="roles[]" value="{{ $role->id }}">
                        <p class="text-sm">{{ $role->name }}</p>
                    @endforeach
                @else
                    @foreach ($roles as $role)
                        @continue($role->slug === 'super-administrator' && ! $currentUser?->isSuperAdministrator())
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $user->roles->pluck('id')->all()))) class="mt-0.5">
                            <span>
                                <span class="font-medium">{{ $role->name }}</span>
                                <span class="block text-muted">{{ $role->description }}</span>
                            </span>
                        </label>
                    @endforeach
                @endif
            </div>
            @error('roles')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </fieldset>
        @if (! $editingSelf && $currentUser?->hasPermission('users.deactivate'))
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                Account is active
            </label>
        @endif
        <div class="flex gap-3">
            <button type="submit" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save changes</button>
            <a href="{{ route('users.index') }}" class="rounded border border-line px-4 py-2 text-sm">Cancel</a>
        </div>
    </form>
</x-layouts.app>
