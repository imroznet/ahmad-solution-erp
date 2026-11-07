<x-layouts.app :title="'Edit '.$invoice->displayNumber()">
    <h1 class="text-xl font-semibold">Draft Mushak 6.3</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Type the VAT percentage yourself. It is not filled from a tax rule, a product, or 15%. A blank percentage does not calculate VAT. Final = base + VAT + adjustment. Supplementary duty, VDS, and AIT stay outside that formula. Amounts update as you type; the server calculates them, and saving calculates them again.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('mushak.update', $invoice) }}" class="mt-4 space-y-4" x-data="mushakForm()" data-calculate-url="{{ route('mushak.calculate') }}" data-precision="{{ old('calculation_precision', $invoice->calculation_precision) }}" data-method="{{ old('rounding_method', $invoice->rounding_method) }}">
        @csrf
        @method('PUT')
        <div class="grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
            <label class="text-sm">Issue date
                <input type="date" name="document_date" value="{{ old('document_date', $invoice->document_date?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
            </label>
            <label class="text-sm">Destination of supply
                <input name="destination" value="{{ old('destination', $invoice->destination) }}" class="mt-1 w-full rounded border border-line px-2 py-1" required>
            </label>
            <label class="text-sm">Calculation precision
                <select name="calculation_precision" x-model="precision" @change="recalculateAll()" class="mt-1 w-full rounded border border-line px-2 py-1">
                    @foreach ([2, 3, 4] as $precision)
                        <option value="{{ $precision }}" @selected((int) old('calculation_precision', $invoice->calculation_precision) === $precision)>{{ $precision }} decimal places</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Rounding method
                <select name="rounding_method" x-model="method" @change="recalculateAll()" class="mt-1 w-full rounded border border-line px-2 py-1">
                    @foreach (\App\Tax\VatRounding::ALL as $method)
                        <option value="{{ $method }}" @selected(old('rounding_method', $invoice->rounding_method) === $method)>{{ \App\Tax\VatRounding::label($method) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Buyer
                <select name="buyer_source" class="mt-1 w-full rounded border border-line px-2 py-1">
                    <option value="invoice" @selected(old('buyer_source', $invoice->buyer_source) === 'invoice')>Commercial invoice customer</option>
                    <option value="party" @selected(old('buyer_source', $invoice->buyer_source) === 'party')>Existing party</option>
                    <option value="manual" @selected(old('buyer_source', $invoice->buyer_source) === 'manual')>One-off buyer</option>
                </select>
            </label>
            <label class="text-sm">Existing party ID
                <input name="party_id" value="{{ old('party_id', $invoice->party_id) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
            </label>
            <label class="text-sm">One-off buyer name
                <input name="buyer_name" value="{{ old('buyer_name', $invoice->buyer_name) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
            </label>
            <label class="text-sm">One-off buyer BIN
                <input name="buyer_bin" value="{{ old('buyer_bin', $invoice->buyer_bin) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
            </label>
            <label class="text-sm sm:col-span-2">One-off buyer address
                <textarea name="buyer_address" class="mt-1 w-full rounded border border-line px-2 py-1" rows="2">{{ old('buyer_address', $invoice->buyer_address) }}</textarea>
            </label>
            <label class="text-sm">Responsible person
                <input name="responsible_name" value="{{ old('responsible_name', $invoice->responsible_name) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
            </label>
            <label class="text-sm">Designation
                <input name="responsible_designation" value="{{ old('responsible_designation', $invoice->responsible_designation) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
            </label>
            <label class="text-sm">Output VAT rule reference
                <input name="tax_rule_id" value="{{ old('tax_rule_id', $invoice->tax_rule_id) }}" class="mt-1 w-full rounded border border-line px-2 py-1">
            </label>
            <label class="flex items-end gap-2 text-sm">
                <input type="checkbox" name="tax_review_confirmed" value="1" @checked(old('tax_review_confirmed', $invoice->tax_review_status === 'treatment_confirmed'))>
                A user has confirmed the legal treatment. This is not a certification by the system.
            </label>
            <label class="text-sm sm:col-span-2">Review note
                <textarea name="tax_review_note" class="mt-1 w-full rounded border border-line px-2 py-1" rows="2">{{ old('tax_review_note', $invoice->tax_review_note) }}</textarea>
            </label>
            <label class="text-sm sm:col-span-2">Internal note <span class="text-muted">(kept off the PDF)</span>
                <textarea name="internal_notes" class="mt-1 w-full rounded border border-line px-2 py-1" rows="2">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
            </label>
        </div>
        <div class="overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="text-left text-muted">
                    <tr>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2">Base</th>
                        <th class="px-3 py-2">VAT %</th>
                        <th class="px-3 py-2">Calculated VAT</th>
                        <th class="px-3 py-2">Adjustment</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2">SD</th>
                        <th class="px-3 py-2">Final</th>
                        <th class="px-3 py-2">Driver</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lines as $index => $line)
                        <tr class="border-t border-line align-top" data-line="{{ $index }}">
                            <td class="px-3 py-2">
                                <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                <textarea name="lines[{{ $index }}][description]" class="w-48 rounded border border-line px-2 py-1" rows="2">{{ $line->description }}</textarea>
                                <p class="mt-1 text-xs text-muted">{{ $line->quantity }} {{ $line->unit }}</p>
                            </td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][base_amount]" value="{{ $line->base_amount }}" data-field="base_amount" inputmode="decimal" class="w-28 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'base')" @blur="preview({{ $index }}, true)"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][vat_rate]" value="{{ $line->vat_rate_percent }}" data-field="vat_rate" inputmode="decimal" class="w-24 rounded border border-line px-2 py-1" placeholder="Blank" @input="edited({{ $index }}, 'base')" @blur="preview({{ $index }}, true)"></td>
                            <td class="px-3 py-2"><input data-field="vat_amount" value="{{ $line->vat_amount }}" readonly tabindex="-1" aria-label="Calculated VAT" class="w-28 rounded border border-line bg-canvas px-2 py-1"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][adjustment_amount]" value="{{ $line->adjustment_amount }}" data-field="adjustment_amount" inputmode="decimal" class="w-28 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'adjustment')" @blur="preview({{ $index }}, true)"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][adjustment_reason]" value="{{ $line->adjustment_reason }}" class="w-40 rounded border border-line px-2 py-1"></td>
                            {{-- Supplementary duty has no live handler on purpose: editing it must never change the driver, the base, or the VAT. --}}
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][supplementary_duty_amount]" value="{{ $line->supplementary_duty_amount }}" class="w-28 rounded border border-line px-2 py-1" placeholder="Blank"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][final_amount]" value="{{ $line->final_amount }}" data-field="final_amount" inputmode="decimal" class="w-28 rounded border border-line px-2 py-1" @input="edited({{ $index }}, 'final')" @blur="preview({{ $index }}, true)"></td>
                            <td class="px-3 py-2">
                                <select name="lines[{{ $index }}][driver]" data-field="driver" class="rounded border border-line px-2 py-1" @change="preview({{ $index }})">
                                    <option value="forward" @selected(old('lines.'.$index.'.driver', $line->calculation_snapshot['driver'] ?? 'forward') === 'forward')>From base and rate</option>
                                    <option value="reverse" @selected(old('lines.'.$index.'.driver', $line->calculation_snapshot['driver'] ?? 'forward') === 'reverse')>From final amount</option>
                                </select>
                                <p data-field="note" class="mt-1 max-w-xs text-xs text-muted"></p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Calculate and save</button>
    </form>
    <script>
        // Live VAT preview for the draft Mushak form. The browser does no
        // money arithmetic at all: every preview is the server's
        // VatLineCalculator, the same authority that runs again on save.
        // That is what keeps the preview and the submitted values in
        // agreement, including half-even and directed rounding.
        function mushakForm() {
            return {
                precision: '2',
                method: 'half_up',
                timers: {},
                requests: {},
                init() {
                    this.precision = this.$root.dataset.precision || '2';
                    this.method = this.$root.dataset.method || 'half_up';
                },
                row(index) {
                    return this.$root.querySelector('[data-line="' + index + '"]');
                },
                field(row, name) {
                    return row.querySelector('[data-field="' + name + '"]');
                },
                edited(index, origin) {
                    const row = this.row(index);
                    if (! row) return;
                    const driver = this.field(row, 'driver');
                    // Base, rate, and adjustment edits all calculate forward.
                    // An adjustment edit therefore never reverse-calculates
                    // the base or the VAT. A final-amount edit reverses.
                    if (driver) driver.value = origin === 'final' ? 'reverse' : 'forward';
                    clearTimeout(this.timers[index]);
                    this.timers[index] = setTimeout(() => this.preview(index), 250);
                },
                recalculateAll() {
                    this.$root.querySelectorAll('[data-line]').forEach((row) => this.preview(row.dataset.line));
                },
                async preview(index, normalize = false) {
                    const row = this.row(index);
                    if (! row) return;
                    const base = this.field(row, 'base_amount').value.trim();
                    const rate = this.field(row, 'vat_rate').value.trim();
                    const adjustment = this.field(row, 'adjustment_amount').value.trim();
                    const finalAmount = this.field(row, 'final_amount').value.trim();
                    const driver = this.field(row, 'driver').value;
                    const note = this.field(row, 'note');
                    if (driver === 'reverse' && finalAmount === '') {
                        this.field(row, 'base_amount').value = '';
                        this.field(row, 'vat_amount').value = '';
                        note.textContent = '';
                        return;
                    }
                    if (driver === 'forward' && base === '') {
                        this.field(row, 'vat_amount').value = '';
                        this.field(row, 'final_amount').value = '';
                        note.textContent = '';
                        return;
                    }
                    const requestId = (this.requests[index] || 0) + 1;
                    this.requests[index] = requestId;
                    const body = new FormData();
                    body.append('_token', this.$root.querySelector('input[name="_token"]').value);
                    body.append('calculation_precision', this.precision);
                    body.append('rounding_method', this.method);
                    body.append('driver', driver);
                    body.append('base_amount', base);
                    body.append('vat_rate', rate);
                    body.append('adjustment_amount', adjustment === '' ? '0' : adjustment);
                    body.append('final_amount', finalAmount);
                    let response;
                    let payload;
                    try {
                        response = await fetch(this.$root.dataset.calculateUrl, {
                            method: 'POST',
                            headers: { Accept: 'application/json' },
                            body,
                        });
                        payload = await response.json();
                    } catch (error) {
                        note.textContent = 'Could not calculate yet. Saving will calculate again.';
                        return;
                    }
                    if (this.requests[index] !== requestId) return;
                    if (! response.ok) {
                        note.textContent = payload.message || 'The line could not be calculated.';
                        return;
                    }
                    // While typing, only derived fields change, so the field
                    // being edited keeps the user's text and caret. On blur,
                    // every field is normalised to the server's scaled values.
                    // The percentage is never overwritten: it stays exactly
                    // as the user typed it.
                    this.field(row, 'vat_amount').value = payload.vat_amount ?? '';
                    if (driver === 'reverse') {
                        this.field(row, 'base_amount').value = payload.base_amount;
                        if (normalize) {
                            this.field(row, 'final_amount').value = payload.final_amount ?? '';
                            this.field(row, 'adjustment_amount').value = payload.adjustment_amount;
                        }
                    } else {
                        this.field(row, 'final_amount').value = payload.final_amount ?? '';
                        if (normalize) {
                            this.field(row, 'base_amount').value = payload.base_amount;
                            this.field(row, 'adjustment_amount').value = payload.adjustment_amount;
                        }
                    }
                    if (payload.vat_rate === null) {
                        note.textContent = 'No VAT calculated — the percentage is blank.';
                    } else if (payload.residual_applied) {
                        note.textContent = 'VAT is the residual, so the final amount is kept exactly.';
                    } else {
                        note.textContent = '';
                    }
                },
            };
        }
    </script>
</x-layouts.app>
