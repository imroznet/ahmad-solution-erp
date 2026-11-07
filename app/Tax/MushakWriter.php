<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\MushakInvoice;
use App\Models\MushakInvoiceEvent;
use App\Models\MushakInvoiceLine;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\TaxRule;
use App\Models\User;
use App\Parties\PartyAuthorizer;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Prepares a Mushak 6.3 from an issued commercial invoice.
 * The commercial invoice is not changed. VAT uses only the percentage
 * typed for the line. A linked tax rule is a source reference.
 */
final class MushakWriter
{
    public function __construct(
        private readonly VatLineCalculator $calculator,
        private readonly CompanySnapshot $companies,
        private readonly MushakNumberAllocator $numbers,
        private readonly PartyAuthorizer $parties,
        private readonly AuditLogger $audit,
    ) {}

    public function start(User $actor, CommercialInvoice $invoice): MushakInvoice
    {
        $invoice->loadMissing(['lines', 'quotation']);
        $this->assertSourceInvoice($invoice);

        $mushak = DB::transaction(function () use ($actor, $invoice): MushakInvoice {
            CommercialInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
            $this->assertNoOpenMushak($invoice);

            $document = MushakInvoice::query()->create([
                'commercial_invoice_id' => $invoice->id,
                'quotation_id' => $invoice->quotation_id,
                'status' => 'draft',
                'buyer_source' => 'invoice',
                'party_id' => $invoice->quotation?->party_id,
                'document_date' => now()->timezone('Asia/Dhaka')->toDateString(),
                'destination' => $this->destinationFromInvoice($invoice),
                'calculation_precision' => 2,
                'rounding_method' => VatRounding::HALF_UP,
                'tax_review_status' => 'review_required',
                'currency_code' => 'BDT',
                'total_adjustment' => '0.0000',
                'created_by' => $actor->id,
            ]);

            foreach ($invoice->lines as $line) {
                $this->makeLine($document, $line);
            }

            $this->buyerFromInvoice($document, $invoice);
            $document->save();
            $this->event($document, MushakInvoiceEvent::PREPARED, $actor, null);
            $this->audit->record('mushak_prepared', $document, newValues: $this->payload($document), actor: $actor);

            return $document->refresh();
        });

        return $mushak;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $actor, MushakInvoice $mushak, array $input): MushakInvoice
    {
        if ($mushak->stringAttribute('status') !== 'draft') {
            throw new TaxRuleException('mushak', 'Only a draft Mushak 6.3 can be edited.');
        }

        return DB::transaction(function () use ($actor, $mushak, $input): MushakInvoice {
            // The pre-transaction check above is only a fast fail. Reload
            // under lock and recheck here: a concurrent issue could otherwise
            // commit after that check but before this update saves.
            $locked = MushakInvoice::query()->whereKey($mushak->id)->lockForUpdate()->firstOrFail();

            if ($locked->stringAttribute('status') !== 'draft') {
                throw new TaxRuleException('mushak', 'Only a draft Mushak 6.3 can be edited.');
            }

            $locked->loadMissing(['lines', 'commercialInvoice.lines', 'commercialInvoice.quotation']);
            $invoice = $locked->commercialInvoice;

            if (! $invoice instanceof CommercialInvoice) {
                throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice is missing.');
            }

            $precision = $this->precision($input['calculation_precision'] ?? null);
            $rounding = $this->rounding($input['rounding_method'] ?? null);
            $locked->calculation_precision = $precision;
            $locked->rounding_method = $rounding;
            $locked->document_date = $this->date($input['document_date'] ?? null);
            $locked->destination = $this->limited($input['destination'] ?? null, 'destination', 'Enter the destination of supply.', 200);
            $locked->responsible_name = $this->optionalLimited($input['responsible_name'] ?? null, 160);
            $locked->responsible_designation = $this->optionalLimited($input['responsible_designation'] ?? null, 120);
            $locked->internal_notes = $this->optionalText($input['internal_notes'] ?? null);
            $locked->tax_review_note = $this->optionalText($input['tax_review_note'] ?? null);
            $this->applyReview($locked, $actor, $input);
            $this->applyBuyer($locked, $invoice, $actor, $input);
            $this->applyRuleReference($locked, $input);
            $this->applyLines($locked, $actor, $input, $precision, $rounding);
            $this->applyTotals($locked);
            $locked->save();
            $this->event($locked, MushakInvoiceEvent::UPDATED, $actor, null);
            $this->audit->record('mushak_updated', $locked, newValues: $this->payload($locked), actor: $actor);

            return $locked->refresh();
        });
    }

