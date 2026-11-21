<x-layouts.app :title="$journal->number">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $journal->number }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $journal->status->label() }} · {{ $journal->source->label() }} · {{ $journal->journalDateString() }}</p>
        </div>
        <a href="{{ route('journals.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">All journals</a>
    </div>
    @include('accounting.partials.errors')
    <dl class="mt-4 grid max-w-3xl gap-2 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Financial year</dt><dd>{{ $journal->financial_year_starts_on?->toDateString() }} to {{ $journal->financial_year_ends_on?->toDateString() }}</dd></div>
        <div><dt class="text-muted">Memo</dt><dd>{{ $journal->memo ?: '—' }}</dd></div>
        <div><dt class="text-muted">Source document</dt><dd>{{ $journal->source_type ? $journal->source_type.' '.$journal->source_id : 'None' }}</dd></div>
        <div>
            <dt class="text-muted">Reversal</dt>
            <dd>
                @if ($journal->reversal)
                    <a class="text-brand hover:text-brand-dark" href="{{ route('journals.show', $journal->reversal) }}">{{ $journal->reversal->number }}</a>
                @elseif ($journal->reverses)
                    Reverses <a class="text-brand hover:text-brand-dark" href="{{ route('journals.show', $journal->reverses) }}">{{ $journal->reverses->number }}</a>
                @else
                    None
                @endif
            </dd>
        </div>
    </dl>
    <div class="mt-6 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Account</th>
                    <th class="px-3 py-2 font-medium">Party</th>
                    <th class="px-3 py-2 font-medium">Note</th>
                    <th class="px-3 py-2 text-right font-medium">Debit</th>
                    <th class="px-3 py-2 text-right font-medium">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($journal->lines as $line)
                    <tr class="border-b border-line last:border-b-0">
                        <td class="px-3 py-2">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                        <td class="px-3 py-2">{{ $line->party?->legal_name }}</td>
                        <td class="px-3 py-2">{{ $line->memo }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line->decimalAttribute('debit'))->groupedAmount() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line->decimalAttribute('credit'))->groupedAmount() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-muted">Amounts are BDT.</p>
    <div class="mt-4 flex flex-wrap gap-3">
        @if ($journal->status->value === 'draft' && $canCreate)
            <a href="{{ route('journals.edit', $journal) }}" class="rounded border border-line px-3 py-2 text-sm">Edit draft</a>
            <form method="POST" action="{{ route('journals.destroy', $journal) }}" onsubmit="return confirm('Delete this draft?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Delete draft</button>
            </form>
        @endif
        @if ($journal->status->value === 'draft' && $canPost)
            <form method="POST" action="{{ route('journals.post', $journal) }}">
                @csrf
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Post journal</button>
            </form>
        @endif
        @if ($journal->status->value === 'posted' && $canReverse && ! $journal->reversal)
            <form method="POST" action="{{ route('journals.reverse', $journal) }}" onsubmit="return confirm('Post a reversal that negates this journal?')">
                @csrf
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Reverse</button>
            </form>
        @endif
    </div>
</x-layouts.app>
