<?php

namespace App\Tax;

use App\Models\Mushak63Document;
use App\Models\WithholdingRecord;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders an internal working paper. Remote fetching and PHP inside the HTML
 * are disabled. The file is not written under public/.
 */
final class TaxPdfRenderer
{
    public function downloadMushak(Mushak63Document $document): Response
    {
        $document->loadMissing(['lines', 'invoice']);
        $binary = $this->render('tax.working-paper', [
            'title' => 'Mushak 6.3 working paper',
            'notice' => 'This is not an issued Mushak 6.3 tax invoice. Statutory issue is blocked. See the source register.',
            'lines' => [
                'Linked commercial invoice' => $document->stringAttribute('invoice_number'),
                'Quotation' => (string) $document->getAttribute('quotation_number'),
                'Customer' => $document->stringAttribute('party_legal_name'),
                'Customer BIN' => (string) ($document->getAttribute('party_bin') ?? ''),
                'Classification' => $document->stringAttribute('supply_classification'),
                'Rule code' => $document->stringAttribute('rule_code') === '' ? 'None' : $document->stringAttribute('rule_code'),
                'Invoice total (tax excluded)' => $document->decimalAttribute('invoice_total').' '.$document->stringAttribute('currency_code'),
                'Pinned preview' => $this->preview($document->getAttribute('pinned_rule_snapshot')),
            ],
            'table' => $document->lines->map(fn ($line): array => [
                (string) $line->getAttribute('position'),
                $line->stringAttribute('description'),
                $line->decimalAttribute('quantity'),
                $line->decimalAttribute('line_total'),
            ])->all(),
            'blockers' => IssuanceGate::blockers(),
        ]);

        return $this->response($binary, 'mushak-working-paper-'.$document->getKey().'.pdf');
    }

    public function downloadWithholding(WithholdingRecord $record): Response
    {
        $label = TaxType::label($record->taxType());
        $binary = $this->render('tax.working-paper', [
            'title' => $label.' working paper',
            'notice' => 'This is not a '.$label.' certificate. Certificate issue is blocked. This amount is not output VAT.',
            'lines' => [
                'Direction' => TaxDirection::label($record->stringAttribute('direction')),
                'Other party' => $record->stringAttribute('party_legal_name'),
                'Classification' => $record->stringAttribute('supply_classification'),
                'Record date' => (string) $record->dateString('record_date'),
                'Base' => $record->decimalAttribute('basis_amount').' BDT',
                'Certificate reference received' => (string) ($record->getAttribute('certificate_reference') ?? ''),
                'Treasury reference' => (string) ($record->getAttribute('treasury_reference') ?? ''),
                'Pinned preview' => $this->preview($record->getAttribute('pinned_rule_snapshot')),
            ],
            'table' => [],
            'blockers' => IssuanceGate::blockers(),
        ]);

        return $this->response($binary, strtolower($label).'-working-paper-'.$record->getKey().'.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data): string
    {
        $html = view($view, $data)->render();
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function response(string $binary, string $filename): Response
    {
        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function preview(mixed $snapshot): string
    {
        if (! is_array($snapshot)) {
            return 'No approved rule is pinned. No tax amount is calculated.';
        }

        $amount = $snapshot['amount'] ?? null;
        $version = $snapshot['version_id'] ?? null;

        return 'Rule version '.$version.', preview BDT '.(is_string($amount) || is_int($amount) ? (string) $amount : 'not calculated').'. Not an issued assessment.';
    }
}
