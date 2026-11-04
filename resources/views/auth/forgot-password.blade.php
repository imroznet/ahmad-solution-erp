<x-layouts.guest title="Reset password">
    <h1 class="text-lg font-semibold">Reset password</h1>
    <p class="mt-1 text-sm text-muted">If the account exists, a reset link will be sent to that address.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('email')
                <p class="mt-1 text-sm text-red-800">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full rounded bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">Send reset link</button>
    </form>
    <p class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="text-brand hover:text-brand-dark">Back to sign in</a>
    </p>
</x-layouts.guest>