    public function issue(User $actor, MushakInvoice $mushak): MushakInvoice
    {
        if ($mushak->stringAttribute('status') !== 'draft') {
            throw new TaxRuleException('mushak', 'Only a draft Mushak 6.3 can be issued.');
        }

        return DB::transaction(function () use ($actor, $mushak): MushakInvoice {
            $locked = MushakInvoice::query()->whereKey($mushak->id)->lockForUpdate()->firstOrFail();
            $locked->load('lines', 'commercialInvoice');
            $invoice = $locked->commercialInvoice;

            if (! $invoice instanceof CommercialInvoice) {
                throw new TaxRuleException('commercial_invoice_id', 'The commercial invoice is missing.');
            }

            CommercialInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
            $this->assertSourceInvoice($invoice);

            if (MushakInvoice::query()->where('commercial_invoice_id', $invoice->id)->where('status', 'issued')->lockForUpdate()->exists()) {
                throw new TaxRuleException('commercial_invoice_id', 'This commercial invoice already has an issued Mushak 6.3. Void that document before issuing another.');
            }

            if ($locked->lines->isEmpty()) {
                throw new TaxRuleException('lines', 'Mushak 6.3 needs the commercial invoice lines.');
            }

            foreach ($locked->lines as $line) {
                if ($line->getAttribute('vat_rate_percent') === null || $line->getAttribute('vat_amount') === null || $line->getAttribute('final_amount') === null) {
                    throw new TaxRuleException('vat_rate', 'Enter a VAT percentage for every line before issuing. Leave none blank. An explicit zero is allowed.');
                }

                if (! $this->isZero($line->decimalAttribute('adjustment_amount')) && $line->stringAttribute('adjustment_reason') === '') {
                    throw new TaxRuleException('adjustment_reason', 'Enter a reason for the rounding adjustment.');
                }
            }

            if ($locked->stringAttribute('buyer_name') === '') {
                throw new TaxRuleException('buyer_name', 'Enter the buyer name before issuing Mushak 6.3.');
            }

            $seller = $this->companies->capture();

            if ($seller['bin'] === null || $seller['bin'] === '') {
                throw new TaxRuleException('seller_bin', 'The company profile has no BIN. Add the registered BIN before issuing Mushak 6.3.');
            }

            if ($seller['address_lines'] === []) {
                throw new TaxRuleException('seller_address', 'The company profile has no registered or corporate address. Add that address before issuing Mushak 6.3.');
            }

            if ($locked->getAttribute('total_final') === null || ! $locked->amountsReconcile()) {
                throw new TaxRuleException('final_amount', 'The line totals do not reconcile to base + VAT + adjustment.');
            }

            $locked->seller_legal_name = $seller['legal_name'];
            $locked->seller_bin = $seller['bin'];
            $locked->seller_tin = $seller['tin'];
            $locked->seller_address = implode("\n", $seller['address_lines']);
            $locked->seller_phone = $seller['phone'];
            $locked->seller_email = $seller['email'];
            $locked->setAttribute('seller_snapshot', $seller);
            $locked->setAttribute('buyer_snapshot', $this->buyerSnapshot($locked));
            $locked->setAttribute('rule_snapshot', $this->ruleSnapshot($locked->taxRule));
            $locked->number = $this->numbers->allocate();
            $locked->status = 'issued';
            $locked->issued_at = now();
            $locked->issued_by = $actor->id;
            $locked->save();
            $this->event($locked, MushakInvoiceEvent::ISSUED, $actor, null);
            $this->audit->record('mushak_issued', $locked, newValues: $this->payload($locked), actor: $actor);

            return $locked->refresh();
        });
    }

