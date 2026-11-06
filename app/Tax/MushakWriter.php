<?php

namespace App\Tax;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\MushakDocument;
use App\Models\MushakDocumentEvent;
use App\Models\MushakDocumentLine;
use App\Models\Party;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Mushak 6.3 draft. VAT figures are stored as typed.
 * This class does not multiply a percentage and does not read a tax rule.
 */
final class MushakWriter
{
    public function __construct(
        private readonly TaxText $text,
        private readonly MushakFigures $figures,
        private readonly BuyerSnapshot $buyers,
        private readonly MushakAuthorizer $authorizer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): MushakDocument
    {
        return DB::transaction(function () use ($actor, $input): MushakDocument {
            $built = $this->build($actor, $input);
            $document = MushakDocument::query()->create([
                ...$built['header'],
                'status' => DocumentStatus::DRAFT,
                'tax_review_status' => TaxReviewStatus::REVIEW_REQUIRED,
                'manual_vat_notice' => MushakFieldMap::MANUAL_NOTICE,
                'currency_code' => 'BDT',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->insertLines($document, $built['lines']);
            $this->event($document, MushakDocumentEvent::CREATED, $actor, null);
            $this->audit->record('mushak_created', $document, [], $this->auditPayload($document), $actor);

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(MushakDocument $document, User $actor, array $input): MushakDocument
    {
        return DB::transaction(function () use ($document, $actor, $input): MushakDocument {
            $locked = $this->lock($document);

            if ($locked->stringAttribute('status') !== DocumentStatus::DRAFT) {
                throw new TaxRuleException('mushak', 'Only a draft Mushak 6.3 can be edited.');
            }

            $built = $this->build($actor, $input);
            $locked->fill($built['header']);
            $locked->manual_vat_notice = MushakFieldMap::MANUAL_NOTICE;
            $locked->updated_by = $actor->id;
            $locked->save();

            foreach ($locked->lines()->get() as $line) {
                $line->superseded_at = now();
                $line->superseded_by = $actor->id;
                $line->save();
            }

            $this->insertLines($locked, $built['lines']);
            $this->event($locked, MushakDocumentEvent::UPDATED, $actor, null);
            $this->audit->record('mushak_updated', $locked, [], $this->auditPayload($locked), $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function build(User $actor, array $input): array
    {
        $source = $input['buyer_source'] ?? null;

        if (! is_string($source) || ! isset(BuyerSource::options()[$source])) {
            throw new TaxRuleException('buyer_source', 'Choose an existing customer or type a one-off buyer.');
        }

        $invoice = $this->invoice($actor, $input['commercial_invoice_id'] ?? null);
        $buyer = $source === BuyerSource::PARTY
            ? $this->partyBuyer($input, $invoice)
            : $this->manualBuyer($input);
        $lines = $this->lines($actor, $input, $invoice);
        $totals = [
            'total_value' => TaxAmount::money($input['total_value'] ?? null, 'total_value'),
            'total_supplementary_duty' => TaxAmount::money($input['total_supplementary_duty'] ?? null, 'total_supplementary_duty'),
            'total_vat' => TaxAmount::money($input['total_vat'] ?? null, 'total_vat'),
            'total_inclusive' => TaxAmount::money($input['total_inclusive'] ?? null, 'total_inclusive'),
        ];
        $figureLines = array_map(fn (array $line): array => [
            'description' => $line['description'],
            'unit' => $line['unit'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'line_value' => $line['line_value'],
            'supplementary_duty' => $line['supplementary_duty'],
            'vat_amount' => $line['vat_amount'],
            'inclusive_value' => $line['inclusive_value'],
        ], $lines);
        $this->figures->assertDraft($figureLines, $totals);

        return [
            'header' => [
                'commercial_invoice_id' => $invoice?->id,
                'quotation_id' => $invoice?->quotation_id,
                'buyer_source' => $source,
                ...$buyer,
                'destination' => $this->text->nullable($input, 'destination', 300),
                'responsible_name' => $this->text->nullable($input, 'responsible_name', 200),
                'responsible_designation' => $this->text->nullable($input, 'responsible_designation', 120),
                'issue_date' => TaxAmount::date($input['issue_date'] ?? null, 'issue_date'),
                'issue_time' => TaxAmount::time($input['issue_time'] ?? null, 'issue_time'),
                ...$totals,
                'internal_notes' => $this->text->nullable($input, 'internal_notes', 5000),
            ],
            'lines' => $lines,
        ];
    }

    private function invoice(User $actor, mixed $id): ?CommercialInvoice
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! is_numeric($id)) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued commercial invoice.');
        }

        $invoice = CommercialInvoice::query()->find((int) $id);

        if (! $invoice instanceof CommercialInvoice || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued commercial invoice. A draft or voided invoice cannot be linked.');
        }

        if (! $this->authorizer->documents()->canViewInvoice($actor, $invoice)) {
            throw new TaxRuleException('commercial_invoice_id', 'Choose an issued commercial invoice you can view.');
        }

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{party_id: int, buyer_name: string, buyer_bin: ?string, buyer_tin: ?string, buyer_address: ?string, buyer_phone: ?string, buyer_email: ?string}
     */
    private function partyBuyer(array $input, ?CommercialInvoice $invoice): array
    {
        $partyId = $input['party_id'] ?? null;

        if (! is_numeric($partyId)) {
            throw new TaxRuleException('party_id', 'Choose a customer.');
        }

        $party = Party::query()->find((int) $partyId);

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole('customer')) {
            throw new TaxRuleException('party_id', 'Choose an active customer. A one-off name is not saved as a customer.');
        }

        if ($invoice instanceof CommercialInvoice) {
            $invoice->loadMissing('quotation');
            $quotationParty = $invoice->quotation?->getAttribute('party_id');

            if ((int) $quotationParty !== (int) $party->id) {
                throw new TaxRuleException('party_id', 'The customer must be the customer on the linked commercial invoice.');
            }
        }

        return $this->buyers->fromParty($party);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{party_id: null, buyer_name: ?string, buyer_bin: ?string, buyer_tin: ?string, buyer_address: ?string, buyer_phone: ?string, buyer_email: ?string}
     */
    private function manualBuyer(array $input): array
    {
        if (is_numeric($input['party_id'] ?? null)) {
            throw new TaxRuleException('party_id', 'A one-off buyer is not saved as a customer. Leave the customer blank.');
        }

        $email = $this->text->nullable($input, 'buyer_email', 255);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new TaxRuleException('buyer_email', 'Enter a valid email address, or leave it blank.');
        }

        return [
            'party_id' => null,
            'buyer_name' => $this->text->nullable($input, 'buyer_name', 200),
            'buyer_bin' => $this->text->nullable($input, 'buyer_bin', 30),
            'buyer_tin' => $this->text->nullable($input, 'buyer_tin', 30),
            'buyer_address' => $this->text->nullable($input, 'buyer_address', 1000),
            'buyer_phone' => $this->text->nullable($input, 'buyer_phone', 40),
            'buyer_email' => $email,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function lines(User $actor, array $input, ?CommercialInvoice $invoice): array
    {
        $rows = $input['lines'] ?? null;

        if (! is_array($rows)) {
            throw new TaxRuleException('lines', 'Add at least one line.');
        }

        $maximum = (int) config('asl.mushak.max_lines', 30);
        $lines = [];
        $position = 0;

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            $position++;

            if ($position > $maximum) {
                throw new TaxRuleException('lines', 'A Mushak 6.3 can contain at most '.$maximum.' lines.');
            }

            $description = $this->text->required($row['description'] ?? null, 'lines.'.$index.'.description', 1000);
            $rate = TaxAmount::percentage($row['vat_rate'] ?? null, 'lines.'.$index.'.vat_rate');
            $invoiceLineId = $this->invoiceLine($row['commercial_invoice_line_id'] ?? null, $invoice, 'lines.'.$index.'.commercial_invoice_line_id');
            $lines[] = [
                'position' => $position,
                'commercial_invoice_line_id' => $invoiceLineId,
                'quotation_line_id' => null,
                'delivery_challan_line_id' => null,
                'description' => $description,
                'unit' => $this->text->nullable($row, 'unit', 40),
                'quantity' => TaxAmount::quantity($row['quantity'] ?? null, 'lines.'.$index.'.quantity'),
                'unit_price' => TaxAmount::money($row['unit_price'] ?? null, 'lines.'.$index.'.unit_price'),
                'line_value' => TaxAmount::money($row['line_value'] ?? null, 'lines.'.$index.'.line_value'),
                'supplementary_duty' => TaxAmount::money($row['supplementary_duty'] ?? null, 'lines.'.$index.'.supplementary_duty'),
                'vat_rate' => $rate,
                'specific_tax_note' => $this->text->nullable($row, 'specific_tax_note', 120),
                'vat_amount' => TaxAmount::money($row['vat_amount'] ?? null, 'lines.'.$index.'.vat_amount'),
                'inclusive_value' => TaxAmount::money($row['inclusive_value'] ?? null, 'lines.'.$index.'.inclusive_value'),
                'vat_rate_entered_by' => $rate === null ? null : $actor->id,
                'vat_rate_entered_at' => $rate === null ? null : now(),
            ];
        }

        if ($lines === []) {
            throw new TaxRuleException('lines', 'Add at least one line. VAT is not added for you.');
        }

        $this->copyInvoiceLineLinks($lines, $invoice);

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach (['description', 'unit', 'quantity', 'unit_price', 'line_value', 'supplementary_duty', 'vat_rate', 'specific_tax_note', 'vat_amount', 'inclusive_value'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return false;
            }

            if (is_int($value)) {
                return false;
            }
        }

        return true;
    }

    private function invoiceLine(mixed $id, ?CommercialInvoice $invoice, string $field): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! $invoice instanceof CommercialInvoice || ! is_numeric($id)) {
            throw new TaxRuleException($field, 'Choose a line from the linked commercial invoice.');
        }

        $line = CommercialInvoiceLine::query()
            ->where('commercial_invoice_id', $invoice->id)
            ->whereKey((int) $id)
            ->first();

        if (! $line instanceof CommercialInvoiceLine) {
            throw new TaxRuleException($field, 'Choose a line from the linked commercial invoice.');
        }

        return (int) $line->id;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function copyInvoiceLineLinks(array &$lines, ?CommercialInvoice $invoice): void
    {
        if (! $invoice instanceof CommercialInvoice) {
            return;
        }

        $ids = array_values(array_filter(array_map(
            fn (array $line): int => (int) ($line['commercial_invoice_line_id'] ?? 0),
            $lines,
        )));

        if ($ids === []) {
            return;
        }

        $sources = CommercialInvoiceLine::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($lines as $index => $line) {
            $source = $sources->get((int) ($line['commercial_invoice_line_id'] ?? 0));

            if (! $source instanceof CommercialInvoiceLine) {
                continue;
            }

            $lines[$index]['quotation_line_id'] = $source->quotation_line_id;
            $lines[$index]['delivery_challan_line_id'] = $source->delivery_challan_line_id;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function insertLines(MushakDocument $document, array $lines): void
    {
        foreach ($lines as $line) {
            MushakDocumentLine::query()->create([
                ...$line,
                'mushak_document_id' => $document->id,
                'created_at' => now(),
            ]);
        }
    }

    private function lock(MushakDocument $document): MushakDocument
    {
        $locked = MushakDocument::query()->whereKey($document->id)->lockForUpdate()->first();

        if (! $locked instanceof MushakDocument) {
            throw new TaxRuleException('mushak', 'This Mushak 6.3 could not be locked.');
        }

        return $locked;
    }

    private function event(MushakDocument $document, string $event, User $actor, ?string $remarks): void
    {
        MushakDocumentEvent::query()->create([
            'mushak_document_id' => $document->id,
            'event' => $event,
            'actor_id' => $actor->id,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(MushakDocument $document): array
    {
        return [
            'number' => $document->nullableAttribute('number'),
            'status' => $document->stringAttribute('status'),
            'buyer_source' => $document->stringAttribute('buyer_source'),
            'buyer_name' => $document->nullableAttribute('buyer_name'),
            'commercial_invoice_id' => $document->commercial_invoice_id,
            'tax_review_status' => $document->stringAttribute('tax_review_status'),
            'rate_determined_by_erp' => false,
        ];
    }
}
