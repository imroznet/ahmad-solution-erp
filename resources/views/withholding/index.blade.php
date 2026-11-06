<x-layouts.app :title="strtoupper($kind).' records'">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ strtoupper($kind) }} records</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">{{ $kind === 'vds' ? 'VAT deducted at source.' : 'Income-tax withholding.' }} The amount is typed. It is not output VAT and it is not added to a Mushak total. A statutory certificate is not issued from this screen.</p>
        </div>
        @if ($canRecord)
            <a href="{{ route($kind.'.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Record {{ strtoupper($kind) }}</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Party</th><th class="px-3 py-2">Direction</th><th class="px-3 py-2">Amount</th></tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="font-medium text-brand" href="{{ route($kind.'.show', $record) }}">{{ $record->record_date?->toDateString() }}</a></td>
                        <td class="px-3 py-2">{{ $record->party_name }}</td>
                        <td class="px-3 py-2">{{ \App\Tax\WithholdingDirection::label($record->direction) }}</td>
                        <td class="px-3 py-2">{{ $record->amount }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-4 text-muted" colspan="4">No records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
