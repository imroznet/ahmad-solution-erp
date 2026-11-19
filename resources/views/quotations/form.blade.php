<x-layouts.app :title="$mode === 'create' ? 'New quotation' : ($mode === 'revise' ? 'New revision' : 'Edit draft')">
    @php
        $initialLines = old('lines', $lines);
        if (! is_array($initialLines) || $initialLines === []) {
            $initialLines = [['description' => '', 'unit' => '', 'quantity' => '1', 'unit_price' => '0.00']];
        }
        $unitSymbols = [];
        foreach ($unitChoices ?? [] as $unitChoice) {
            if (is_array($unitChoice) && isset($unitChoice['symbol'])) {
                $unitSymbols[] = (string) $unitChoice['symbol'];
            }
        }
        foreach ($initialLines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }
            $unit = trim(strip_tags((string) ($line['unit'] ?? '')));
            $known = $unit !== '' && in_array($unit, $unitSymbols, true);
            $initialLines[$index]['unit'] = $unit;
            $initialLines[$index]['unitChoice'] = $unit === '' ? '' : ($known ? $unit : '__other__');
            $initialLines[$index]['unitCustom'] = $known || $unit === '' ? '' : $unit;
        }
        $action = $mode === 'create'
            ? route('quotations.store')
            : ($mode === 'revise' ? route('quotations.revisions.store', $quotation) : route('quotations.update', $quotation));
        $heading = $mode === 'create' ? 'New quotation' : ($mode === 'revise' ? 'New revision' : 'Edit draft');
        $date = old('quotation_date', $revision?->quotation_date?->format('Y-m-d') ?? now()->timezone('Asia/Dhaka')->toDateString());
        $valid = old('valid_until', $revision?->valid_until?->format('Y-m-d') ?? now()->timezone('Asia/Dhaka')->addDays(30)->toDateString());
        $taxTreatment = old('tax_treatment', $revision?->tax_treatment ?? 'excluded');
        if (! in_array($taxTreatment, ['excluded', 'included'], true)) {
            $taxTreatment = 'excluded';
        }
        $storedVat = $revision?->vat_rate_percent !== null ? \App\Tax\VatPresentation::rate((string) $revision->vat_rate_percent) : '';
        $storedTax = $revision?->tax_rate_percent !== null ? \App\Tax\VatPresentation::rate((string) $revision->tax_rate_percent) : '';
        $storedAdjustment = $revision?->tax_treatment === 'included' ? (string) $revision->adjustment_amount : '';
    @endphp
    <h1 class="text-xl font-semibold">{{ $heading }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">
        @if ($mode === 'revise')
            Saving creates revision {{ ($revision->revision_number ?? 0) + 1 }} and leaves revision {{ $revision->revision_number ?? 1 }} unchanged. The customer snapshot is taken again from the current party record.
        @else
            Lines are typed by hand. Choose VAT excluding or VAT including for this revision. A rate is never filled in for you. Saving a draft refreshes the customer snapshot from the current party record.
        @endif
    </p>

    <form method="POST" action="{{ $action }}" class="mt-6 max-w-5xl space-y-4" x-data="quotationTaxForm()" x-init="start()" data-quotation-tax data-calculate-url="{{ route('quotations.calculate') }}">
        @csrf
        @if ($mode === 'draft') @method('PUT') @endif

        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The quotation could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="party_id" class="block text-sm font-medium">Customer</label>
                @if ($mode === 'revise')
                    <p class="mt-1 text-sm">{{ $party->legal_name }}</p>
                    <input type="hidden" name="party_id" value="{{ $party->id }}">
                @else
                    <select id="party_id" name="party_id" required class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm" @if ($mode === 'create') onchange="window.location='{{ route('quotations.create') }}?party=' + this.value" @else onchange="window.location='{{ route('quotations.edit', $quotation) }}?party=' + this.value" @endif>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('party_id', $party->id) === (int) $customer->id)>{{ $customer->legal_name }}</option>
                        @endforeach
                    </select>
                    @if ($mode === 'draft')
                        <p class="mt-1 text-xs text-muted">Changing the customer reloads this form.</p>
                    @endif
                @endif
            </div>
            <div>
                <label for="quotation_date" class="block text-sm font-medium">Quotation date</label>
                <input id="quotation_date" name="quotation_date" type="date" required value="{{ $date }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="valid_until" class="block text-sm font-medium">Valid until</label>
                <input id="valid_until" name="valid_until" type="date" required value="{{ $valid }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
        </div>

        <fieldset class="rounded border border-line bg-white p-3">
            <legend class="px-1 text-sm font-medium">Contact snapshot</legend>
            @forelse ($party->contacts as $contact)
                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="radio" name="contact_id" value="{{ $contact->id }}" @checked((string) old('contact_id', $party->contacts->firstWhere('is_primary', true)?->id) === (string) $contact->id)>
                    <span>{{ $contact->name }}@if ($contact->designation), {{ $contact->designation }}@endif</span>
                </label>
            @empty
                <p class="mt-2 text-sm text-muted">No active contact. The party phone and email will be copied.</p>
            @endforelse
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <fieldset class="rounded border border-line bg-white p-3">
                <legend class="px-1 text-sm font-medium">Billing address</legend>
                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="radio" name="billing_address_id" value="" @checked(old('billing_address_id', '') === '')>
                    <span>Default billing address, if one is active</span>
                </label>
                @foreach ($party->addresses as $address)
                    <label class="mt-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="billing_address_id" value="{{ $address->id }}" @checked((string) old('billing_address_id') === (string) $address->id)>
                        <span>{{ $address->address_line_1 }}, {{ $address->district }}</span>
                    </label>
                @endforeach
            </fieldset>
            <fieldset class="rounded border border-line bg-white p-3">
                <legend class="px-1 text-sm font-medium">Delivery address</legend>
                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="radio" name="shipping_address_id" value="" @checked(old('shipping_address_id', '') === '')>
                    <span>Default delivery address, if one is active</span>
                </label>
                @foreach ($party->addresses as $address)
                    <label class="mt-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="shipping_address_id" value="{{ $address->id }}" @checked((string) old('shipping_address_id') === (string) $address->id)>
                        <span>{{ $address->address_line_1 }}, {{ $address->district }}</span>
                    </label>
                @endforeach
            </fieldset>
        </div>

        <div x-data="{ lines: {{ \Illuminate\Support\Js::from($initialLines) }}, max: {{ (int) config('asl.quotations.max_lines', 50) }} }" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-medium">Lines</h2>
                <button type="button" class="rounded border border-line px-3 py-2 text-sm" @click="if (lines.length < max) lines.push({description: '', unit: '', unitChoice: '', unitCustom: '', quantity: '1', unit_price: '0.00'})">Add line</button>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid gap-3 rounded border border-line bg-white p-3 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <label class="block text-sm font-medium" :for="'description-' + index">Description</label>
                        <textarea :id="'description-' + index" :name="'lines[' + index + '][description]'" x-model="line.description" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium" :for="'unit-' + index">Unit</label>
                        <select :id="'unit-' + index" x-model="line.unitChoice" @change="line.unit = line.unitChoice === '__other__' ? line.unitCustom.trim() : line.unitChoice" class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                            <option value="">Choose a unit</option>
                            @foreach ($unitChoices ?? [] as $unitOption)
                                @if (is_array($unitOption) && isset($unitOption['symbol'], $unitOption['name']))
                                    <option value="{{ $unitOption['symbol'] }}">{{ $unitOption['name'] }} ({{ $unitOption['symbol'] }})</option>
                                @endif
                            @endforeach
                            <option value="__other__">Other…</option>
                        </select>
                        <input x-show="line.unitChoice === '__other__'" x-cloak x-model="line.unitCustom" maxlength="40" placeholder="Type the unit" aria-label="Custom unit" @input="line.unit = line.unitCustom.trim()" class="mt-2 w-full rounded border border-line px-3 py-2 text-sm">
                        <input type="hidden" :name="'lines[' + index + '][unit]'" :value="line.unit">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium" :for="'quantity-' + index">Quantity</label>
                        <input :id="'quantity-' + index" :name="'lines[' + index + '][quantity]'" x-model="line.quantity" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium" :for="'price-' + index">Unit price</label>
                        <input :id="'price-' + index" :name="'lines[' + index + '][unit_price]'" x-model="line.unit_price" inputmode="decimal" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end lg:col-span-1">
                        <button type="button" class="text-sm text-muted" @click="if (lines.length > 1) lines.splice(index, 1)">Remove</button>
                    </div>
                </div>
            </template>
        </div>

        <fieldset class="rounded border border-line bg-white p-3">
            <legend class="px-1 text-sm font-medium">Tax treatment</legend>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="tax_treatment" value="excluded" x-model="treatment">
                    VAT excluding
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="tax_treatment" value="included" x-model="treatment">
                    VAT including
                </label>
            </div>
            <p class="mt-2 text-xs text-muted">Excluding leaves tax out of the total. Including adds the VAT percentage and the Tax percentage you type to the line total. Blank means that percentage is not calculated. Type 0 if the rate is zero.</p>
            <div x-show="treatment === 'included'" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="vat_rate_percent" class="block text-sm font-medium">VAT %</label>
                    <input id="vat_rate_percent" name="vat_rate_percent" inputmode="decimal" autocomplete="off" value="{{ old('vat_rate_percent', $storedVat) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">Leave blank for no VAT. Nothing is selected for you.</p>
                </div>
                <div>
                    <label for="tax_rate_percent" class="block text-sm font-medium">Tax %</label>
                    <input id="tax_rate_percent" name="tax_rate_percent" inputmode="decimal" autocomplete="off" value="{{ old('tax_rate_percent', $storedTax) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">Generic tax percentage. This is not VAT, supplementary duty, VDS, or AIT. Leave blank for no tax.</p>
                </div>
                <div>
                    <label for="adjustment_amount" class="block text-sm font-medium">Adjustment</label>
                    <input id="adjustment_amount" name="adjustment_amount" inputmode="decimal" autocomplete="off" value="{{ old('adjustment_amount', $storedAdjustment) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">Optional. Leave blank for zero. A non-zero amount needs a reason and is not hidden inside VAT or tax.</p>
                </div>
                <div>
                    <label for="adjustment_reason" class="block text-sm font-medium">Reason for the adjustment</label>
                    <input id="adjustment_reason" name="adjustment_reason" value="{{ old('adjustment_reason', $revision?->adjustment_reason) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
            </div>
            <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3"><dt>Base</dt><dd data-tax-display="base_display">—</dd></div>
                <div class="flex justify-between gap-3"><dt>VAT</dt><dd data-tax-display="vat_display">—</dd></div>
                <div class="flex justify-between gap-3"><dt>Tax</dt><dd data-tax-display="tax_display">—</dd></div>
                <div class="flex justify-between gap-3"><dt>Adjustment</dt><dd data-tax-display="adjustment_display">—</dd></div>
                <div class="flex justify-between gap-3 font-medium sm:col-span-2"><dt>Total</dt><dd data-tax-display="total_display">—</dd></div>
            </dl>
            <p data-tax-note class="mt-2 text-xs text-muted"></p>
            <p class="mt-1 text-xs text-muted">The server calculates these amounts. The page does not. Quotation tax uses 2 decimal places, half up, the same rule as the line totals.</p>
        </fieldset>

        <div>
            <label for="terms" class="block text-sm font-medium">Terms</label>
            <textarea id="terms" name="terms" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('terms', $revision?->terms) }}</textarea>
        </div>
        <div>
            <label for="customer_notes" class="block text-sm font-medium">Notes for the customer</label>
            <textarea id="customer_notes" name="customer_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('customer_notes', $revision?->customer_notes) }}</textarea>
        </div>
        <div>
            <label for="internal_notes" class="block text-sm font-medium">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('internal_notes', $revision?->internal_notes) }}</textarea>
            <p class="mt-1 text-xs text-muted">Internal notes stay off the PDF and the customer link.</p>
        </div>

        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save</button>
    </form>
    <script>
        function quotationTaxForm() {
            return {
                treatment: @json($taxTreatment),
                preview: null,
                start() {
                    const root = this.$root;
                    this.preview = window.createQuotationTaxPreview({
                        read: () => this.read(root),
                        send: (snapshot, signal) => this.send(root, snapshot, signal),
                        apply: (patch) => {
                            Object.entries(patch).forEach(([name, value]) => {
                                const el = root.querySelector('[data-tax-display="' + name + '"]');
                                if (el) el.textContent = value ?? '';
                            });
                        },
                        note: (text) => {
                            const el = root.querySelector('[data-tax-note]');
                            if (el) el.textContent = text;
                        },
                    });
                    root.addEventListener('input', () => this.preview.schedule());
                    root.addEventListener('change', () => this.preview.schedule());
                    this.$nextTick(() => this.preview.flush());
                },
                read(root) {
                    const lines = [];
                    const descriptions = root.querySelectorAll('[name$="[description]"]');
                    descriptions.forEach((description) => {
                        const name = description.getAttribute('name') || '';
                        const prefix = name.slice(0, name.indexOf('[description]'));
                        const value = (selector) => {
                            const field = root.querySelector('[name="' + prefix + selector + '"]');
                            return field ? field.value : '';
                        };
                        lines.push({
                            description: description.value,
                            unit: value('[unit]'),
                            quantity: value('[quantity]'),
                            unit_price: value('[unit_price]'),
                        });
                    });
                    const field = (id) => {
                        const el = root.querySelector('#' + id);
                        return el ? el.value : '';
                    };
                    const described = lines.some((line) => line.description.trim() !== '');
                    // Choosing Other… before the unit is typed must not call the server.
                    const unitMissing = lines.some((line) => line.description.trim() !== '' && line.unit.trim() === '');
                    return {
                        skip: !described || unitMissing,
                        lines,
                        tax_treatment: this.treatment,
                        vat_rate_percent: field('vat_rate_percent'),
                        tax_rate_percent: field('tax_rate_percent'),
                        adjustment_amount: field('adjustment_amount'),
                        adjustment_reason: field('adjustment_reason'),
                    };
                },
                async send(root, snapshot, signal) {
                    const body = new FormData();
                    const token = root.querySelector('input[name="_token"]');
                    body.append('_token', token ? token.value : '');
                    body.append('tax_treatment', snapshot.tax_treatment);
                    body.append('vat_rate_percent', snapshot.vat_rate_percent);
                    body.append('tax_rate_percent', snapshot.tax_rate_percent);
                    body.append('adjustment_amount', snapshot.adjustment_amount);
                    body.append('adjustment_reason', snapshot.adjustment_reason);
                    snapshot.lines.forEach((line, index) => {
                        body.append('lines[' + index + '][description]', line.description);
                        body.append('lines[' + index + '][unit]', line.unit);
                        body.append('lines[' + index + '][quantity]', line.quantity);
                        body.append('lines[' + index + '][unit_price]', line.unit_price);
                    });
                    const response = await fetch(root.dataset.calculateUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body,
                        signal,
                    });
                    return { ok: response.ok, payload: await response.json() };
                },
            };
        }
    </script>
</x-layouts.app>
