<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\DeliveryChallan;
use App\Models\MushakEvent;
use App\Models\MushakInvoice;
use App\Models\MushakLine;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Prepares and issues a Mushak 6.3 linked to one commercial invoice.
 * The commercial invoice is not changed. VAT is calculated only from the
 * percentage typed on each line.
 */
final class MushakWriter
{
    public function __construct(
        private readonly VatPriceCalculator $prices,
        private readonly TaxRuleMatcher $rules,
        private readonly CompanySnapshot $companies,
        private readonly MushakNumberAllocator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): MushakInvoice
    {
        return DB::transaction(function () use ($actor, $input): MushakInvoice {
            $commercial = $this->commercial($input);
            $invoice = new MushakInvoice($this->header($input, $commercial));
            $invoice->commercial_invoice_id = $commercial->id;
            $invoice->status = DocumentStatus::DRAFT;
            $invoice->created_by = $actor->id;
            $invoice->currency_code = 'BDT';
            $invoice->tax_review_status = 'pending';
            $invoice->legal_treatment_confirmed = false;
            $invoice->save();
            $calculated = $this->replaceLines($actor, $invoice, $commercial, $input, false);
            $this->storeTotals($invoice, $calculated);
            $this->audit->record('mushak_created', $invoice, newValues: $this->snapshot($invoice), actor: $actor);

            return $invoice->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $actor, MushakInvoice $invoice, array $input): MushakInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $input): MushakInvoice {
            if ($invoice->stringAttribute('status') !== DocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'An issued Mushak 6.3 cannot be edited. Void it and prepare another.');
            }

            $commercial = $invoice->commercialInvoice;

            if (! $commercial instanceof CommercialInvoice || (int) $commercial->getKey() !== (int) ($input['commercial_invoice_id'] ?? 0)) {
                throw new TaxRuleException('commercial_invoice_id', 'This draft stays linked to its commercial invoice.');
            }

            $before = $this->snapshot($invoice);
            $forceBase = $this->settingsChanged($invoice, $input);
            $invoice->fill($this->header($input, $commercial));
            $invoice->save();
            $calculated = $this->replaceLines($actor, $invoice, $commercial, $input, $forceBase);
            $this->storeTotals($invoice, $calculated);
            $this->audit->record('mushak_updated', $invoice, $before, $this->snapshot($invoice), $actor);

            return $invoice->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function issue(User $actor, MushakInvoice $invoice, array $input): MushakInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $input): MushakInvoice {
            if ($invoice->stringAttribute('status') !== DocumentStatus::DRAFT) {
                throw new TaxRuleException('status', 'Only a draft can be issued.');
            }

            $invoice->load('lines.commercialLine.challanLine.challan', 'commercialInvoice.quotation');
            $commercial = $invoice->commercialInvoice;

            if (! $commercial instanceof CommercialInvoice || $commercial->stringAttribute('status') !== DocumentStatus::ISSUED) {
                throw new TaxRuleException('commercial_invoice_id', 'Issue the commercial invoice before issuing Mushak 6.3. A voided commercial invoice cannot be used.');
            }

            if ($invoice->lines->isEmpty()) {
                throw new TaxRuleException('lines', 'Add at least one line.');
            }

            foreach ($invoice->lines as $index => $line) {
                if ($line->rateValue() === null || $line->vat_amount === null || $line->final_amount === null) {
                    throw new TaxRuleException('lines.'.$index.'.vat_rate', 'Enter a VAT percentage on every line before issue. Leave it blank only on a draft. Zero is allowed when you type 0.');
                }
            }

            $company = $this->companies->capture();

            if (! is_string($company['bin']) || trim($company['bin']) === '') {
                throw new TaxRuleException('company_bin', 'Add the company BIN on the company profile before issuing Mushak 6.3.');
            }

            if ($invoice->stringAttribute('buyer_name') === '' || $invoice->stringAttribute('buyer_address') === '') {
                throw new TaxRuleException('buyer_name', 'The buyer name and address are required.');
            }

            if (($input['legal_treatment_confirmed'] ?? null) !== '1' && ($input['legal_treatment_confirmed'] ?? null) !== 1 && ($input['legal_treatment_confirmed'] ?? null) !== true) {
                throw new TaxRuleException('legal_treatment_confirmed', 'Confirm that you accept this manual tax treatment before issue.');
            }

            $note = $this->text($input, 'legal_treatment_note', 2000);

            if ($note === '') {
                throw new TaxRuleException('legal_treatment_note', 'Record the basis on which you confirmed the treatment. This note stays off the customer PDF.');
            }

            $match = $this->rules->explain(
                TaxType::OUTPUT_VAT,
                $invoice->stringAttribute('supply_classification'),
                $invoice->dateAttribute('document_date') ?? '',
            );

            if ($match->ambiguous) {
                throw new TaxRuleException('supply_classification', $match->explanation);
            }

            $review = $this->text($input, 'review_reason', 2000);

            if ($match->version === null && $review === '') {
                throw new TaxRuleException('review_reason', 'No approved output-VAT rule matches this date and classification. Enter a review reason or approve a matching rule. No percentage was assumed.');
            }

            $notice = config('asl.mushak.rate_notice');
            $notice = is_string($notice) && $notice !== ''
                ? $notice
                : 'The VAT percentage on this document was entered by the issuer. It was not selected or certified by this system.';

            $before = $this->snapshot($invoice);
            $invoice->forceFill([
                'status' => DocumentStatus::ISSUED,
                'number' => $this->numbers->allocate(),
                'company_snapshot' => $company,
                'source_snapshot' => $this->sourceSnapshot($commercial),
                'tax_rule_version_id' => $match->version?->getKey(),
                'rule_explanation' => $match->explanation,
                'tax_review_status' => $match->version === null ? 'manual_review' : 'rule_matched',
                'legal_treatment_confirmed' => true,
                'legal_treatment_note' => $note,
                'review_reason' => $review === '' ? null : $review,
                'rate_notice' => $notice,
                'prepared_by_designation' => $this->nullable($input, 'prepared_by_designation', 120),
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ])->save();

            MushakEvent::query()->create([
                'mushak_invoice_id' => $invoice->id,
                'event' => MushakEvent::ISSUED,
                'actor_id' => $actor->id,
                'remarks' => $match->explanation,
                'created_at' => now(),
            ]);
            $this->audit->record('mushak_issued', $invoice, $before, $this->snapshot($invoice), $actor);

            return $invoice->refresh();
        }, 3);
    }

    public function void(User $actor, MushakInvoice $invoice, string $reason): MushakInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $reason): MushakInvoice {
            $reason = trim($reason);

            if ($invoice->stringAttribute('status') === DocumentStatus::VOIDED) {
                throw new TaxRuleException('void_reason', 'This Mushak 6.3 is already void.');
            }

            if ($reason === '' || mb_strlen($reason) > 500) {
                throw new TaxRuleException('void_reason', 'Enter a void reason of at most 500 characters.');
            }

            $before = $this->snapshot($invoice);
            $invoice->forceFill([
                'status' => DocumentStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();
            MushakEvent::query()->create([
                'mushak_invoice_id' => $invoice->id,
                'event' => MushakEvent::VOIDED,
                'actor_id' => $actor->id,
                'remarks' => $reason,
                'created_at' => now(),
            ]);
            $this->audit->record('mushak_voided', $invoice, $before, $this->snapshot($invoice), $actor);

            return $invoice->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<VatPrice>
     */
    private function replaceLines(User $actor, MushakInvoice $invoice, CommercialInvoice $commercial, array $input, bool $forceBase): array
    {
        $commercial->loadMissing('lines');
        $sources = [];

        foreach ($commercial->lines as $line) {
            $sources[(int) $line->getKey()] = $line;
        }

        $rows = $input['lines'] ?? null;

        if (! is_array($rows) || $rows === []) {
            throw new TaxRuleException('lines', 'Add at least one line from the commercial invoice.');
        }

        if (count($rows) > 50) {
            throw new TaxRuleException('lines', 'A Mushak 6.3 can have at most 50 lines.');
        }

        $previous = [];

        foreach ($invoice->lines as $line) {
            $previous[(int) $line->commercial_invoice_line_id] = $line;
        }

        $precision = (int) $invoice->calculation_precision;
        $method = $invoice->stringAttribute('rounding_method');
        $seen = [];
        $calculated = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw new TaxRuleException('lines.'.$index, 'Enter the line.');
            }

            $sourceId = (int) ($row['commercial_invoice_line_id'] ?? 0);
            $source = $sources[$sourceId] ?? null;

            if (! $source instanceof CommercialInvoiceLine) {
                throw new TaxRuleException('lines.'.$index.'.commercial_invoice_line_id', 'Choose a line from this commercial invoice.');
            }

            if (isset($seen[$sourceId])) {
                throw new TaxRuleException('lines.'.$index.'.commercial_invoice_line_id', 'That commercial invoice line is already on this Mushak.');
            }

            $seen[$sourceId] = true;
            $this->assertLineFree($sourceId, (int) $invoice->getKey(), $index);
            $existing = $previous[$sourceId] ?? null;
            $edited = $forceBase ? 'base' : $this->edited($row, $index);
            $price = $this->priceFor($row, $edited, $existing, $precision, $method, $index);
            $this->assertAdjustmentReason($price, $row, $index);
            $calculated[] = [$index, $source, $existing, $price, $row];
        }

        foreach ($invoice->lines as $line) {
            $line->forceFill([
                'superseded_at' => now(),
                'superseded_by' => $actor->id,
            ])->save();
        }

        foreach ($calculated as [$index, $source, $existing, $price, $row]) {
            $this->insertLine($actor, $invoice, $source, $existing, $price, $row, $index);
        }

        $prices = [];

        foreach ($calculated as [, , , $price]) {
            $prices[] = $price;
        }

        return $prices;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function priceFor(array $row, string $edited, ?MushakLine $existing, int $precision, string $method, int $index): VatPrice
    {
        try {
            $base = $this->cell($row, 'base_amount');
            $rate = $this->cell($row, 'vat_rate');
            $adjustment = $this->cell($row, 'adjustment_amount');
            $final = $this->cell($row, 'final_amount');

            if ($edited === 'final') {
                return $this->prices->fromFinal($final, $rate, $adjustment, $precision, $method);
            }

            if ($edited === 'adjustment' && $existing instanceof MushakLine && $existing->rateValue() !== null && $existing->vat_amount !== null) {
                return $this->prices->fromAdjustment(
                    $existing->decimalAttribute('base_amount'),
                    $existing->rateValue(),
                    $existing->decimalAttribute('vat_amount'),
                    $adjustment,
                    $precision,
                    $method,
                );
            }

            return $this->prices->fromBase($base, $rate, $adjustment === '' ? '0' : $adjustment, $precision, $method);
        } catch (TaxRuleException $exception) {
            throw new TaxRuleException('lines.'.$index.'.'.$exception->field, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertLine(
        User $actor,
        MushakInvoice $invoice,
        CommercialInvoiceLine $source,
        ?MushakLine $existing,
        VatPrice $price,
        array $row,
        int $index,
    ): void {
        $description = $this->text($row, 'description', 2000);
        $unit = $this->text($row, 'unit', 40);
        $quantity = $this->quantity($row, $index);

        if ($description === '' || $unit === '') {
            throw new TaxRuleException('lines.'.$index.'.description', 'Enter the description and unit from the commercial invoice. You can correct the wording.');
        }

        $reason = $this->text($row, 'adjustment_reason', 500);
        $rateChanged = ! $existing instanceof MushakLine || $existing->rateValue() !== $price->rate;
        $adjustmentChanged = ! $existing instanceof MushakLine
            || $existing->decimalAttribute('adjustment_amount') !== $price->adjustmentAmount
            || (string) $existing->adjustment_reason !== ($reason === '' ? '' : $reason);
        $rateActor = $actor->id;
        $rateAt = now();
        $adjustmentActor = $actor->id;
        $adjustmentAt = now();

        if (! $rateChanged) {
            $rateActor = $existing->rate_entered_by;
            $enteredAt = $existing->getAttribute('rate_entered_at');
            $rateAt = $enteredAt instanceof \DateTimeInterface ? $enteredAt : now();
        }

        if (! $adjustmentChanged) {
            $adjustmentActor = $existing->adjustment_updated_by;
            $updatedAt = $existing->getAttribute('adjustment_updated_at');
            $adjustmentAt = $updatedAt instanceof \DateTimeInterface ? $updatedAt : now();
        }

        MushakLine::query()->create([
            'mushak_invoice_id' => $invoice->id,
            'commercial_invoice_line_id' => $source->id,
            'position' => $index + 1,
            'description' => $description,
            'unit' => $unit,
            'quantity' => (string) $quantity,
            'base_amount' => $price->baseAmount,
            'vat_rate' => $price->rate,
            'vat_amount' => $price->vatAmount,
            'adjustment_amount' => $price->adjustmentAmount,
            'adjustment_reason' => $reason === '' ? null : $reason,
            'final_amount' => $price->finalAmount,
            'residual_amount' => $price->residualAmount,
            'calculation_origin' => $price->origin,
            'rate_entered_by' => $price->rate === null ? null : $rateActor,
            'rate_entered_at' => $price->rate === null ? null : $rateAt,
            'adjustment_updated_by' => $adjustmentActor,
            'adjustment_updated_at' => $adjustmentAt,
        ]);
    }

    /**
     * @param  list<VatPrice>  $prices
     */
    private function storeTotals(MushakInvoice $invoice, array $prices): void
    {
        $base = Decimal::zero();
        $vat = Decimal::zero();
        $adjustment = Decimal::zero();
        $final = Decimal::zero();
        $complete = true;

        foreach ($prices as $price) {
            $base = $base->plus(Decimal::of($price->baseAmount));
            $adjustment = $adjustment->plus(Decimal::of($price->adjustmentAmount));

            if ($price->vatAmount === null || $price->finalAmount === null) {
                $complete = false;

                continue;
            }

            $vat = $vat->plus(Decimal::of($price->vatAmount));
            $final = $final->plus(Decimal::of($price->finalAmount));
        }

        $precision = (int) $invoice->calculation_precision;
        $invoice->forceFill([
            'base_total' => (string) $base->toScale($precision),
            'adjustment_total' => (string) $adjustment->toScale($precision),
            'vat_total' => $complete ? (string) $vat->toScale($precision) : null,
            'final_total' => $complete ? (string) $final->toScale($precision) : null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function header(array $input, CommercialInvoice $commercial): array
    {
        $precision = $input['calculation_precision'] ?? null;
        $precision = is_numeric($precision) ? (int) $precision : 0;
        $method = $input['rounding_method'] ?? null;
        $method = is_string($method) ? $method : '';

        if (! in_array($precision, VatPriceCalculator::PRECISIONS, true)) {
            throw new TaxRuleException('calculation_precision', 'Choose a calculation precision of 2, 3, or 4 decimal places.');
        }

        if (! in_array($method, RoundingMethod::all(), true)) {
            throw new TaxRuleException('rounding_method', 'Choose a rounding method.');
        }

        $buyer = $this->buyer($input);
        $date = $input['document_date'] ?? null;
        $date = is_string($date) ? trim($date) : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new TaxRuleException('document_date', 'Enter the document date.');
        }

        return array_merge($buyer, [
            'document_date' => $date,
            'supply_destination' => $this->nullable($input, 'supply_destination', 200),
            'supply_classification' => $this->text($input, 'supply_classification', 80),
            'calculation_precision' => $precision,
            'rounding_method' => $method,
            'customer_notes' => $this->nullable($input, 'customer_notes', 2000),
            'internal_notes' => $this->nullable($input, 'internal_notes', 2000),
            'commercial_invoice_id' => $commercial->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buyer(array $input): array
    {
        $source = $input['buyer_source'] ?? 'party';
        $source = is_string($source) ? $source : 'party';

        if ($source === 'manual') {
            $name = $this->text($input, 'buyer_name', 200);
            $address = $this->text($input, 'buyer_address', 2000);

            if ($name === '' || $address === '') {
                throw new TaxRuleException('buyer_name', 'Enter the buyer name and address. Manual entry does not create a customer.');
            }

            return [
                'buyer_source' => 'manual',
                'party_id' => null,
                'buyer_name' => $name,
                'buyer_bin' => $this->nullable($input, 'buyer_bin', 40),
                'buyer_tin' => $this->nullable($input, 'buyer_tin', 40),
                'buyer_address' => $address,
                'buyer_phone' => $this->nullable($input, 'buyer_phone', 40),
                'buyer_email' => $this->nullable($input, 'buyer_email', 160),
            ];
        }

        $partyId = $input['party_id'] ?? null;
        $party = Party::query()->with(['roles', 'addresses'])->find(is_numeric($partyId) ? (int) $partyId : 0);

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active') {
            throw new TaxRuleException('party_id', 'Choose an active customer, or enter the buyer manually.');
        }

        $customer = $party->roles->contains(fn ($role): bool => $role->getAttribute('role') === 'customer');

        if (! $customer) {
            throw new TaxRuleException('party_id', 'Choose a customer. A supplier-only party is not a buyer on Mushak 6.3.');
        }

        $address = $party->addresses->where('is_active', true)->where('is_default_billing', true)->sortByDesc('id')->first()
            ?? $party->addresses->where('is_active', true)->sortByDesc('id')->first();

        if (! $address instanceof PartyAddress) {
            throw new TaxRuleException('party_id', 'This customer has no active address. Add one, or enter the buyer manually.');
        }

        return [
            'buyer_source' => 'party',
            'party_id' => $party->id,
            'buyer_name' => $party->stringAttribute('legal_name'),
            'buyer_bin' => $this->emptyToNull($party->stringAttribute('bin')),
            'buyer_tin' => $this->emptyToNull($party->stringAttribute('tin')),
            'buyer_address' => $this->addressText($address),
            'buyer_phone' => $this->emptyToNull($party->stringAttribute('phone')),
            'buyer_email' => $this->emptyToNull($party->stringAttribute('email')),
        ];
    }

    private function addressText(PartyAddress $address): string
    {
        $parts = [];

        foreach (['address_line_1', 'address_line_2', 'area', 'upazila', 'district', 'division', 'postcode', 'country'] as $key) {
            $value = $address->getAttribute($key);

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        if ($parts === []) {
            throw new TaxRuleException('party_id', 'This customer address is empty. Enter the buyer manually.');
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function commercial(array $input): CommercialInvoice
    {
        $id = $input['commercial_invoice_id'] ?? null;
        $invoice = CommercialInvoice::query()->find(is_numeric($id) ? (int) $id : 0);

        if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued commercial invoice. A draft or voided invoice cannot start a Mushak 6.3.');
        }

        return $invoice;
    }

    private function assertLineFree(int $lineId, ?int $exceptMushakId, int $index): void
    {
        $busy = MushakLine::query()
            ->where('commercial_invoice_line_id', $lineId)
            ->whereHas('invoice', function ($query) use ($exceptMushakId): void {
                $query->where('status', '!=', DocumentStatus::VOIDED);

                if ($exceptMushakId !== null) {
                    $query->whereKeyNot($exceptMushakId);
                }
            })
            ->exists();

        if ($busy) {
            throw new TaxRuleException('lines.'.$index.'.commercial_invoice_line_id', 'That commercial invoice line is already on a Mushak that has not been voided.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function settingsChanged(MushakInvoice $invoice, array $input): bool
    {
        $precision = is_numeric($input['calculation_precision'] ?? null) ? (int) $input['calculation_precision'] : 0;
        $method = is_string($input['rounding_method'] ?? null) ? $input['rounding_method'] : '';

        return (int) $invoice->calculation_precision !== $precision
            || $invoice->stringAttribute('rounding_method') !== $method;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function edited(array $row, int $index): string
    {
        $edited = $row['edited'] ?? 'base';
        $edited = is_string($edited) ? $edited : '';

        if (! in_array($edited, ['base', 'final', 'adjustment'], true)) {
            throw new TaxRuleException('lines.'.$index.'.edited', 'Choose whether this line was edited from the base, the final amount, or the adjustment.');
        }

        return $edited;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function quantity(array $row, int $index): Decimal
    {
        $value = $this->cell($row, 'quantity');

        if (! preg_match('/^\d{1,7}(\.\d{1,4})?$/', $value)) {
            throw new TaxRuleException('lines.'.$index.'.quantity', 'Enter a quantity with at most 4 decimal places.');
        }

        $quantity = Decimal::of($value);

        if ($quantity->isZero() || $quantity->compare(Decimal::of('9999999.9999')) > 0) {
            throw new TaxRuleException('lines.'.$index.'.quantity', 'Enter a quantity greater than zero.');
        }

        return $quantity->toScale(4);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertAdjustmentReason(VatPrice $price, array $row, int $index): void
    {
        if (Decimal::of($price->adjustmentAmount)->isZero()) {
            return;
        }

        if ($this->text($row, 'adjustment_reason', 500) === '') {
            throw new TaxRuleException('lines.'.$index.'.adjustment_reason', 'Enter a reason when the rounding adjustment is not zero.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceSnapshot(CommercialInvoice $invoice): array
    {
        $invoice->loadMissing(['lines.challanLine.challan', 'quotation']);
        $challans = [];

        foreach ($invoice->lines as $line) {
            $challan = $line->challanLine?->challan;

            if ($challan instanceof DeliveryChallan && $challan->stringAttribute('number') !== '') {
                $challans[$challan->stringAttribute('number')] = $challan->stringAttribute('number');
            }
        }

        return [
            'commercial_invoice_id' => $invoice->id,
            'commercial_invoice_number' => $invoice->stringAttribute('number'),
            'quotation_id' => $invoice->quotation_id,
            'quotation_number' => $invoice->quotation?->stringAttribute('number'),
            'challan_numbers' => array_values($challans),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(MushakInvoice $invoice): array
    {
        $invoice->load('lines');
        $lines = [];

        foreach ($invoice->lines as $line) {
            $entered = $line->getAttribute('rate_entered_at');
            $lines[] = [
                'commercial_invoice_line_id' => $line->commercial_invoice_line_id,
                'base_amount' => $line->base_amount,
                'vat_rate' => $line->vat_rate,
                'vat_amount' => $line->vat_amount,
                'adjustment_amount' => $line->adjustment_amount,
                'adjustment_reason' => $line->adjustment_reason,
                'final_amount' => $line->final_amount,
                'residual_amount' => $line->residual_amount,
                'calculation_origin' => $line->stringAttribute('calculation_origin'),
                'rate_entered_by' => $line->rate_entered_by,
                'rate_entered_at' => $entered instanceof \DateTimeInterface ? $entered->format(DATE_ATOM) : null,
            ];
        }

        return [
            'number' => $invoice->displayNumber(),
            'status' => $invoice->stringAttribute('status'),
            'commercial_invoice_id' => $invoice->commercial_invoice_id,
            'buyer_source' => $invoice->stringAttribute('buyer_source'),
            'buyer_name' => $invoice->stringAttribute('buyer_name'),
            'buyer_bin' => $invoice->stringAttribute('buyer_bin'),
            'calculation_precision' => $invoice->calculation_precision,
            'rounding_method' => $invoice->stringAttribute('rounding_method'),
            'base_total' => $invoice->base_total,
            'vat_total' => $invoice->vat_total,
            'adjustment_total' => $invoice->adjustment_total,
            'final_total' => $invoice->final_total,
            'tax_rule_version_id' => $invoice->tax_rule_version_id,
            'tax_review_status' => $invoice->stringAttribute('tax_review_status'),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function cell(array $input, string $field): string
    {
        $value = $input[$field] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function text(array $input, string $field, int $max): string
    {
        $value = $this->cell($input, $field);

        if (mb_strlen($value) > $max) {
            throw new TaxRuleException($field, 'Use at most '.$max.' characters.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function nullable(array $input, string $field, int $max): ?string
    {
        $value = $this->text($input, $field, $max);

        return $value === '' ? null : $value;
    }

    private function emptyToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
