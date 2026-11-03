<x-layouts.app title="Audit log">
    <h1 class="text-xl font-semibold">Audit log</h1>
    <p class="mt-1 text-sm text-muted">Sign-in events and administrative changes. Passwords are not stored.</p>
    <form method="GET" action="{{ route('audit.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
        <label for="event" class="text-sm">Event</label>
        <select id="event" name="event" class="rounded border border-line bg-white px-3 py-2 text-sm">
            <option value="">All</option>
            @foreach ($events as $name)
                <option value="{{ $name }}" @selected($event === $name)>{{ $name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Filter</button>
    </form>
    <div class="mt-4 space-y-3">
        @forelse ($logs as $log)
            <article class="rounded border border-line bg-white px-4 py-3 text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-medium">{{ $log->label() }}</h2>
                    <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</time>
                </div>
                <p class="mt-1 text-muted">{{ $log->user?->name ?? 'Unknown' }} @if ($log->ip_address) · {{ $log->ip_address }} @endif</p>
                @if ($log->old_values || $log->new_values)
                    <details class="mt-2">
                        <summary class="cursor-pointer text-brand">Details</summary>
                        @if ($log->old_values)
                            <h3 class="mt-2 text-xs font-medium uppercase tracking-wide text-muted">Before</h3>
                            <pre class="mt-1 overflow-x-auto text-xs">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                        @if ($log->new_values)
                            <h3 class="mt-2 text-xs font-medium uppercase tracking-wide text-muted">After</h3>
                            <pre class="mt-1 overflow-x-auto text-xs">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                    </details>
                @endif
            </article>
        @empty
            <p class="text-sm text-muted">No audit events match this filter.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
