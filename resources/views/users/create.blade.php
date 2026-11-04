<x-layouts.app title="New user">
    <h1 class="text-xl font-semibold">New user</h1>
    <form method="POST" action="{{ route('users.store') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('name')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('email')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">At least 12 characters, with uppercase, lowercase, and a number.</p>
            @error('password')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <fieldset>
            <legend class="text-sm font-medium">Roles</legend>
            <div class="mt-2 space-y-2">
                @foreach ($roles as $role)
                    @continue($role->slug === 'super-administrator' && ! $currentUser?->isSuperAdministrator())
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', []))) class="mt-0.5">
                        <span>
                            <span class="font-medium">{{ $role->name }}</span>
                            <span class="block text-muted">{{ $role->description }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('roles')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </fieldset>
        <div class="flex gap-3">
            <button type="submit" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">Create user</button>
            <a href="{{ route('users.index') }}" class="rounded border border-line px-4 py-2 text-sm">Cancel</a>
        </div>
    </form>
</x-layouts.app>
