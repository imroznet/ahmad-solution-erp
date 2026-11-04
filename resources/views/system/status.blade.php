<x-layouts.app title="System status">
    <h1 class="text-xl font-semibold">System status</h1>
    <p class="mt-1 text-sm text-muted">Runtime checks for this server. Credentials and connection names are not shown.</p>

    @if ($debug)
        <p class="mt-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">Debug output is enabled. Staging and production must keep it off.</p>
    @endif

    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">PHP</dt>
            <dd class="mt-1 font-medium">{{ $phpVersion }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Environment</dt>
            <dd class="mt-1 font-medium">{{ $environment }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Database</dt>
            <dd class="mt-1 font-medium">{{ $database }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Decimal arithmetic</dt>
            <dd class="mt-1 font-medium">{{ $decimal }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Timezone</dt>
            <dd class="mt-1 font-medium">{{ $timezone }}</dd>
        </div>
        <div class="rounded border border-line bg-white px-4 py-3">
            <dt class="text-sm text-muted">Queue / session</dt>
            <dd class="mt-1 font-medium">{{ $queue }} / {{ $sessionDriver }}</dd>
        </div>
    </dl>

    <h2 class="mt-8 text-base font-semibold">PHP extensions</h2>
    <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Extension</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($extensions as $extension)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2 font-medium">{{ $extension['name'] }}</td>
                        <td class="px-3 py-2">{{ $extension['loaded'] ? 'Loaded' : 'Not loaded' }}</td>
                        <td class="px-3 py-2 text-muted">{{ $extension['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