    public function void(User $actor, MushakInvoice $mushak, string $reason): MushakInvoice
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw new TaxRuleException('void_reason', 'Enter a void reason.');
        }

        if ($mushak->stringAttribute('status') !== 'issued') {
            throw new TaxRuleException('mushak', 'Only an issued Mushak 6.3 can be voided.');
        }

        return DB::transaction(function () use ($actor, $mushak, $reason): MushakInvoice {
            $locked = MushakInvoice::query()->whereKey($mushak->id)->lockForUpdate()->firstOrFail();

            if ($locked->stringAttribute('status') !== 'issued') {
                throw new TaxRuleException('mushak', 'Only an issued Mushak 6.3 can be voided.');
            }

            $locked->status = 'void';
            $locked->voided_at = now();
            $locked->voided_by = $actor->id;
            $locked->void_reason = $reason;
            $locked->save();
            $this->event($locked, MushakInvoiceEvent::VOIDED, $actor, $reason);
            $this->audit->record('mushak_voided', $locked, newValues: $this->payload($locked), actor: $actor);

            return $locked->refresh();
        });
    }

    private function assertSourceInvoice(CommercialInvoice $invoice): void
    {
        if ($invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
            throw new TaxRuleException('commercial_invoice_id', 'Mushak 6.3 can only be prepared from an issued commercial invoice.');
        }
    }

    private function assertNoOpenMushak(CommercialInvoice $invoice): void
    {
        $open = MushakInvoice::query()
            ->where('commercial_invoice_id', $invoice->id)
            ->whereIn('status', ['draft', 'issued'])
            ->exists();

        if ($open) {
            throw new TaxRuleException('commercial_invoice_id', 'This commercial invoice already has a draft or issued Mushak 6.3.');
        }
    }

    private function makeLine(MushakInvoice $document, CommercialInvoiceLine $line): void
    {
        $description = $line->stringAttribute('description');
        MushakInvoiceLine::query()->create([
            'mushak_invoice_id' => $document->id,
            'commercial_invoice_line_id' => $line->id,
            'quotation_line_id' => $line->quotation_line_id,
            'delivery_challan_line_id' => $line->delivery_challan_line_id,
            'position' => $line->getAttribute('position'),
            'description' => $description,
            'source_description' => $description,
            'unit' => $line->stringAttribute('unit'),
            'quantity' => $line->decimalAttribute('quantity'),
            'source_unit_price' => $line->decimalAttribute('unit_price'),
            'source_line_total' => $line->decimalAttribute('line_total'),
            'base_amount' => $this->scaleSource($line->decimalAttribute('line_total')),
            'adjustment_amount' => '0.0000',
            'residual_applied' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyLines(MushakInvoice $mushak, User $actor, array $input, int $precision, string $rounding): void
    {
        $rows = $input['lines'] ?? null;

        if (! is_array($rows)) {
            throw new TaxRuleException('lines', 'Enter the line amounts.');
        }

        $byId = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;

            if (is_numeric($id)) {
                $byId[(int) $id] = $row;
            }
        }

        foreach ($mushak->lines as $line) {
            $row = $byId[(int) $line->id] ?? null;

            if (! is_array($row)) {
                throw new TaxRuleException('lines', 'Every commercial-invoice line has to stay on the Mushak.');
            }

            $description = $this->optionalText($row['description'] ?? null) ?? $line->stringAttribute('source_description');
            $result = $this->calculator->calculate(
                base: $this->inputString($row['base_amount'] ?? null, 'base_amount'),
                rate: $this->nullableString($row['vat_rate'] ?? null),
                adjustment: $this->inputString($row['adjustment_amount'] ?? '0', 'adjustment_amount'),
                final: $this->nullableString($row['final_amount'] ?? null),
                driver: is_string($row['driver'] ?? null) ? $row['driver'] : 'forward',
                precision: $precision,
                roundingMethod: $rounding,
            );

            if (! $this->isZero($result->adjustment) && $this->optionalText($row['adjustment_reason'] ?? null) === null) {
                throw new TaxRuleException('adjustment_reason', 'Enter a reason for a rounding adjustment that is not zero.');
            }

            $previousRate = $line->getAttribute('vat_rate_percent');
            $rateChanged = $this->rateChanged($previousRate, $result->rate);
            $adjustmentChanged = ! $this->sameAmount($line->decimalAttribute('adjustment_amount'), $result->adjustment);

            $line->description = $description;
            $line->setAttribute('base_amount', $result->base);
            $line->setAttribute('vat_rate_percent', $result->rate);
            $line->setAttribute('vat_amount', $result->vat);
            $line->setAttribute('forward_vat_amount', $result->forwardVat);
            $line->residual_applied = $result->residualApplied;
            $line->setAttribute('adjustment_amount', $result->adjustment);
            $line->adjustment_reason = $this->optionalLimited($row['adjustment_reason'] ?? null, 500);
            $line->setAttribute('supplementary_duty_amount', $this->optionalMoney($row['supplementary_duty_amount'] ?? null, $precision, $rounding));
            $line->setAttribute('final_amount', $result->final);
            $line->setAttribute('calculation_snapshot', $result->snapshot());

            if ($result->rate !== null && ($rateChanged || $line->getAttribute('rate_entered_by') === null)) {
                $line->rate_entered_by = $actor->id;
                $line->rate_entered_at = now();
            }

            if (! $this->isZero($result->adjustment) && ($adjustmentChanged || $line->getAttribute('adjustment_by') === null)) {
                $line->adjustment_by = $actor->id;
                $line->adjustment_at = now();
            }

            $line->save();
        }
    }

    private function applyTotals(MushakInvoice $mushak): void
    {
        $mushak->load('lines');
        $base = Decimal::zero();
        $adjustment = Decimal::zero();
        $vat = Decimal::zero();
        $final = Decimal::zero();
        $duty = Decimal::zero();
        $vatComplete = true;
        $finalComplete = true;
        $dutySeen = false;

        foreach ($mushak->lines as $line) {
            $base = $base->plus(Decimal::of($line->decimalAttribute('base_amount')));
            $adjustment = $adjustment->plus(Decimal::of($line->decimalAttribute('adjustment_amount')));
            $dutyAmount = $line->getAttribute('supplementary_duty_amount');

            if (is_string($dutyAmount) && $dutyAmount !== '') {
                $duty = $duty->plus(Decimal::of($dutyAmount));
                $dutySeen = true;
            }

            $vatAmount = $line->getAttribute('vat_amount');
            $finalAmount = $line->getAttribute('final_amount');

            if (! is_string($vatAmount) || $vatAmount === '') {
                $vatComplete = false;
            } else {
                $vat = $vat->plus(Decimal::of($vatAmount));
            }

            if (! is_string($finalAmount) || $finalAmount === '') {
                $finalComplete = false;
            } else {
                $final = $final->plus(Decimal::of($finalAmount));
            }
        }

        $precision = (int) $mushak->calculation_precision;
        $mushak->setAttribute('subtotal_base', $this->scaled($base, $precision));
        $mushak->setAttribute('total_adjustment', $this->scaled($adjustment, $precision));
        $mushak->setAttribute('total_vat', $vatComplete ? $this->scaled($vat, $precision) : null);
        $mushak->setAttribute('total_final', $finalComplete ? $this->scaled($final, $precision) : null);
        $mushak->setAttribute('total_supplementary_duty', $dutySeen ? $this->scaled($duty, $precision) : null);
        $mushak->setAttribute('calculation_snapshot', [
            'formula' => 'final = base + VAT + adjustment',
            'precision' => $precision,
            'rounding_method' => $mushak->stringAttribute('rounding_method'),
            'legal_rate_determined' => false,
            'supplementary_duty_excluded_from_vat' => true,
            'vds_excluded' => true,
            'ait_excluded' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyBuyer(MushakInvoice $mushak, CommercialInvoice $invoice, User $actor, array $input): void
    {
        $source = $input['buyer_source'] ?? 'invoice';

        if (! is_string($source) || ! in_array($source, ['invoice', 'party', 'manual'], true)) {
            throw new TaxRuleException('buyer_source', 'Choose the commercial-invoice buyer, an existing party, or a one-off buyer.');
        }

        if ($source === 'invoice') {
            $this->buyerFromInvoice($mushak, $invoice);

            return;
        }

        if ($source === 'party') {
            $partyId = $input['party_id'] ?? null;
            $party = is_numeric($partyId) ? Party::query()->find((int) $partyId) : null;

            if (! $party instanceof Party || ! $this->parties->canView($actor, $party)) {
                throw new TaxRuleException('party_id', 'Choose a party you are allowed to view.');
            }

            $party->loadMissing('addresses');
            $address = $party->addresses->first(fn (PartyAddress $row): bool => (bool) $row->is_default_billing && (bool) $row->is_active)
                ?? $party->addresses->first(fn (PartyAddress $row): bool => (bool) $row->is_active);
            $mushak->buyer_source = 'party';
            $mushak->party_id = $party->id;
            $mushak->buyer_name = $party->stringAttribute('legal_name');
            $mushak->buyer_bin = $this->nullableString($party->getAttribute('bin'));
            $mushak->buyer_tin = $this->nullableString($party->getAttribute('tin'));
            $mushak->buyer_address = $address instanceof PartyAddress ? implode("\n", $address->lines()) : null;
            $mushak->buyer_phone = $this->nullableString($party->getAttribute('phone'));
            $mushak->buyer_email = $this->nullableString($party->getAttribute('email'));

            return;
        }

        $mushak->buyer_source = 'manual';
        $mushak->party_id = null;
        $mushak->buyer_name = $this->limited($input['buyer_name'] ?? null, 'buyer_name', 'Enter the buyer name.', 200);
        $mushak->buyer_bin = $this->optionalLimited($input['buyer_bin'] ?? null, 30);
        $mushak->buyer_tin = $this->optionalLimited($input['buyer_tin'] ?? null, 30);
        $mushak->buyer_address = $this->optionalText($input['buyer_address'] ?? null);
        $mushak->buyer_phone = $this->optionalLimited($input['buyer_phone'] ?? null, 40);
        $mushak->buyer_email = $this->optionalLimited($input['buyer_email'] ?? null, 255);
    }

    private function buyerFromInvoice(MushakInvoice $mushak, CommercialInvoice $invoice): void
    {
        $invoice->loadMissing('quotation');
        $mushak->buyer_source = 'invoice';
        $mushak->party_id = $invoice->quotation?->party_id;
        $mushak->buyer_name = $invoice->stringAttribute('party_legal_name');
        $mushak->buyer_bin = $this->nullableString($invoice->getAttribute('party_bin'));
        $mushak->buyer_tin = $this->nullableString($invoice->getAttribute('party_tin'));
        $mushak->buyer_address = implode("\n", $invoice->addressLines('billing_address'));
        $mushak->buyer_phone = $this->nullableString($invoice->getAttribute('party_phone'));
        $mushak->buyer_email = $this->nullableString($invoice->getAttribute('party_email'));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyRuleReference(MushakInvoice $mushak, array $input): void
    {
        $raw = $input['tax_rule_id'] ?? null;

        if ($raw === null || $raw === '') {
            $mushak->tax_rule_id = null;

            return;
        }

        if (! is_numeric($raw)) {
            throw new TaxRuleException('tax_rule_id', 'Choose a tax rule reference or leave it blank.');
        }

        $rule = TaxRule::query()->find((int) $raw);

        if (! $rule instanceof TaxRule
            || $rule->stringAttribute('tax_type') !== TaxType::OUTPUT_VAT
            || $rule->stringAttribute('approval_status') !== 'approved'
            || $rule->stringAttribute('activity_status') !== 'active') {
            throw new TaxRuleException('tax_rule_id', 'The VAT reference must be an active approved output-VAT rule. It does not set the percentage.');
        }

        $rawDate = $mushak->getAttribute('document_date');
        $on = $rawDate instanceof \DateTimeInterface ? $rawDate->format('Y-m-d') : (is_string($rawDate) ? substr($rawDate, 0, 10) : '');
        $from = $rule->dateString('effective_from') ?? '';
        $to = $rule->dateString('effective_to');

        if ($on === '' || ! TaxRuleWindow::overlaps($from, $to, $on, $on)) {
            throw new TaxRuleException('tax_rule_id', 'That rule is not effective on the Mushak date. It was not used as a percentage.');
        }

        $mushak->tax_rule_id = $rule->id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyReview(MushakInvoice $mushak, User $actor, array $input): void
    {
        $confirmed = filter_var($input['tax_review_confirmed'] ?? false, FILTER_VALIDATE_BOOL);

        if ($confirmed) {
            $mushak->tax_review_status = 'treatment_confirmed';
            $mushak->tax_review_confirmed_at = now();
            $mushak->tax_review_confirmed_by = $actor->id;

            return;
        }

        $mushak->tax_review_status = 'review_required';
        $mushak->tax_review_confirmed_at = null;
        $mushak->tax_review_confirmed_by = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerSnapshot(MushakInvoice $mushak): array
    {
        return [
            'source' => $mushak->stringAttribute('buyer_source'),
            'party_id' => $mushak->party_id,
            'name' => $mushak->stringAttribute('buyer_name'),
            'bin' => $this->nullableString($mushak->getAttribute('buyer_bin')),
            'tin' => $this->nullableString($mushak->getAttribute('buyer_tin')),
            'address' => $this->nullableString($mushak->getAttribute('buyer_address')),
            'phone' => $this->nullableString($mushak->getAttribute('buyer_phone')),
            'email' => $this->nullableString($mushak->getAttribute('buyer_email')),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ruleSnapshot(?TaxRule $rule): ?array
    {
        if (! $rule instanceof TaxRule) {
            return null;
        }

        return [
            'tax_rule_id' => $rule->id,
            'version' => (int) $rule->getAttribute('version'),
            'tax_type' => $rule->stringAttribute('tax_type'),
            'rule_code' => $rule->stringAttribute('rule_code'),
            'legal_instrument' => $rule->stringAttribute('legal_instrument'),
            'section_reference' => $this->nullableString($rule->getAttribute('section_reference')),
            'source_url' => $this->nullableString($rule->getAttribute('source_url')),
            'effective_from' => $rule->dateString('effective_from'),
            'effective_to' => $rule->dateString('effective_to'),
            'supply_classification' => $rule->stringAttribute('supply_classification'),
            'rate_was_not_applied' => true,
            'documented_rate_ignored' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MushakInvoice $mushak): array
    {
        $mushak->loadMissing('lines');

        return [
            'number' => $mushak->stringAttribute('number'),
            'status' => $mushak->stringAttribute('status'),
            'commercial_invoice_id' => $mushak->commercial_invoice_id,
            'buyer_source' => $mushak->stringAttribute('buyer_source'),
            'party_id' => $mushak->party_id,
            'tax_review_status' => $mushak->stringAttribute('tax_review_status'),
            'calculation_precision' => $mushak->calculation_precision,
            'rounding_method' => $mushak->stringAttribute('rounding_method'),
            'subtotal_base' => $mushak->getAttribute('subtotal_base'),
            'total_vat' => $mushak->getAttribute('total_vat'),
            'total_adjustment' => $mushak->getAttribute('total_adjustment'),
            'total_final' => $mushak->getAttribute('total_final'),
            'lines' => $mushak->lines->map(fn (MushakInvoiceLine $line): array => [
                'id' => $line->id,
                'base_amount' => $line->getAttribute('base_amount'),
                'vat_rate_percent' => $line->getAttribute('vat_rate_percent'),
                'vat_amount' => $line->getAttribute('vat_amount'),
                'adjustment_amount' => $line->getAttribute('adjustment_amount'),
                'final_amount' => $line->getAttribute('final_amount'),
            ])->all(),
        ];
    }

    private function event(MushakInvoice $mushak, string $event, User $actor, ?string $remarks): void
    {
        MushakInvoiceEvent::query()->create([
            'mushak_invoice_id' => $mushak->id,
            'event' => $event,
            'actor_id' => $actor->id,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }

    private function destinationFromInvoice(CommercialInvoice $invoice): string
    {
        $lines = $invoice->addressLines('shipping_address');

        if ($lines === []) {
            $lines = $invoice->addressLines('billing_address');
        }

        $text = trim(implode(', ', $lines));

        return $text !== '' ? mb_substr($text, 0, 200) : 'Buyer address was not on the commercial invoice';
    }

    private function scaleSource(string $amount): string
    {
        return (string) Decimal::of($amount)->toScale(4, RoundingMode::Unnecessary);
    }

    private function scaled(Decimal $amount, int $precision): string
    {
        return (string) $amount->toScale($precision, RoundingMode::Unnecessary);
    }

    private function optionalMoney(mixed $value, int $precision, string $rounding): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new TaxRuleException('supplementary_duty_amount', 'Enter the supplementary duty amount or leave it blank.');
        }

        try {
            $parsed = Decimal::of(trim((string) $value))->toScale($precision, VatRounding::mode($rounding));
        } catch (\InvalidArgumentException) {
            throw new TaxRuleException('supplementary_duty_amount', 'Enter the supplementary duty amount as a decimal.');
        }

        if ($parsed->isNegative()) {
            throw new TaxRuleException('supplementary_duty_amount', 'Supplementary duty cannot be negative. It is not part of VAT.');
        }

        return (string) $parsed;
    }

    private function precision(mixed $value): int
    {
        if (! is_numeric($value) || ! in_array((int) $value, [2, 3, 4], true)) {
            throw new TaxRuleException('calculation_precision', 'Choose a calculation precision of 2, 3, or 4 decimal places.');
        }

        return (int) $value;
    }

    private function rounding(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, VatRounding::ALL, true)) {
            throw new TaxRuleException('rounding_method', 'Choose a rounding method.');
        }

        return $value;
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || date('Y-m-d', strtotime($value)) !== $value) {
            throw new TaxRuleException('document_date', 'Enter the issue date.');
        }

        return $value;
    }

    private function rateChanged(mixed $previous, ?string $next): bool
    {
        if ($next === null) {
            return $previous !== null;
        }

        if (! is_string($previous) || $previous === '') {
            return true;
        }

        return Decimal::of($previous)->compare(Decimal::of($next)) !== 0;
    }

    private function sameAmount(string $left, string $right): bool
    {
        return Decimal::of($left)->compare(Decimal::of($right)) === 0;
    }

    private function isZero(string $amount): bool
    {
        return Decimal::of($amount)->isZero();
    }

    private function inputString(mixed $value, string $field): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new TaxRuleException($field, 'Enter the amount.');
        }

        return trim($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function limited(mixed $value, string $field, string $message, int $limit): string
    {
        $text = $this->optionalLimited($value, $limit);

        if ($text === null) {
            throw new TaxRuleException($field, $message);
        }

        return $text;
    }

    private function optionalLimited(mixed $value, int $limit): ?string
    {
        $text = $this->optionalText($value);

        if ($text !== null && mb_strlen($text) > $limit) {
            throw new TaxRuleException('mushak', 'One of the text fields is too long.');
        }

        return $text;
    }

    private function optionalText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
