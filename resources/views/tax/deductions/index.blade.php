<x-layouts.app :title="$title">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $title }} records</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">{{ $title }} is not output VAT. Amounts are typed. This is not Mushak 6.6 and not a withholding certificate.</p>
        </div>
        @if ($currentUser?->hasPermission($kind.'.create'))
            <a href="{{ route('deductions.create', ['kind' => $kind]) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New {{ $title }} record</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Party</th><th class="px-3 py-2">Direction</th><th class="px-3 py-2">Amount</th><th class="px-3 py-2">Status</th></tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="font-medium text-brand" href="{{ route('deductions.show', ['kind' => $kind, 'record' => $record->id]) }}">{{ $record->counterparty_name }}</a></td>
                        <td class="px-3 py-2">{{ $record->directionLabel() }}</td>
                        <td class="px-3 py-2">{{ $record->deducted_amount ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $record->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-muted">No records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
