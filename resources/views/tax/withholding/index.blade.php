@php
    $title = $kind === 'vds' ? 'VDS records' : 'AIT records';
    $notice = $kind === 'vds'
        ? 'A VDS record is not output VAT and is not added to a Mushak 6.3 VAT amount. Mushak 6.6 is not generated. SRO No-140-Ain/2026/345-Mushak was not verified from the gazette text.'
        : 'An AIT record is income-tax withholding. It is not VAT and it is not VDS. No withholding rate is applied. The Withholding Tax Rules, 2026 were not verified from the gazette text, so this is not a statutory certificate.';
@endphp
<x-layouts.app :title="$title">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $title }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">{{ $notice }}</p>
        </div>
        @if ($canManage)
            <a href="{{ route($kind.'.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New record</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Direction</th>
                    <th class="px-3 py-2 font-medium">Counterparty</th>
                    <th class="px-3 py-2 font-medium">Amount</th>
                    <th class="px-3 py-2 font-medium">Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a href="{{ route($kind.'.show', $record) }}" class="font-medium text-brand hover:underline">{{ $record->record_date?->format('Y-m-d') }}</a></td>
                        <td class="px-3 py-2">{{ $record->directionLabel() }}</td>
                        <td class="px-3 py-2">{{ $record->counterparty_name }}</td>
                        <td class="px-3 py-2">{{ $record->nullableDecimal('deducted_amount') }}</td>
                        <td class="px-3 py-2">{{ $record->reviewLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-muted">No records. Nothing is created from a customer receipt or a supplier payment automatically.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
