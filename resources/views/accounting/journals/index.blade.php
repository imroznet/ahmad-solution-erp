<x-layouts.app title="Journals">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Journals</h1>
        @if ($canCreate)
            <a href="{{ route('journals.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New journal</a>
        @endif
    </div>
    <p class="mt-1 max-w-3xl text-sm text-muted">A draft can be edited or deleted. A posted journal stays as it was. The correction is a reversal. Numbers use the Asia/Dhaka year.</p>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Source</th>
                    <th class="px-3 py-2 font-medium">Memo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($journals as $journal)
                    <tr class="border-b border-line last:border-b-0">
                        <td class="px-3 py-2"><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('journals.show', $journal) }}">{{ $journal->number }}</a></td>
                        <td class="px-3 py-2">{{ $journal->journalDateString() }}</td>
                        <td class="px-3 py-2">{{ $journal->status->label() }}</td>
                        <td class="px-3 py-2">{{ $journal->source->label() }}</td>
                        <td class="px-3 py-2">{{ $journal->memo }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-muted">No journals yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $journals->links() }}</div>
</x-layouts.app>
