<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;

final class InvoiceWriter
{
    public function __construct(
        private readonly DocumentLock $locks,
        private readonly DocumentLines $lines,
        private readonly DocumentCustomerSnapshot $customers,
        private readonly DocumentText $text,
        private readonly DocumentTransaction $transactions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input): CommercialInvoice
    {
        /** @var CommercialInvoice $invoice */
        $invoice = $this->transactions->run(function () use ($actor, $input): CommercialInvoice {
            $quotationId = $this->text->id($input, 'quotation_id');
            [$quotation, $revision] = $this->locks->accepted($quotationId);
            $challanIds = $this->lines->challanIdsForInvoice($quotationId, $input);
            $this->locks->challansInOrder($challanIds);
            $prepared = $this->lines->forInvoice($revision, $quotationId, $input, null);
            $total = $this->lines->total($prepared, $quotationId, $revision->decimalAttribute('total'), null);
            $invoice = CommercialInvoice::query()->create([
                'number' => null,
                'quotation_id' => $quotation->getKey(),
                'quotation_revision_id' => $revision->getKey(),
                'status' => DocumentStatus::DRAFT,
                'invoice_date' => $this->text->date($input['invoice_date'] ?? null, 'invoice_date'),
                ...$this->customers->fromRevision($revision),
                'customer_notes' => $this->text->nullable($input, 'customer_notes', 5000),
                'internal_notes' => $this->text->nullable($input, 'internal_notes', 5000),
                'tax_notice' => $this->notice(),
                'tax_treatment' => 'excluded',
                'subtotal' => $total,
                'total' => $total,
                'currency_code' => Money::DEFAULT_CURRENCY,
                'created_by' => $actor->getKey(),
            ]);
            [, $current] = $this->replaceLines($invoice, $actor, $prepared);
            $this->audit->record('commercial_invoice_created', $invoice, newValues: $this->payload($invoice, $current), actor: $actor);

            return $invoice;
        }, 'commercial_invoices');

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(CommercialInvoice $invoice, User $actor, array $input): CommercialInvoice
    {
        /** @var CommercialInvoice $updated */
        $updated = $this->transactions->run(function () use ($invoice, $actor, $input): CommercialInvoice {
            [$quotation, $revision] = $this->locks->accepted((int) $invoice->quotation_id);
            $quotationId = (int) $quotation->getKey();
            $challanIds = $this->lines->challanIdsForInvoice($quotationId, $input);
            $this->locks->challansInOrder($challanIds);
            $invoice = $this->locks->invoice((int) $invoice->getKey());
            $this->assertDraft($invoice);

            if ((int) $invoice->quotation_id !== $quotationId || (int) $invoice->quotation_revision_id !== (int) $revision->getKey()) {
                throw new DocumentRuleException('quotation_id', 'The invoice must use the accepted revision of the quotation.');
            }

            $prepared = $this->lines->forInvoice($revision, $quotationId, $input, (int) $invoice->getKey());
            $total = $this->lines->total($prepared, $quotationId, $revision->decimalAttribute('total'), (int) $invoice->getKey());
            $invoice->fill([
                'invoice_date' => $this->text->date($input['invoice_date'] ?? null, 'invoice_date'),
                'customer_notes' => $this->text->nullable($input, 'customer_notes', 5000),
                'internal_notes' => $this->text->nullable($input, 'internal_notes', 5000),
                'subtotal' => $total,
                'total' => $total,
            ])->save();
            [$superseded, $current] = $this->replaceLines($invoice, $actor, $prepared);
            $this->audit->record(
                'commercial_invoice_updated',
                $invoice,
                oldValues: ['lines' => $superseded],
                newValues: $this->payload($invoice->refresh(), $current, $superseded),
                actor: $actor,
            );

            return $invoice;
        }, 'commercial_invoices');

        return $updated;
    }

    /**
     * The previous rows stay in the table. They are marked superseded and are
     * left out of the current-line relation.
     *
     * @param  list<array{delivery_challan_line_id: int, quotation_line_id: int, position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>  $lines
     * @return array{0: list<array<string, int|string>>, 1: list<array<string, int|string>>}
     */
    private function replaceLines(CommercialInvoice $invoice, User $actor, array $lines): array
    {
        $this->assertDraft($invoice);
        $superseded = [];

        foreach ($invoice->lines()->get() as $existing) {
            $superseded[] = $this->snapshot($existing);
            $existing->setAttribute('superseded_at', now());
            $existing->setAttribute('superseded_by', $actor->getKey());
            $existing->save();
        }

        $current = [];

        foreach ($lines as $line) {
            $created = $invoice->lines()->create($line);
            $current[] = $this->snapshot($created);
        }

        return [$superseded, $current];
    }

    /**
     * @return array<string, int|string>
     */
    private function snapshot(CommercialInvoiceLine $line): array
    {
        return [
            'id' => (int) $line->getKey(),
            'commercial_invoice_id' => (int) $line->getAttribute('commercial_invoice_id'),
            'delivery_challan_line_id' => (int) $line->getAttribute('delivery_challan_line_id'),
            'quotation_line_id' => (int) $line->getAttribute('quotation_line_id'),
            'position' => (int) $line->getAttribute('position'),
            'description' => $line->stringAttribute('description'),
            'unit' => $line->stringAttribute('unit'),
            'quantity' => $line->decimalAttribute('quantity'),
            'unit_price' => $line->decimalAttribute('unit_price'),
            'line_total' => $line->decimalAttribute('line_total'),
        ];
    }

    private function assertDraft(CommercialInvoice $invoice): void
    {
        if ($invoice->stringAttribute('status') !== DocumentStatus::DRAFT) {
            throw new DocumentRuleException('invoice', 'Only a draft invoice can be edited.');
        }
    }

    private function notice(): string
    {
        $notice = config('asl.commercial_invoices.notice');

        return is_string($notice) && $notice !== ''
            ? $notice
            : 'This is a commercial invoice. Tax is excluded and is not calculated. This document is not a tax invoice or a Mushak form.';
    }

    /**
     * @param  list<array<string, int|string>>  $lines
     * @param  list<array<string, int|string>>  $superseded
     * @return array<string, mixed>
     */
    private function payload(CommercialInvoice $invoice, array $lines, array $superseded = []): array
    {
        return [
            'number' => $invoice->displayNumber(),
            'quotation_id' => $invoice->quotation_id,
            'quotation_revision_id' => $invoice->quotation_revision_id,
            'status' => $invoice->stringAttribute('status'),
            'total' => $invoice->decimalAttribute('total'),
            'currency_code' => $invoice->stringAttribute('currency_code'),
            'tax_treatment' => $invoice->stringAttribute('tax_treatment'),
            'line_count' => count($lines),
            'line_ids' => array_map(fn (array $line): int => (int) $line['id'], $lines),
            'superseded_line_ids' => array_map(fn (array $line): int => (int) $line['id'], $superseded),
            'lines' => $lines,
        ];
    }
}
