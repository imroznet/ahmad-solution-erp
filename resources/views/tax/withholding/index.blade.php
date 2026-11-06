<x-layouts.app :title="$title">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $title }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">{{ $intro }}</p>
        </div>
        @if ($canCreate)
            <a href="{{ route($createRoute) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New record</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Direction</th>
                    <th class="px-3 py-2 font-medium">Party</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="font-medium text-brand" href="{{ route($showRoute, $record) }}">{{ $record->record_date?->format('d M Y') }}</a></td>
                        <td class="px-3 py-2">{{ $record->directionLabel() }}</td>
                        <td class="px-3 py-2">{{ $record->counterparty_name }}</td>
                        <td class="px-3 py-2">{{ $record->stringAttribute('status') === 'voided' ? 'Voided' : 'Recorded' }}</td>
                        <td class="px-3 py-2 text-right">{{ $record->taxMoney()->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-muted">No records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
