<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
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
            $this->replaceLines($invoice, $prepared);
            $this->audit->record('commercial_invoice_created', $invoice, newValues: $this->payload($invoice), actor: $actor);

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
            $this->replaceLines($invoice, $prepared);
            $this->audit->record('commercial_invoice_updated', $invoice, newValues: $this->payload($invoice->refresh()), actor: $actor);

            return $invoice;
        }, 'commercial_invoices');

        return $updated;
    }

    /**
     * @param  list<array{delivery_challan_line_id: int, quotation_line_id: int, position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>  $lines
     */
    private function replaceLines(CommercialInvoice $invoice, array $lines): void
    {
        $this->assertDraft($invoice);

        foreach ($invoice->lines()->get() as $existing) {
            if ($existing->delete() !== true) {
                throw new DocumentRuleException('lines', 'Draft lines could not be replaced.');
            }
        }

        foreach ($lines as $line) {
            $invoice->lines()->create($line);
        }
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
     * @return array<string, mixed>
     */
    private function payload(CommercialInvoice $invoice): array
    {
        return [
            'number' => $invoice->displayNumber(),
            'quotation_id' => $invoice->quotation_id,
            'quotation_revision_id' => $invoice->quotation_revision_id,
            'status' => $invoice->stringAttribute('status'),
            'total' => $invoice->decimalAttribute('total'),
            'currency_code' => $invoice->stringAttribute('currency_code'),
            'tax_treatment' => $invoice->stringAttribute('tax_treatment'),
            'line_count' => $invoice->lines()->count(),
        ];
    }
}
