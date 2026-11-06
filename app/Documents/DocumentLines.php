<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanLine;
use App\Models\QuotationLine;
use App\Models\QuotationRevision;
use App\Quotations\QuotationCalculator;
use App\Support\Decimal;
use App\Support\Money;

/**
 * Builds challan and invoice lines from the accepted revision.
 * Callers hold the quotation lock, and invoice callers also hold the challan locks.
 */
final class DocumentLines
{
    public function __construct(
        private readonly DocumentQuantity $quantities,
        private readonly DocumentBalance $balances,
        private readonly InvoicePricing $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{quotation_line_id: int, position: int, description: string, unit: string, quantity: string, accepted_quantity: string}>
     */
    public function forChallan(QuotationRevision $revision, array $input, ?int $exceptChallanId): array
    {
        $revision->loadMissing('lines');
        $sources = $this->quotationLines($revision);
        $parsed = $this->quantities->included($input, 'quotation_line_id', 'Add at least one delivered quantity.');
        $seen = [];
        $built = [];
        $position = 1;

        foreach ($parsed as $row) {
            $lineId = $row['id'];
            $index = $row['index'];

            if (isset($seen[$lineId])) {
                throw new DocumentRuleException('lines.'.$index.'.quotation_line_id', 'Each quotation line can appear once on a challan.');
            }

            $seen[$lineId] = true;
            $source = $sources[$lineId] ?? null;

            if (! $source instanceof QuotationLine) {
                throw new DocumentRuleException('lines.'.$index.'.quotation_line_id', 'Choose a line from the accepted quotation.');
            }

            $accepted = $source->decimalAttribute('quantity');
            $combined = Decimal::of($this->balances->deliveredQuantity($lineId, $exceptChallanId))->plus(Decimal::of($row['quantity']));

            if ($combined->compare(Decimal::of($accepted)) > 0) {
                throw new DocumentRuleException(
                    'lines.'.$index.'.quantity',
                    'The delivered quantity for "'.$this->label($source).'" would be '.QuotationCalculator::formatQuantity((string) $combined).', which is above the accepted quantity of '.QuotationCalculator::formatQuantity($accepted).'.',
                );
            }

            $built[] = [
                'quotation_line_id' => $lineId,
                'position' => $position,
                'description' => $this->text($source, 'description'),
                'unit' => $this->text($source, 'unit'),
                'quantity' => $row['quantity'],
                'accepted_quantity' => $accepted,
            ];
            $position++;
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<int>
     */
    public function challanIdsForInvoice(int $quotationId, array $input): array
    {
        $parsed = $this->quantities->included($input, 'delivery_challan_line_id', 'Add at least one quantity to invoice.');
        $ids = [];

        foreach ($parsed as $row) {
            $challanLine = DeliveryChallanLine::query()->with('challan')->find($row['id']);

            if (! $challanLine instanceof DeliveryChallanLine || ! $challanLine->challan instanceof DeliveryChallan) {
                throw new DocumentRuleException('lines.'.$row['index'].'.delivery_challan_line_id', 'Choose a delivered line from an issued challan for this accepted quotation.');
            }

            if ((int) $challanLine->challan->quotation_id !== $quotationId) {
                throw new DocumentRuleException('lines.'.$row['index'].'.delivery_challan_line_id', 'Choose a delivered line from an issued challan for this accepted quotation.');
            }

            $ids[] = (int) $challanLine->challan->getKey();
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{delivery_challan_line_id: int, quotation_line_id: int, position: int, description: string, unit: string, quantity: string, unit_price: string, line_total: string}>
     */
    public function forInvoice(QuotationRevision $revision, int $quotationId, array $input, ?int $exceptInvoiceId): array
    {
        $sources = $this->quotationLines($revision);
        $parsed = $this->quantities->included($input, 'delivery_challan_line_id', 'Add at least one quantity to invoice.');
        $seen = [];
        $runningQuantity = [];
        $runningAmount = [];
        $built = [];
        $position = 1;

        foreach ($parsed as $row) {
            $index = $row['index'];
            $challanLineId = $row['id'];

            if (isset($seen[$challanLineId])) {
                throw new DocumentRuleException('lines.'.$index.'.delivery_challan_line_id', 'Each delivered line can appear once on an invoice.');
            }

            $seen[$challanLineId] = true;
            $challanLine = DeliveryChallanLine::query()->with('challan')->find($challanLineId);
            $challan = $challanLine?->challan;

            if (! $challanLine instanceof DeliveryChallanLine
                || ! $challan instanceof DeliveryChallan
                || $challan->stringAttribute('status') !== DocumentStatus::ISSUED
                || (int) $challan->quotation_id !== $quotationId
                || (int) $challan->quotation_revision_id !== (int) $revision->getKey()) {
                throw new DocumentRuleException('lines.'.$index.'.delivery_challan_line_id', 'Choose a delivered line from an issued challan for this accepted quotation.');
            }

            $quotationLineId = (int) $challanLine->quotation_line_id;
            $source = $sources[$quotationLineId] ?? null;

            if (! $source instanceof QuotationLine) {
                throw new DocumentRuleException('lines.'.$index.'.delivery_challan_line_id', 'The delivered line does not belong to the accepted revision.');
            }

            $nextOnChallan = Decimal::of($this->balances->invoicedQuantityForChallanLine($challanLineId, $exceptInvoiceId))
                ->plus(Decimal::of($row['quantity']));

            if ($nextOnChallan->compare(Decimal::of($challanLine->decimalAttribute('quantity'))) > 0) {
                throw new DocumentRuleException(
                    'lines.'.$index.'.quantity',
                    'The invoiced quantity for "'.$this->label($source).'" would exceed the quantity delivered on '.$challan->displayNumber().'.',
                );
            }

            $priorQuantity = $runningQuantity[$quotationLineId] ?? $this->balances->invoicedQuantityForQuotationLine($quotationLineId, $exceptInvoiceId);
            $priorAmount = $runningAmount[$quotationLineId] ?? $this->balances->invoicedAmountForQuotationLine($quotationLineId, $exceptInvoiceId);
            $acceptedQuantity = $source->decimalAttribute('quantity');
            $combinedQuantity = Decimal::of($priorQuantity)->plus(Decimal::of($row['quantity']));

            if ($combinedQuantity->compare(Decimal::of($acceptedQuantity)) > 0) {
                throw new DocumentRuleException('lines.'.$index.'.quantity', 'The invoiced quantity for "'.$this->label($source).'" would exceed the accepted quantity.');
            }

            $lineTotal = $this->pricing->stageAmount(
                $row['quantity'],
                $source->decimalAttribute('unit_price'),
                $acceptedQuantity,
                $source->decimalAttribute('line_total'),
                $priorQuantity,
                $priorAmount,
                'lines.'.$index.'.quantity',
            );

            $runningQuantity[$quotationLineId] = (string) $combinedQuantity->toScale(4);
            $runningAmount[$quotationLineId] = Money::of($priorAmount)->plus(Money::of($lineTotal))->amount();

            $built[] = [
                'delivery_challan_line_id' => $challanLineId,
                'quotation_line_id' => $quotationLineId,
                'position' => $position,
                'description' => $this->text($source, 'description'),
                'unit' => $this->text($source, 'unit'),
                'quantity' => $row['quantity'],
                'unit_price' => $source->decimalAttribute('unit_price'),
                'line_total' => $lineTotal,
            ];
            $position++;
        }

        return $built;
    }

    /**
     * @param  list<array{line_total: string}>  $lines
     */
    public function total(array $lines, int $quotationId, string $revisionTotal, ?int $exceptInvoiceId): string
    {
        $document = Money::zero();

        foreach ($lines as $line) {
            $document = $document->plus(Money::of($line['line_total']));
        }

        $combined = Money::of($this->balances->issuedInvoiceTotal($quotationId, $exceptInvoiceId))->plus($document);
        $amount = $combined->amount();

        if (Decimal::of($amount)->compare(Decimal::of($revisionTotal)) > 0) {
            throw new DocumentRuleException('lines', 'The invoiced amount would exceed the accepted quotation total of '.$revisionTotal.' BDT.');
        }

        if (Decimal::of($amount)->compare(Decimal::of(QuotationCalculator::TOTAL_MAX)) > 0) {
            throw new DocumentRuleException('lines', 'The invoiced amount would exceed '.QuotationCalculator::TOTAL_MAX.' BDT.');
        }

        return $document->amount();
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceInputFromLines(CommercialInvoice $invoice): array
    {
        $invoice->load('lines');
        $lines = [];

        foreach ($invoice->lines as $line) {
            $lines[] = [
                'delivery_challan_line_id' => (int) $line->delivery_challan_line_id,
                'quantity' => $line->decimalAttribute('quantity'),
            ];
        }

        return ['lines' => $lines];
    }

    /**
     * @return array<int, QuotationLine>
     */
    private function quotationLines(QuotationRevision $revision): array
    {
        $revision->loadMissing('lines');
        $sources = [];

        foreach ($revision->lines as $line) {
            $sources[(int) $line->getKey()] = $line;
        }

        return $sources;
    }

    private function label(QuotationLine $line): string
    {
        $text = $this->text($line, 'description');

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'...' : $text;
    }

    private function text(QuotationLine $line, string $key): string
    {
        $value = $line->getAttribute($key);

        return is_string($value) ? $value : '';
    }
}
