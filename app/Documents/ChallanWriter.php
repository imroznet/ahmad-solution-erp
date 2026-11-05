<?php

namespace App\Documents;

use App\Models\DeliveryChallan;
use App\Models\User;
use App\Services\AuditLogger;

final class ChallanWriter
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
    public function create(User $actor, array $input): DeliveryChallan
    {
        /** @var DeliveryChallan $challan */
        $challan = $this->transactions->run(function () use ($actor, $input): DeliveryChallan {
            $quotationId = $this->text->id($input, 'quotation_id');
            [$quotation, $revision] = $this->locks->accepted($quotationId);
            $prepared = $this->lines->forChallan($revision, $input, null);
            $challan = DeliveryChallan::query()->create([
                'number' => null,
                'quotation_id' => $quotation->getKey(),
                'quotation_revision_id' => $revision->getKey(),
                'status' => DocumentStatus::DRAFT,
                'challan_date' => $this->text->date($input['challan_date'] ?? null, 'challan_date'),
                ...$this->deliveryFields($input),
                ...$this->customers->fromRevision($revision),
                'customer_notes' => $this->text->nullable($input, 'customer_notes', 5000),
                'internal_notes' => $this->text->nullable($input, 'internal_notes', 5000),
                'created_by' => $actor->getKey(),
            ]);
            $this->replaceLines($challan, $prepared);
            $this->audit->record('delivery_challan_created', $challan, newValues: $this->payload($challan), actor: $actor);

            return $challan;
        }, 'delivery_challans');

        return $challan;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(DeliveryChallan $challan, User $actor, array $input): DeliveryChallan
    {
        /** @var DeliveryChallan $updated */
        $updated = $this->transactions->run(function () use ($challan, $actor, $input): DeliveryChallan {
            [$quotation, $revision] = $this->locks->accepted((int) $challan->quotation_id);
            $challan = $this->locks->challan((int) $challan->getKey());
            $this->assertDraft($challan);

            if ((int) $challan->quotation_id !== (int) $quotation->getKey() || (int) $challan->quotation_revision_id !== (int) $revision->getKey()) {
                throw new DocumentRuleException('quotation_id', 'The challan must use the accepted revision of the quotation.');
            }

            $prepared = $this->lines->forChallan($revision, $input, (int) $challan->getKey());
            $challan->fill([
                'challan_date' => $this->text->date($input['challan_date'] ?? null, 'challan_date'),
                ...$this->deliveryFields($input),
                'customer_notes' => $this->text->nullable($input, 'customer_notes', 5000),
                'internal_notes' => $this->text->nullable($input, 'internal_notes', 5000),
            ])->save();
            $this->replaceLines($challan, $prepared);
            $this->audit->record('delivery_challan_updated', $challan, newValues: $this->payload($challan->refresh()), actor: $actor);

            return $challan;
        }, 'delivery_challans');

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, ?string>
     */
    private function deliveryFields(array $input): array
    {
        return [
            'delivery_location' => $this->text->nullable($input, 'delivery_location', 200),
            'recipient_name' => $this->text->nullable($input, 'recipient_name', 160),
            'recipient_phone' => $this->text->nullable($input, 'recipient_phone', 40),
            'vehicle_or_carrier' => $this->text->nullable($input, 'vehicle_or_carrier', 160),
            'carrier_reference' => $this->text->nullable($input, 'carrier_reference', 120),
        ];
    }

    /**
     * @param  list<array{quotation_line_id: int, position: int, description: string, unit: string, quantity: string, accepted_quantity: string}>  $lines
     */
    private function replaceLines(DeliveryChallan $challan, array $lines): void
    {
        $this->assertDraft($challan);

        foreach ($challan->lines()->get() as $existing) {
            if ($existing->delete() !== true) {
                throw new DocumentRuleException('lines', 'Draft lines could not be replaced.');
            }
        }

        foreach ($lines as $line) {
            $challan->lines()->create($line);
        }
    }

    private function storedDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        return null;
    }

    private function assertDraft(DeliveryChallan $challan): void
    {
        if ($challan->stringAttribute('status') !== DocumentStatus::DRAFT) {
            throw new DocumentRuleException('challan', 'Only a draft challan can be edited.');
        }
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
            'challan_date' => $this->storedDate($challan->getAttribute('challan_date')),
            'line_count' => $challan->lines()->count(),
        ];
    }
}
