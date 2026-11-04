<x-layouts.guest title="Choose a new password">
    <h1 class="text-lg font-semibold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('email')
                <p class="mt-1 text-sm text-red-800">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-muted">At least 12 characters, with uppercase, lowercase, and a number.</p>
            @error('password')
                <p class="mt-1 text-sm text-red-800">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <button type="submit" class="w-full rounded bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">Update password</button>
    </form>
</x-layouts.guest>
