<?php

namespace App\Documents;

use App\Support\Decimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sums issued quantities and amounts. Voided and draft rows are excluded.
 * Values are read as decimal strings and added with Decimal, never as floats.
 */
final class DocumentBalance
{
    public function deliveredQuantity(int $quotationLineId, ?int $exceptChallanId): string
    {
        $query = DB::table('delivery_challan_lines as lines')
            ->join('delivery_challans as challans', 'challans.id', '=', 'lines.delivery_challan_id')
            ->where('challans.status', DocumentStatus::ISSUED)
            ->whereNull('lines.superseded_at')
            ->where('lines.quotation_line_id', $quotationLineId);

        if ($exceptChallanId !== null) {
            $query->where('challans.id', '!=', $exceptChallanId);
        }

        return $this->sum($query, 'lines.quantity', 4);
    }

    public function invoicedQuantityForChallanLine(int $challanLineId, ?int $exceptInvoiceId): string
    {
        $query = DB::table('commercial_invoice_lines as lines')
            ->join('commercial_invoices as invoices', 'invoices.id', '=', 'lines.commercial_invoice_id')
            ->where('invoices.status', DocumentStatus::ISSUED)
            ->whereNull('lines.superseded_at')
            ->where('lines.delivery_challan_line_id', $challanLineId);

        if ($exceptInvoiceId !== null) {
            $query->where('invoices.id', '!=', $exceptInvoiceId);
        }

        return $this->sum($query, 'lines.quantity', 4);
    }

    public function invoicedQuantityForQuotationLine(int $quotationLineId, ?int $exceptInvoiceId): string
    {
        $query = $this->issuedInvoiceLines($quotationLineId, $exceptInvoiceId);

        return $this->sum($query, 'lines.quantity', 4);
    }

    public function invoicedAmountForQuotationLine(int $quotationLineId, ?int $exceptInvoiceId): string
    {
        $query = $this->issuedInvoiceLines($quotationLineId, $exceptInvoiceId);

        return $this->sum($query, 'lines.line_total', 2);
    }

    public function issuedInvoiceTotal(int $quotationId, ?int $exceptInvoiceId): string
    {
        $query = DB::table('commercial_invoices')
            ->where('status', DocumentStatus::ISSUED)
            ->where('quotation_id', $quotationId);

        if ($exceptInvoiceId !== null) {
            $query->where('id', '!=', $exceptInvoiceId);
        }

        return $this->sum($query, 'total', 2);
    }

    private function issuedInvoiceLines(int $quotationLineId, ?int $exceptInvoiceId): Builder
    {
        $query = DB::table('commercial_invoice_lines as lines')
            ->join('commercial_invoices as invoices', 'invoices.id', '=', 'lines.commercial_invoice_id')
            ->where('invoices.status', DocumentStatus::ISSUED)
            ->whereNull('lines.superseded_at')
            ->where('lines.quotation_line_id', $quotationLineId);

        if ($exceptInvoiceId !== null) {
            $query->where('invoices.id', '!=', $exceptInvoiceId);
        }

        return $query;
    }

    private function sum(Builder $query, string $column, int $scale): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new DocumentRuleException('lines', 'A stored amount could not be read.');
        }

        $total = Decimal::zero();
        $values = (clone $query)
            ->selectRaw('CAST('.$column.' AS CHAR) as amount_text')
            ->pluck('amount_text');

        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value)) {
                throw new DocumentRuleException('lines', 'A stored amount could not be read.');
            }

            $total = $total->plus(Decimal::of(is_int($value) ? (string) $value : $value));
        }

        return (string) $total->toScale($scale, RoundingMode::Unnecessary);
    }
}
