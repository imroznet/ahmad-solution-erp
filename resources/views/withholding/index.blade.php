<x-layouts.app :title="\App\Tax\TaxType::label($taxType)">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ \App\Tax\TaxType::label($taxType) }} records</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">{{ $taxType === 'vds' ? 'VDS is withholding of VAT. It is not output VAT and it is not added to a Mushak total.' : 'AIT is income-tax withholding. It is not VAT and it is not VDS.' }} A record is not created for every receipt or payment. A certificate cannot be issued.</p>
        </div>
        @if ($currentUser?->hasPermission($taxType === 'vds' ? 'vds.manage' : 'ait.manage'))
            <a href="{{ route($taxType === 'vds' ? 'vds.create' : 'ait.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New record</a>
        @endif
    </div>
    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="text-left text-muted"><tr><th class="px-3 py-2">Record</th><th class="px-3 py-2">Direction</th><th class="px-3 py-2">Party</th><th class="px-3 py-2">Base</th><th class="px-3 py-2">Status</th></tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t border-line">
                        <td class="px-3 py-2"><a class="text-brand underline" href="{{ route($taxType === 'vds' ? 'vds.show' : 'ait.show', $record) }}">{{ $record->id }}</a></td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxDirection::label($record->direction) }}</td>
                        <td class="px-3 py-2">{{ $record->party_legal_name }}</td>
                        <td class="px-3 py-2">{{ $record->basis_amount }}</td>
                        <td class="px-3 py-2">{{ \App\Tax\TaxDocumentStatus::label($record->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-muted">No records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
