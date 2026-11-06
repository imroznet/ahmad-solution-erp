@php
    $map = \App\Tax\MushakFieldMap::class;
    $source = old('buyer_source', $document?->buyer_source ?? 'party');
    $rows = old('lines', $lines);
    if (! is_array($rows)) {
        $rows = $lines;
    }
    if (old('lines') === null && count($rows) < (int) config('asl.mushak.max_lines', 30)) {
        $rows[] = [
            'commercial_invoice_line_id' => null,
            'description' => null,
            'unit' => null,
            'quantity' => null,
            'unit_price' => null,
            'line_value' => null,
            'supplementary_duty' => null,
            'vat_rate' => null,
            'specific_tax_note' => null,
            'vat_amount' => null,
            'inclusive_value' => null,
        ];
    }
    $value = function (string $key, mixed $fallback = null) use ($document): mixed {
        return old($key, $document?->getAttribute($key) ?? $fallback);
    };
@endphp
<x-layouts.app :title="$document ? 'Edit Mushak 6.3' : 'New Mushak 6.3'">
    <h1 class="text-xl font-semibold">{{ $document ? 'Edit draft Mushak 6.3' : 'New Mushak 6.3' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">{{ $map::MANUAL_NOTICE }} Leave a percentage blank when you are not recording one. Zero is stored only if you type it.</p>
    @if ($invoice)
        <p class="mt-2 text-sm">Linked commercial invoice {{ $invoice->displayNumber() }}. Description, unit, quantity, unit price, and the commercial line total can be copied in. VAT, supplementary duty, and the inclusive amount stay blank.</p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $document ? route('mushak.update', $document) : route('mushak.store') }}" class="mt-4 space-y-4">
        @csrf
        @if ($document)
            @method('PUT')
        @endif
        <input type="hidden" name="commercial_invoice_id" value="{{ $value('commercial_invoice_id', $invoice?->id) }}">

        <fieldset class="rounded border border-line bg-white p-4">
            <legend class="px-1 text-sm font-medium">Buyer</legend>
            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="radio" name="buyer_source" value="party" @checked($source === 'party')> Existing customer</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="buyer_source" value="manual" @checked($source === 'manual')> One-off buyer, not saved as a customer</label>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="party_id" class="block text-sm font-medium">Customer</label>
                    <select id="party_id" name="party_id" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                        <option value="">None</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) $value('party_id', $invoice?->quotation?->party_id) === (string) $customer->id)>{{ $customer->legal_name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-muted">Used only when the source is an existing customer. The saved customer details replace a typed name.</p>
                </div>
                <div>
                    <label for="buyer_name" class="block text-sm font-medium">{{ $map::BUYER_NAME }}</label>
                    <input id="buyer_name" name="buyer_name" value="{{ $value('buyer_name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">Used only for a one-off buyer.</p>
                </div>
                <div>
                    <label for="buyer_bin" class="block text-sm font-medium">{{ $map::BUYER_BIN }}</label>
                    <input id="buyer_bin" name="buyer_bin" value="{{ $value('buyer_bin') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="buyer_tin" class="block text-sm font-medium">Buyer TIN</label>
                    <input id="buyer_tin" name="buyer_tin" value="{{ $value('buyer_tin') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">Kept on the record. It is not a form column.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="buyer_address" class="block text-sm font-medium">Buyer address</label>
                    <textarea id="buyer_address" name="buyer_address" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ $value('buyer_address') }}</textarea>
                </div>
                <div>
                    <label for="buyer_phone" class="block text-sm font-medium">Buyer phone</label>
                    <input id="buyer_phone" name="buyer_phone" value="{{ $value('buyer_phone') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="buyer_email" class="block text-sm font-medium">Buyer email</label>
                    <input id="buyer_email" name="buyer_email" value="{{ $value('buyer_email') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="destination" class="block text-sm font-medium">{{ $map::DESTINATION }}</label>
                    <input id="destination" name="destination" value="{{ $value('destination') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
            </div>
        </fieldset>

        <fieldset class="rounded border border-line bg-white p-4">
            <legend class="px-1 text-sm font-medium">Issue and responsibility</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="issue_date" class="block text-sm font-medium">{{ $map::ISSUE_DATE }}</label>
                    <input id="issue_date" name="issue_date" value="{{ old('issue_date', $document?->issue_date?->format('Y-m-d')) }}" placeholder="YYYY-MM-DD" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="issue_time" class="block text-sm font-medium">{{ $map::ISSUE_TIME }}</label>
                    <input id="issue_time" name="issue_time" value="{{ $value('issue_time') }}" placeholder="HH:MM" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="responsible_name" class="block text-sm font-medium">{{ $map::RESPONSIBLE_NAME }}</label>
                    <input id="responsible_name" name="responsible_name" value="{{ $value('responsible_name') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="responsible_designation" class="block text-sm font-medium">{{ $map::RESPONSIBLE_DESIGNATION }}</label>
                    <input id="responsible_designation" name="responsible_designation" value="{{ $value('responsible_designation') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
            </div>
        </fieldset>

        <div class="overflow-x-auto rounded border border-line bg-white p-4">
            <h2 class="text-sm font-medium">Lines</h2>
            <p class="mt-1 text-xs text-muted">Type every figure you want stored. A total is not filled in from the percentage. Column 6 is value excluding all taxes.</p>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-muted">
                    <tr>
                        <th class="px-2 py-1 font-medium">Description</th>
                        <th class="px-2 py-1 font-medium">Unit</th>
                        <th class="px-2 py-1 font-medium">Qty</th>
                        <th class="px-2 py-1 font-medium">Unit price</th>
                        <th class="px-2 py-1 font-medium">Value</th>
                        <th class="px-2 py-1 font-medium">SD</th>
                        <th class="px-2 py-1 font-medium">VAT %</th>
                        <th class="px-2 py-1 font-medium">VAT amount</th>
                        <th class="px-2 py-1 font-medium">Inclusive</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr>
                            <td class="px-2 py-1">
                                <input type="hidden" name="lines[{{ $index }}][commercial_invoice_line_id]" value="{{ $row['commercial_invoice_line_id'] ?? '' }}">
                                <input aria-label="Description {{ $index + 1 }}" name="lines[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" class="w-48 rounded border border-line px-2 py-1">
                            </td>
                            <td class="px-2 py-1"><input aria-label="Unit {{ $index + 1 }}" name="lines[{{ $index }}][unit]" value="{{ $row['unit'] ?? '' }}" class="w-20 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="Quantity {{ $index + 1 }}" name="lines[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}" class="w-24 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="Unit price {{ $index + 1 }}" name="lines[{{ $index }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}" class="w-24 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="Line value {{ $index + 1 }}" name="lines[{{ $index }}][line_value]" value="{{ $row['line_value'] ?? '' }}" class="w-24 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="Supplementary duty {{ $index + 1 }}" name="lines[{{ $index }}][supplementary_duty]" value="{{ $row['supplementary_duty'] ?? '' }}" class="w-24 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="VAT percentage {{ $index + 1 }}" name="lines[{{ $index }}][vat_rate]" value="{{ $row['vat_rate'] ?? '' }}" class="w-20 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="VAT amount {{ $index + 1 }}" name="lines[{{ $index }}][vat_amount]" value="{{ $row['vat_amount'] ?? '' }}" class="w-24 rounded border border-line px-2 py-1"></td>
                            <td class="px-2 py-1"><input aria-label="Inclusive value {{ $index + 1 }}" name="lines[{{ $index }}][inclusive_value]" value="{{ $row['inclusive_value'] ?? '' }}" class="w-24 rounded border border-line px-2 py-1"></td>
                        </tr>
                        <tr>
                            <td colspan="9" class="px-2 pb-2">
                                <input aria-label="Specific tax note {{ $index + 1 }}" name="lines[{{ $index }}][specific_tax_note]" value="{{ $row['specific_tax_note'] ?? '' }}" placeholder="Specific tax note, if the rate column is not a percentage" class="w-full rounded border border-line px-2 py-1 text-xs">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <fieldset class="rounded border border-line bg-white p-4">
            <legend class="px-1 text-sm font-medium">{{ $map::TOTAL_LABEL }}</legend>
            <p class="mt-2 text-xs text-muted">Type the totals. They must equal the lines you entered. They are not calculated for you.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                <div>
                    <label for="total_value" class="block text-sm font-medium">Total value</label>
                    <input id="total_value" name="total_value" value="{{ $value('total_value') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="total_supplementary_duty" class="block text-sm font-medium">Total SD</label>
                    <input id="total_supplementary_duty" name="total_supplementary_duty" value="{{ $value('total_supplementary_duty') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="total_vat" class="block text-sm font-medium">Total VAT</label>
                    <input id="total_vat" name="total_vat" value="{{ $value('total_vat') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="total_inclusive" class="block text-sm font-medium">Total inclusive</label>
                    <input id="total_inclusive" name="total_inclusive" value="{{ $value('total_inclusive') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
            </div>
        </fieldset>

        <div>
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ $value('internal_notes') }}</textarea>
            <p class="mt-1 text-xs text-muted">Not printed on the PDF.</p>
        </div>

        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
    </form>
</x-layouts.app>
