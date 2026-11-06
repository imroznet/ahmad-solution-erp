<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceEvent;
use App\Models\User;
use App\Services\AuditLogger;

final class InvoiceWorkflow
{
    public function __construct(
        private readonly DocumentLock $locks,
        private readonly DocumentLines $lines,
        private readonly DocumentNumberAllocator $numbers,
        private readonly CompanySnapshot $companies,
        private readonly DocumentText $text,
        private readonly DocumentTransaction $transactions,
        private readonly AuditLogger $audit,
    ) {}

    public function issue(CommercialInvoice $invoice, User $actor): CommercialInvoice
    {
        /** @var CommercialInvoice $issued */
        $issued = $this->transactions->run(function () use ($invoice, $actor): CommercialInvoice {
            [$quotation, $revision] = $this->locks->accepted((int) $invoice->quotation_id);
            $quotationId = (int) $quotation->getKey();
            $input = $this->lines->invoiceInputFromLines($invoice);
            $challanIds = $this->lines->challanIdsForInvoice($quotationId, $input);
            $this->locks->challansInOrder($challanIds);
            $invoice = $this->locks->invoice((int) $invoice->getKey());
            $this->assertDraft($invoice);

            if ((int) $invoice->quotation_id !== $quotationId || (int) $invoice->quotation_revision_id !== (int) $revision->getKey()) {
                throw new DocumentRuleException('invoice', 'The invoice must use the accepted revision of the quotation.');
            }
            $prepared = $this->lines->forInvoice($revision, $quotationId, $input, (int) $invoice->getKey());
            $total = $this->lines->total($prepared, $quotationId, $revision->decimalAttribute('total'), (int) $invoice->getKey());
            $this->reprice($invoice, $prepared, $total);
            $invoice->fill([
                'number' => $this->numbers->allocate(DocumentNumberAllocator::INVOICE),
                'company_snapshot' => $this->companies->capture(),
                'tax_notice' => $this->notice(),
                'issued_at' => now(),
                'issued_by' => $actor->getKey(),
                'status' => DocumentStatus::ISSUED,
            ])->save();
            $invoice->events()->create([
                'event' => CommercialInvoiceEvent::ISSUED,
                'actor_id' => $actor->getKey(),
                'remarks' => null,
                'created_at' => now(),
            ]);
            $this->audit->record('commercial_invoice_issued', $invoice, newValues: $this->payload($invoice), actor: $actor);

            return $invoice;
        }, 'commercial_invoices');

        return $issued;
    }

    public function void(CommercialInvoice $invoice, User $actor, string $reason): void
    {
        $this->transactions->run(function () use ($invoice, $actor, $reason): void {
            $this->locks->header((int) $invoice->quotation_id);
            $invoice = $this->locks->invoice((int) $invoice->getKey());

            if (! in_array($invoice->stringAttribute('status'), [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true)) {
                throw new DocumentRuleException('invoice', 'This invoice is already voided.');
            }

            $voidReason = $this->text->required($reason, 'void_reason', 1000);
            $invoice->fill([
                'status' => DocumentStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->getKey(),
                'void_reason' => $voidReason,
            ])->save();
            $invoice->events()->create([
                'event' => CommercialInvoiceEvent::VOIDED,
                'actor_id' => $actor->getKey(),
                'remarks' => $voidReason,
                'created_at' => now(),
            ]);
            $this->audit->record('commercial_invoice_voided', $invoice, newValues: [
                ...$this->payload($invoice),
                'void_reason' => $voidReason,
            ], actor: $actor);
        }, 'commercial_invoices');
    }

    /**
     * Reprice while the invoice is still a draft, then the caller marks it issued.
     *
     * @param  list<array{delivery_challan_line_id: int, quotation_line_id: int, position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>  $lines
     */
    private function reprice(CommercialInvoice $invoice, array $lines, string $total): void
    {
        $this->assertDraft($invoice);
        $existing = [];

        foreach ($invoice->lines()->get() as $line) {
            $existing[(int) $line->delivery_challan_line_id] = $line;
        }

        if (count($existing) !== count($lines)) {
            throw new DocumentRuleException('lines', 'The invoice lines could not be repriced.');
        }

        foreach ($lines as $line) {
            $record = $existing[$line['delivery_challan_line_id']] ?? null;

            if ($record === null) {
                throw new DocumentRuleException('lines', 'The invoice lines could not be repriced.');
            }

            $record->fill([
                'description' => $line['description'],
                'unit' => $line['unit'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'position' => $line['position'],
            ])->save();
        }

        $invoice->fill([
            'subtotal' => $total,
            'total' => $total,
        ])->save();
    }

    private function assertDraft(CommercialInvoice $invoice): void
    {
        if ($invoice->stringAttribute('status') !== DocumentStatus::DRAFT) {
            throw new DocumentRuleException('invoice', 'Only a draft invoice can be issued.');
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
        ];
    }
}
