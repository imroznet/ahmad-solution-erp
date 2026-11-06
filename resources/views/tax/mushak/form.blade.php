<x-layouts.app :title="'Edit '.$invoice->displayNumber()">
    <h1 class="text-xl font-semibold">Draft Mushak 6.3</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Type the VAT percentage yourself. It is not filled from a tax rule, a product, or 15%. A blank percentage does not calculate VAT. Final = base + VAT + adjustment. Supplementary duty, VDS, and AIT stay outside that formula.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('mushak.update', $invoice) }}" class="mt-4 space-y-4">
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
                <select name="calculation_precision" class="mt-1 w-full rounded border border-line px-2 py-1">
                    @foreach ([2, 3, 4] as $precision)
                        <option value="{{ $precision }}" @selected((int) old('calculation_precision', $invoice->calculation_precision) === $precision)>{{ $precision }} decimal places</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Rounding method
                <select name="rounding_method" class="mt-1 w-full rounded border border-line px-2 py-1">
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
                        <th class="px-3 py-2">Adjustment</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2">SD</th>
                        <th class="px-3 py-2">Final</th>
                        <th class="px-3 py-2">Driver</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lines as $index => $line)
                        <tr class="border-t border-line align-top">
                            <td class="px-3 py-2">
                                <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                <textarea name="lines[{{ $index }}][description]" class="w-48 rounded border border-line px-2 py-1" rows="2">{{ $line->description }}</textarea>
                                <p class="mt-1 text-xs text-muted">{{ $line->quantity }} {{ $line->unit }}</p>
                            </td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][base_amount]" value="{{ $line->base_amount }}" class="w-28 rounded border border-line px-2 py-1"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][vat_rate]" value="{{ $line->vat_rate_percent }}" class="w-24 rounded border border-line px-2 py-1" placeholder="Blank"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][adjustment_amount]" value="{{ $line->adjustment_amount }}" class="w-28 rounded border border-line px-2 py-1"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][adjustment_reason]" value="{{ $line->adjustment_reason }}" class="w-40 rounded border border-line px-2 py-1"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][supplementary_duty_amount]" value="{{ $line->supplementary_duty_amount }}" class="w-28 rounded border border-line px-2 py-1" placeholder="Blank"></td>
                            <td class="px-3 py-2"><input name="lines[{{ $index }}][final_amount]" value="{{ $line->final_amount }}" class="w-28 rounded border border-line px-2 py-1"></td>
                            <td class="px-3 py-2">
                                <select name="lines[{{ $index }}][driver]" class="rounded border border-line px-2 py-1">
                                    <option value="forward">From base and rate</option>
                                    <option value="reverse">From final amount</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Calculate and save</button>
    </form>
</x-layouts.app>
