<x-layouts.guest title="Sign in">
    <h1 class="text-lg font-semibold">Sign in</h1>
    <p class="mt-1 text-sm text-muted">Use the account issued by an administrator.</p>
    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('email')
                <p class="mt-1 text-sm text-red-800">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="rounded border-line">
            Remember this browser
        </label>
        <button type="submit" class="w-full rounded bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">Sign in</button>
    </form>
    <p class="mt-4 text-sm">
        <a href="{{ route('password.request') }}" class="text-brand hover:text-brand-dark">Forgot password</a>
    </p>
</x-layouts.guest>
