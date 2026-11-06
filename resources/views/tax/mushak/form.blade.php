<x-layouts.app :title="$document ? 'Edit Mushak 6.3' : 'New Mushak 6.3'">
    <h1 class="text-xl font-semibold">{{ $document ? 'Edit draft Mushak 6.3' : 'New Mushak 6.3' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Linked to commercial invoice {{ $invoice->displayNumber() }}. This does not change that invoice. The VAT percentage starts blank. Type a percentage, including 0, and the VAT amount and VAT-inclusive amount are calculated from the line base. The system does not choose the percentage or decide whether it is legally applicable.</p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $document ? route('mushak.update', $document) : route('mushak.store') }}" class="mt-4 space-y-4" data-calculate="{{ route('mushak.calculate') }}" x-data="mushakEditor(@js(old('buyer_source', $document?->buyer_source ?? 'party')))" @change="recalculate($event)">
        @csrf
        @if ($document)
            @method('PUT')
            <input type="hidden" name="document_id" value="{{ $document->id }}">
        @endif
        <input type="hidden" name="commercial_invoice_id" value="{{ $invoice->id }}">

        <div class="grid gap-3 rounded border border-line bg-white p-4 sm:grid-cols-2">
            <div>
                <label for="document_date" class="block text-sm font-medium">Document date</label>
                <input id="document_date" name="document_date" type="date" required value="{{ old('document_date', $document?->document_date?->toDateString() ?? now()->timezone('Asia/Dhaka')->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <fieldset class="sm:col-span-2">
                <legend class="text-sm font-medium">Buyer</legend>
                <div class="mt-2 flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="radio" name="buyer_source" value="party" x-model="source"> Saved customer</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="buyer_source" value="manual" x-model="source"> Enter manually</label>
                </div>
                <p class="mt-1 text-xs text-muted">Manual entry is not saved as a customer.</p>
            </fieldset>
            <div class="sm:col-span-2" x-show="source === 'party'" x-cloak>
                <label for="party_id" class="block text-sm font-medium">Customer</label>
                <select id="party_id" name="party_id" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    <option value="">Choose a customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('party_id', $document?->party_id) === (string) $customer->id)>{{ $customer->legal_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 space-y-3" x-show="source === 'manual'" x-cloak>
                <div>
                    <label for="buyer_legal_name" class="block text-sm font-medium">Buyer name</label>
                    <input id="buyer_legal_name" name="buyer_legal_name" value="{{ old('buyer_legal_name', $document?->buyer_source === 'manual' ? $document->buyer_legal_name : '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="buyer_bin" class="block text-sm font-medium">Buyer BIN</label>
                        <input id="buyer_bin" name="buyer_bin" value="{{ old('buyer_bin', $document?->buyer_source === 'manual' ? $document->buyer_bin : '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-muted">Leave blank when the buyer has no BIN. A BIN is not invented.</p>
                    </div>
                    <div>
                        <label for="buyer_tin" class="block text-sm font-medium">Buyer TIN</label>
                        <input id="buyer_tin" name="buyer_tin" value="{{ old('buyer_tin', $document?->buyer_source === 'manual' ? $document->buyer_tin : '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label for="buyer_address" class="block text-sm font-medium">Buyer address</label>
                    <textarea id="buyer_address" name="buyer_address" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('buyer_address', $document?->buyer_source === 'manual' ? implode("\n", $document->buyerLines()) : '') }}</textarea>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label for="tax_rule_version_id" class="block text-sm font-medium">Approved output-VAT rule, optional</label>
                <select id="tax_rule_version_id" name="tax_rule_version_id" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    <option value="">No rule selected. The document stays marked for tax review.</option>
                    @foreach ($ruleVersions as $version)
                        <option value="{{ $version->id }}" @selected((string) old('tax_rule_version_id', $document?->tax_rule_version_id) === (string) $version->id)>
                            {{ $version->rule?->code }} version {{ $version->version_number }}
                            @if ($version->nullableDecimal('rate_percent'))
                                (record mentions {{ $version->nullableDecimal('rate_percent') }}%, not applied)
                            @endif
                        </option>
                    @endforeach
                </select>
                <label class="mt-2 inline-flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirm_treatment" value="1" @checked(old('confirm_treatment', $document?->review_status === 'treatment_recorded'))>
                    <span>I record that this approved rule is the legal treatment for this document. The line percentages are still the figures I enter. The system does not certify them.</span>
                </label>
            </div>
            <div class="sm:col-span-2">
                <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
                <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $document?->internal_notes) }}</textarea>
                <p class="mt-1 text-xs text-muted">Internal notes stay off the PDF.</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Description</th>
                        <th class="px-3 py-2 font-medium">Unit</th>
                        <th class="px-3 py-2 font-medium">Quantity</th>
                        <th class="px-3 py-2 font-medium">Unit price</th>
                        <th class="px-3 py-2 font-medium">Base amount</th>
                        <th class="px-3 py-2 font-medium">SD amount</th>
                        <th class="px-3 py-2 font-medium">VAT %</th>
                        <th class="px-3 py-2 font-medium">VAT amount</th>
                        <th class="px-3 py-2 font-medium">VAT-inclusive</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr class="border-t border-line" data-line>
                            <td class="px-3 py-2">
                                <input type="hidden" name="lines[{{ $index }}][commercial_invoice_line_id]" value="{{ $row['commercial_invoice_line_id'] }}">
                                <input type="hidden" name="lines[{{ $index }}][driver]" value="{{ $row['driver'] }}" data-driver>
                                <input name="lines[{{ $index }}][description]" value="{{ $row['description'] }}" class="w-56 rounded border border-line px-2 py-1" aria-label="Description">
                            </td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][unit]" value="{{ $row['unit'] }}" class="w-20 rounded border border-line px-2 py-1" aria-label="Unit"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" data-vat="components" inputmode="decimal" class="w-24 rounded border border-line px-2 py-1" aria-label="Quantity"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][unit_price]" value="{{ $row['unit_price'] }}" data-vat="components" inputmode="decimal" class="w-28 rounded border border-line px-2 py-1" aria-label="Unit price"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][base_amount]" value="{{ $row['base_amount'] }}" data-vat="base" inputmode="decimal" class="w-28 rounded border border-line px-2 py-1" aria-label="Base amount"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][supplementary_duty_amount]" value="{{ $row['supplementary_duty_amount'] }}" data-vat="duty" inputmode="decimal" class="w-24 rounded border border-line px-2 py-1" aria-label="Supplementary duty"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][vat_rate]" value="{{ $row['vat_rate'] }}" data-vat="rate" inputmode="decimal" placeholder="" autocomplete="off" class="w-24 rounded border border-line px-2 py-1" aria-label="VAT percentage"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][vat_amount]" value="{{ $row['vat_amount'] }}" data-vat="vat" readonly tabindex="-1" class="w-28 rounded border border-line bg-canvas px-2 py-1" aria-label="VAT amount"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][inclusive_amount]" value="{{ $row['inclusive_amount'] }}" data-vat="inclusive" inputmode="decimal" class="w-28 rounded border border-line px-2 py-1" aria-label="VAT-inclusive amount"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p id="vat-totals" class="text-sm text-muted" x-text="status">VAT is not calculated while a percentage is blank. An entered 0 is calculated as zero VAT. Supplementary duty is separate and is not included in the VAT amount.</p>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
    </form>
    <script>
        function mushakEditor(initialSource) {
            return {
                source: initialSource,
                status: 'VAT is not calculated while a percentage is blank. An entered 0 is calculated as zero VAT. Supplementary duty is separate and is not included in the VAT amount.',
                async recalculate(event) {
                    const input = event.target;
                    if (!input.dataset || !input.dataset.vat || input.dataset.vat === 'vat') {
                        return;
                    }
                    const row = input.closest('[data-line]');
                    const driver = row ? row.querySelector('[data-driver]') : null;
                    if (driver) {
                        driver.value = input.dataset.vat === 'inclusive' ? 'inclusive' : (input.dataset.vat === 'rate' ? 'rate' : (input.dataset.vat === 'base' ? 'base' : 'components'));
                    }
                    const form = this.$root;
                    const body = new FormData(form);
                    body.delete('_method');
                    const response = await fetch(form.dataset.calculate, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body,
                    });
                    if (!response.ok) {
                        this.status = 'The amounts were not calculated. Check the percentage and the amounts.';
                        return;
                    }
                    const data = await response.json();
                    const rows = form.querySelectorAll('[data-line]');
                    data.lines.forEach((line, index) => {
                        const current = rows[index];
                        if (!current) {
                            return;
                        }
                        const base = current.querySelector('[data-vat="base"]');
                        const vat = current.querySelector('[data-vat="vat"]');
                        const inclusive = current.querySelector('[data-vat="inclusive"]');
                        const driverField = current.querySelector('[data-driver]');
                        const driver = driverField ? driverField.value : '';
                        if (!line.calculated) {
                            if (vat) {
                                vat.value = '';
                            }
                            if (inclusive) {
                                inclusive.value = '';
                            }
                        } else {
                            if (base && (driver !== 'base' || document.activeElement !== base)) {
                                base.value = line.base;
                            }
                            if (vat) {
                                vat.value = line.vat === null ? '' : line.vat;
                            }
                            if (inclusive && (driver !== 'inclusive' || document.activeElement !== inclusive)) {
                                inclusive.value = line.inclusive === null ? '' : line.inclusive;
                            }
                        }
                    });
                    this.status = data.vat_total === null
                        ? data.notice + ' VAT total is not calculated until every line has a percentage.'
                        : data.notice + ' Base ' + data.base_total + ', VAT ' + data.vat_total + ', VAT-inclusive ' + data.inclusive_total + '.';
                },
            };
        }
    </script>
</x-layouts.app>
