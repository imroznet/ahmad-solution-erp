<x-layouts.app title="Public site">
    <h1 class="text-xl font-semibold">Public site</h1>
    <p class="mt-1 max-w-2xl text-sm text-muted">ahmad.com.bd stays visible unless maintenance is turned on. This screen does not hide the ERP, and it does not change quotation share links.</p>

    <form method="POST" action="{{ route('public-site.update') }}" class="mt-6 max-w-2xl rounded border border-line bg-white px-4 py-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="maintenance_enabled" value="0">
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="maintenance_enabled" value="1" class="mt-1" @checked(filter_var(old('maintenance_enabled', $enabled), FILTER_VALIDATE_BOOLEAN))>
            <span>
                <span class="font-medium">Hide the public site for maintenance</span>
                <span class="mt-1 block text-muted">Visitors then see that the site is unavailable right now because of maintenance. Turn this off to show the site again.</span>
            </span>
        </label>
        @error('maintenance_enabled')
            <p class="mt-2 text-sm text-red-800">{{ $message }}</p>
        @enderror
        <button type="submit" class="mt-4 rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save</button>
    </form>

    <div class="mt-6 max-w-2xl text-sm">
        <p class="font-medium">Flag file</p>
        <p class="mt-1 break-all font-mono text-xs">{{ $path }}</p>
        @if ($shared)
            <p class="mt-2 text-muted">The public site reads this path when it is deployed with the same file. A value of 1 hides the public site. Any other value, or a missing file, leaves it visible.</p>
        @else
            <p class="mt-2 text-muted">This copy writes the flag inside its own storage. ahmad.com.bd does not read that file. Set <span class="font-mono">PUBLIC_SITE_MAINTENANCE_FILE</span> to the shared path, <span class="font-mono">/home/ahmadcom/ahmad-private/public-site-maintenance</span>, on the host before the live site will follow this switch.</p>
        @endif
    </div>
</x-layouts.app>
