<x-layouts.app :title="$mushak ? 'Edit Mushak 6.3' : 'New Mushak 6.3'">
    <h1 class="text-xl font-semibold">{{ $mushak ? 'Edit draft Mushak 6.3' : 'New Mushak 6.3' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">This is a tax invoice linked to commercial invoice {{ $commercial->displayNumber() }}. It does not change that invoice. Type the VAT percentage yourself. The system does not choose one, including 15%. A blank percentage calculates nothing. Zero is used only when you enter 0.</p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $mushak ? route('mushak.update', $mushak) : route('mushak.store') }}" class="mt-4 space-y-4" x-data="mushakForm()">
        @csrf
        @if ($mushak)
            @method('PUT')
        @endif
        <input type="hidden" name="commercial_invoice_id" value="{{ $commercial->id }}">

        <div class="grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
            <div>
                <label for="document_date" class="block text-sm font-medium">Document date</label>
                <input id="document_date" name="document_date" type="date" required value="{{ old('document_date', $mushak?->document_date?->toDateString() ?? $today) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="supply_destination" class="block text-sm font-medium">Place of supply</label>
                <input id="supply_destination" name="supply_destination" value="{{ old('supply_destination', $mushak?->supply_destination) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="supply_classification" class="block text-sm font-medium">Supply classification</label>
                <input id="supply_classification" name="supply_classification" value="{{ old('supply_classification', $mushak?->supply_classification) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-muted">Used only to find an approved rule. An empty classification is not a wildcard, and a matching rule does not fill the percentage.</p>
            </div>
            <div>
                <label for="calculation_precision" class="block text-sm font-medium">Calculation precision</label>
                <select id="calculation_precision" name="calculation_precision" x-model="precision" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ([2, 3, 4] as $scale)
                        <option value="{{ $scale }}" @selected((string) old('calculation_precision', $mushak?->calculation_precision ?? 2) === (string) $scale)>{{ $scale }} decimal places</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="rounding_method" class="block text-sm font-medium">Rounding method</label>
                <select id="rounding_method" name="rounding_method" x-model="method" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($methods as $method)
                        <option value="{{ $method }}" @selected(old('rounding_method', $mushak?->rounding_method ?? 'half_up') === $method)>{{ \App\Tax\RoundingMethod::label($method) }} — {{ \App\Tax\RoundingMethod::definition($method) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">The Mushak face shows BDT at 2 decimal places. A finer precision is kept in the calculation and in the supplementary note. Changing precision or the rounding method recalculates VAT from each line's base and keeps the adjustment.</p>
            </div>
        </div>

        <fieldset class="rounded border border-line bg-white p-4">
            <legend class="px-1 text-sm font-medium">Buyer</legend>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                <label><input type="radio" name="buyer_source" value="party" @checked(old('buyer_source', $mushak?->buyer_source ?? 'party') === 'party')> Existing customer</label>
                <label><input type="radio" name="buyer_source" value="manual" @checked(old('buyer_source', $mushak?->buyer_source) === 'manual')> Enter once, without saving a customer</label>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="party_id" class="block text-sm font-medium">Customer number</label>
                    <input id="party_id" name="party_id" value="{{ old('party_id', $partyId) }}" inputmode="numeric" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">Used when "Existing customer" is selected. The name, BIN, TIN, and address are copied. Later edits to the customer do not change an issued document.</p>
                </div>
                <div>
                    <label for="buyer_name" class="block text-sm font-medium">Buyer name</label>
                    <input id="buyer_name" name="buyer_name" value="{{ old('buyer_name', $mushak?->buyer_name) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="buyer_bin" class="block text-sm font-medium">Buyer BIN</label>
                    <input id="buyer_bin" name="buyer_bin" value="{{ old('buyer_bin', $mushak?->buyer_bin) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="buyer_tin" class="block text-sm font-medium">Buyer TIN</label>
                    <input id="buyer_tin" name="buyer_tin" value="{{ old('buyer_tin', $mushak?->buyer_tin) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="buyer_address" class="block text-sm font-medium">Buyer address</label>
                    <textarea id="buyer_address" name="buyer_address" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('buyer_address', $mushak?->buyer_address) }}</textarea>
                </div>
            </div>
        </fieldset>

        <div class="overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Description</th>
                        <th class="px-3 py-2 font-medium">Base amount</th>
                        <th class="px-3 py-2 font-medium">VAT % you enter</th>
                        <th class="px-3 py-2 font-medium">Calculated VAT</th>
                        <th class="px-3 py-2 font-medium">Rounding / Decimal Adjustment</th>
                        <th class="px-3 py-2 font-medium">Final amount</th>
                        <th class="px-3 py-2 font-medium">Recalculate from</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr class="border-t border-line align-top" data-line="{{ $index }}">
                            <td class="px-3 py-2">
                                <input type="hidden" name="lines[{{ $index }}][commercial_invoice_line_id]" value="{{ $row['commercial_invoice_line_id'] }}">
                                <input name="lines[{{ $index }}][description]" value="{{ old('lines.'.$index.'.description', $row['description']) }}" class="w-56 rounded border border-line px-2 py-1" aria-label="Description">
                                <input name="lines[{{ $index }}][unit]" value="{{ old('lines.'.$index.'.unit', $row['unit']) }}" class="mt-1 w-24 rounded border border-line px-2 py-1" aria-label="Unit">
                                <input name="lines[{{ $index }}][quantity]" value="{{ old('lines.'.$index.'.quantity', $row['quantity']) }}" class="mt-1 w-24 rounded border border-line px-2 py-1" aria-label="Quantity">
                            </td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][base_amount]" value="{{ old('lines.'.$index.'.base_amount', $row['base_amount']) }}" data-field="base_amount" class="w-28 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'base')" @blur="preview({{ $index }})"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][vat_rate]" value="{{ old('lines.'.$index.'.vat_rate', $row['vat_rate']) }}" data-field="vat_rate" placeholder="Blank" class="w-24 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'base')" @blur="preview({{ $index }})"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][vat_amount]" value="{{ old('lines.'.$index.'.vat_amount', $row['vat_amount']) }}" data-field="vat_amount" readonly class="w-28 rounded border border-line bg-canvas px-2 py-1" aria-label="Calculated VAT"></td>
                            <td class="px-3 py-2">
                                <input name="lines[{{ $index }}][adjustment_amount]" value="{{ old('lines.'.$index.'.adjustment_amount', $row['adjustment_amount']) }}" data-field="adjustment_amount" class="w-28 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'adjustment')" @blur="preview({{ $index }})">
                                <input name="lines[{{ $index }}][adjustment_reason]" value="{{ old('lines.'.$index.'.adjustment_reason', $row['adjustment_reason']) }}" placeholder="Reason if not zero" class="mt-1 w-40 rounded border border-line px-2 py-1" aria-label="Adjustment reason">
                            </td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][final_amount]" value="{{ old('lines.'.$index.'.final_amount', $row['final_amount']) }}" data-field="final_amount" class="w-28 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'final')" @blur="preview({{ $index }})"></td>
                            <td class="px-3 py-2">
                                <select name="lines[{{ $index }}][edited]" data-field="edited" class="rounded border border-line px-2 py-1">
                                    @foreach (['base' => 'Base and percentage', 'final' => 'Final amount', 'adjustment' => 'Adjustment only'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('lines.'.$index.'.edited', $row['edited']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="mt-1 block text-brand" @click="preview({{ $index }})">Preview</button>
                                <p data-field="note" class="mt-1 max-w-xs text-xs text-muted"></p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-sm">Final = base + VAT + Rounding / Decimal Adjustment. The adjustment never changes the percentage. VDS and AIT are not part of this calculation.</p>
        <div>
            <label for="customer_notes" class="block text-sm font-medium">Note printed for the buyer</label>
            <textarea id="customer_notes" name="customer_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('customer_notes', $mushak?->customer_notes) }}</textarea>
        </div>
        <div>
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $mushak?->internal_notes) }}</textarea>
            <p class="mt-1 text-xs text-muted">Internal notes stay off the PDF.</p>
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
    </form>
    <script>
        function mushakForm() {
            return {
                precision: '{{ old('calculation_precision', $mushak?->calculation_precision ?? 2) }}',
                method: '{{ old('rounding_method', $mushak?->rounding_method ?? 'half_up') }}',
                edited(index, origin) {
                    const select = this.row(index).querySelector('[data-field="edited"]');
                    if (select) select.value = origin;
                },
                row(index) {
                    return this.$root.querySelector('[data-line="' + index + '"]');
                },
                async preview(index) {
                    const row = this.row(index);
                    const body = new FormData();
                    body.append('_token', this.$root.querySelector('input[name="_token"]').value);
                    body.append('calculation_precision', this.precision);
                    body.append('rounding_method', this.method);
                    body.append('edited', row.querySelector('[data-field="edited"]').value);
                    ['base_amount', 'vat_rate', 'vat_amount', 'adjustment_amount', 'final_amount'].forEach((field) => {
                        body.append(field, row.querySelector('[data-field="' + field + '"]').value);
                    });
                    const response = await fetch('{{ route('mushak.calculate') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body,
                    });
                    const payload = await response.json();
                    const note = row.querySelector('[data-field="note"]');
                    if (!response.ok) {
                        note.textContent = payload.message || 'The line could not be calculated.';
                        return;
                    }
                    row.querySelector('[data-field="base_amount"]').value = payload.base_amount;
                    row.querySelector('[data-field="vat_amount"]').value = payload.vat_amount ?? '';
                    row.querySelector('[data-field="adjustment_amount"]').value = payload.adjustment_amount;
                    row.querySelector('[data-field="final_amount"]').value = payload.final_amount ?? '';
                    note.textContent = payload.explanation;
                },
            };
        }
    </script>
</x-layouts.app>
