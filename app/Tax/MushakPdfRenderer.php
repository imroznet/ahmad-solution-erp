<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\MushakDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders Mushak 6.3 in memory. The file is not written under public/.
 */
final class MushakPdfRenderer
{
    public function __construct(private readonly CompanySnapshot $companies) {}

    public function download(MushakDocument $document, string $copy): Response
    {
        $binary = $this->binary($document, $copy);
        $number = $document->stringAttribute('number');
        $filename = ($number !== '' ? $number : 'mushak-6.3-draft').'-'.$copy.'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(MushakDocument $document, string $copy): array
    {
        if (! in_array($copy, ['original', 'duplicate'], true)) {
            throw new TaxRuleException('copy', 'Choose the original or the duplicate.');
        }

        $document->loadMissing('lines');
        $issued = $document->stringAttribute('status') !== DocumentStatus::DRAFT;

        if ($issued) {
            $stored = $document->getAttribute('company_snapshot');
            $company = $this->companies->forIssued(is_array($stored) ? $stored : null);
        } else {
            $live = $this->companies->capture();
            $company = [
                'legal_name' => $live['legal_name'],
                'trading_name' => $live['trading_name'],
                'tin' => $live['tin'],
                'bin' => $live['bin'],
                'email' => $live['email'],
                'phone' => $live['phone'],
                'website' => $live['website'],
                'address' => $live['address_lines'],
            ];
        }

        return [
            'document' => $document,
            'company' => $company,
            'copy' => $copy,
        ];
    }

    private function binary(MushakDocument $document, string $copy): string
    {
        $html = view('tax.mushak.pdf', $this->viewData($document, $copy))->render();
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
}
