<?php

namespace App\Documents;

use App\Models\CommercialInvoiceLine;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanEvent;
use App\Models\User;
use App\Services\AuditLogger;

final class ChallanWorkflow
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

    public function issue(DeliveryChallan $challan, User $actor): DeliveryChallan
    {
        /** @var DeliveryChallan $issued */
        $issued = $this->transactions->run(function () use ($challan, $actor): DeliveryChallan {
            [$quotation, $revision] = $this->locks->accepted((int) $challan->quotation_id);
            $challan = $this->locks->challan((int) $challan->getKey());
            $this->assertStatus($challan, DocumentStatus::DRAFT, 'Only a draft challan can be issued.');

            if ((int) $challan->quotation_id !== (int) $quotation->getKey() || (int) $challan->quotation_revision_id !== (int) $revision->getKey()) {
                throw new DocumentRuleException('challan', 'The challan must use the accepted revision of the quotation.');
            }

            $challan->load('lines');
            $input = ['lines' => []];

            foreach ($challan->lines as $line) {
                $input['lines'][] = [
                    'quotation_line_id' => (int) $line->quotation_line_id,
                    'quantity' => $line->decimalAttribute('quantity'),
                ];
            }

            $this->lines->forChallan($revision, $input, (int) $challan->getKey());
            $challan->fill([
                'number' => $this->numbers->allocate(DocumentNumberAllocator::CHALLAN),
                'company_snapshot' => $this->companies->capture(),
                'issued_at' => now(),
                'issued_by' => $actor->getKey(),
                'status' => DocumentStatus::ISSUED,
            ])->save();
            $this->event($challan, DeliveryChallanEvent::ISSUED, $actor, null, null);
            $this->audit->record('delivery_challan_issued', $challan, newValues: $this->payload($challan), actor: $actor);

            return $challan;
        }, 'delivery_challans');

        return $issued;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function dispatch(DeliveryChallan $challan, User $actor, array $input): void
    {
        $this->transactions->run(function () use ($challan, $actor, $input): void {
            $this->locks->header((int) $challan->quotation_id);
            $challan = $this->locks->challan((int) $challan->getKey());
            $this->assertStatus($challan, DocumentStatus::ISSUED, 'Record dispatch only for an issued challan.');

            if ($challan->dispatched_at !== null) {
                throw new DocumentRuleException('challan', 'Dispatch has already been recorded.');
            }

            $recipient = $this->text->nullable($input, 'recipient_name', 160);
            $remarks = $this->text->nullable($input, 'remarks', 2000);
            $challan->fill([
                'dispatched_at' => now(),
                'dispatched_by' => $actor->getKey(),
            ])->save();
            $this->event($challan, DeliveryChallanEvent::DISPATCHED, $actor, $recipient, $remarks);
            $this->audit->record('delivery_challan_dispatched', $challan, newValues: [
                ...$this->payload($challan),
                'recipient_name' => $recipient,
            ], actor: $actor);
        }, 'delivery_challans');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function acknowledge(DeliveryChallan $challan, User $actor, array $input): void
    {
        $this->transactions->run(function () use ($challan, $actor, $input): void {
            $this->locks->header((int) $challan->quotation_id);
            $challan = $this->locks->challan((int) $challan->getKey());
            $this->assertStatus($challan, DocumentStatus::ISSUED, 'Record acknowledgment only for an issued challan.');

            if ($challan->acknowledged_at !== null) {
                throw new DocumentRuleException('challan', 'Acknowledgment has already been recorded.');
            }

            $name = $this->text->required($input['acknowledgment_name'] ?? null, 'acknowledgment_name', 160);
            $remarks = $this->text->nullable($input, 'remarks', 2000);
            $challan->fill([
                'acknowledged_at' => now(),
                'acknowledged_by' => $actor->getKey(),
                'acknowledgment_name' => $name,
            ])->save();
            $this->event($challan, DeliveryChallanEvent::ACKNOWLEDGED, $actor, $name, $remarks);
            $this->audit->record('delivery_challan_acknowledged', $challan, newValues: [
                ...$this->payload($challan),
                'acknowledgment_name' => $name,
            ], actor: $actor);
        }, 'delivery_challans');
    }

    public function void(DeliveryChallan $challan, User $actor, string $reason): void
    {
        $this->transactions->run(function () use ($challan, $actor, $reason): void {
            $this->locks->header((int) $challan->quotation_id);
            $challan = $this->locks->challan((int) $challan->getKey());

            if (! in_array($challan->stringAttribute('status'), [DocumentStatus::DRAFT, DocumentStatus::ISSUED], true)) {
                throw new DocumentRuleException('challan', 'This challan is already voided.');
            }

            $challan->load('lines');
            $lineIds = [];

            foreach ($challan->lines as $line) {
                $lineIds[] = (int) $line->getKey();
            }

            $invoiceIds = $lineIds === [] ? [] : CommercialInvoiceLine::query()
                ->whereIn('delivery_challan_line_id', $lineIds)
                ->orderBy('commercial_invoice_id')
                ->pluck('commercial_invoice_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $invoices = $this->locks->invoicesInOrder($invoiceIds);

            foreach ($invoices as $invoice) {
                if ($invoice->stringAttribute('status') !== DocumentStatus::VOIDED) {
                    throw new DocumentRuleException('challan', 'Void the commercial invoices that use this challan before voiding the challan.');
                }
            }

            $voidReason = $this->text->required($reason, 'void_reason', 1000);
            $challan->fill([
                'status' => DocumentStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->getKey(),
                'void_reason' => $voidReason,
            ])->save();
            $this->event($challan, DeliveryChallanEvent::VOIDED, $actor, null, $voidReason);
            $this->audit->record('delivery_challan_voided', $challan, newValues: [
                ...$this->payload($challan),
                'void_reason' => $voidReason,
            ], actor: $actor);
        }, 'delivery_challans');
    }

    private function assertStatus(DeliveryChallan $challan, string $status, string $message): void
    {
        if ($challan->stringAttribute('status') !== $status) {
            throw new DocumentRuleException('challan', $message);
        }
    }

    private function event(DeliveryChallan $challan, string $event, User $actor, ?string $recipient, ?string $remarks): void
    {
        $challan->events()->create([
            'event' => $event,
            'actor_id' => $actor->getKey(),
            'recipient_name' => $recipient,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DeliveryChallan $challan): array
    {
        return [
            'number' => $challan->displayNumber(),
            'quotation_id' => $challan->quotation_id,
            'quotation_revision_id' => $challan->quotation_revision_id,
            'status' => $challan->stringAttribute('status'),
        ];
    }
}
