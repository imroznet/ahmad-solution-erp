<x-layouts.app title="Opening balances">
    <h1 class="text-xl font-semibold">Opening balances</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">
        The go-live cut-off is the company registration date
        @if ($registrationDate)
            ({{ \Illuminate\Support\Carbon::parse($registrationDate)->format('j F Y') }}).
        @else
            .
        @endif
        Enter the opening balances and the opening date. Each account is included once, so a bank that opened later can have its own later date. A bank or MFS balance cannot be dated before that account was opened. The date has to fall in a financial year. This does not back-post old invoices. A posted opening journal is locked. A correction is a reversal.
    </p>
    @include('accounting.partials.errors')

    @foreach ($openings as $opening)
        <p class="mt-4 text-sm">Posted as <a class="font-medium text-brand hover:text-brand-dark" href="{{ route('journals.show', $opening) }}">{{ $opening->number }}</a> on {{ $opening->journalDateString() }}.</p>
        <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Account</th>
                        <th class="px-3 py-2 text-right font-medium">Debit</th>
                        <th class="px-3 py-2 text-right font-medium">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($opening->lines as $line)
                        <tr class="border-b border-line last:border-b-0">
                            <td class="px-3 py-2">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line->decimalAttribute('debit'))->groupedAmount() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::of($line->decimalAttribute('credit'))->groupedAmount() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if ($accounts->isNotEmpty())
        <form method="POST" action="{{ route('opening-balances.store') }}" class="mt-6 max-w-4xl space-y-4">
            @csrf
            <div class="grid gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2">
                <div>
                    <label for="journal_date" class="block text-sm font-medium">Opening date</label>
                    <input id="journal_date" name="journal_date" type="date" required value="{{ old('journal_date', $registrationDate) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="memo" class="block text-sm font-medium">Memo</label>
                    <input id="memo" name="memo" value="{{ old('memo', 'Opening balances') }}" maxlength="2000" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
            </div>
            <div class="overflow-x-auto rounded border border-line bg-white">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-line text-left text-muted">
                        <tr>
                            <th class="px-3 py-2 font-medium">Account</th>
                            <th class="px-3 py-2 font-medium">Debit</th>
                            <th class="px-3 py-2 font-medium">Credit</th>
                            <th class="px-3 py-2 font-medium">Party</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $index => $account)
                            @php
                                $oldLine = old('lines.'.$index, []);
                            @endphp
                            <tr class="border-b border-line last:border-b-0">
                                <td class="px-3 py-2">
                                    {{ $account->code }} {{ $account->name }}
                                    <input type="hidden" name="lines[{{ $index }}][account_id]" value="{{ $account->id }}">
                                </td>
                                <td class="px-3 py-2">
                                    <label for="debit-{{ $account->id }}" class="sr-only">Debit for {{ $account->name }}</label>
                                    <input id="debit-{{ $account->id }}" name="lines[{{ $index }}][debit]" value="{{ $oldLine['debit'] ?? '' }}" inputmode="decimal" class="w-full rounded border border-line px-3 py-2 text-sm">
                                </td>
                                <td class="px-3 py-2">
                                    <label for="credit-{{ $account->id }}" class="sr-only">Credit for {{ $account->name }}</label>
                                    <input id="credit-{{ $account->id }}" name="lines[{{ $index }}][credit]" value="{{ $oldLine['credit'] ?? '' }}" inputmode="decimal" class="w-full rounded border border-line px-3 py-2 text-sm">
                                </td>
                                <td class="px-3 py-2">
                                    <label for="party-{{ $account->id }}" class="sr-only">Party for {{ $account->name }}</label>
                                    <select id="party-{{ $account->id }}" name="lines[{{ $index }}][party_id]" class="w-full rounded border border-line px-3 py-2 text-sm">
                                        <option value="">None</option>
                                        @foreach ($parties as $party)
                                            <option value="{{ $party->id }}" @selected((string) ($oldLine['party_id'] ?? '') === (string) $party->id)>{{ $party->legal_name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Post opening balances</button>
        </form>
    @endif
</x-layouts.app>
