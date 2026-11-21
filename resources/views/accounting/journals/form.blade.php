<x-layouts.app :title="$journal ? 'Edit '.$journal->number : 'New journal'">
    <h1 class="text-xl font-semibold">{{ $journal ? 'Edit '.$journal->number : 'New journal' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Enter a debit or a credit on each line. The journal has to balance before it can be posted. Amounts are BDT. Leave unused rows blank.</p>
    @include('accounting.partials.errors')
    @php
        $submitted = old('lines', $rows);
    @endphp
    <form method="POST" action="{{ $journal ? route('journals.update', $journal) : route('journals.store') }}" class="mt-6 max-w-5xl space-y-4">
        @csrf
        @if ($journal)
            @method('PUT')
        @endif
        <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2">
            <div>
                <label for="journal_date" class="block text-sm font-medium">Date</label>
                <input id="journal_date" name="journal_date" type="date" required value="{{ old('journal_date', $journal?->journalDateString() ?: $defaultDate) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="memo" class="block text-sm font-medium">Memo</label>
                <input id="memo" name="memo" value="{{ old('memo', $journal->memo ?? '') }}" maxlength="2000" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
        </div>
        <div class="space-y-3">
            @foreach ($submitted as $index => $row)
                <div class="grid gap-3 rounded border border-line bg-white p-3 lg:grid-cols-[1.4fr_8rem_8rem_1fr_1fr]">
                    <div>
                        <label for="account-{{ $index }}" class="block text-sm font-medium">Account</label>
                        <select id="account-{{ $index }}" name="lines[{{ $index }}][account_id]" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                            <option value="">None</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) ($row['account_id'] ?? '') === (string) $account->id)>{{ $account->code }} {{ $account->name }}{{ $account->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="debit-{{ $index }}" class="block text-sm font-medium">Debit</label>
                        <input id="debit-{{ $index }}" name="lines[{{ $index }}][debit]" value="{{ $row['debit'] ?? '' }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="credit-{{ $index }}" class="block text-sm font-medium">Credit</label>
                        <input id="credit-{{ $index }}" name="lines[{{ $index }}][credit]" value="{{ $row['credit'] ?? '' }}" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="party-{{ $index }}" class="block text-sm font-medium">Party</label>
                        <select id="party-{{ $index }}" name="lines[{{ $index }}][party_id]" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                            <option value="">None</option>
                            @foreach ($parties as $party)
                                <option value="{{ $party->id }}" @selected((string) ($row['party_id'] ?? '') === (string) $party->id)>{{ $party->legal_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="line-memo-{{ $index }}" class="block text-sm font-medium">Line note</label>
                        <input id="line-memo-{{ $index }}" name="lines[{{ $index }}][memo]" value="{{ $row['memo'] ?? '' }}" maxlength="200" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save draft</button>
            <a href="{{ $journal ? route('journals.show', $journal) : route('journals.index') }}" class="rounded border border-line px-3 py-2 text-sm">Cancel</a>
        </div>
    </form>
</x-layouts.app>
