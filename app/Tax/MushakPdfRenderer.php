<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\Mushak63Document;
use App\Quotations\QuotationPdfRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders Mushak 6.3 in memory from the saved lines.
 * The file is not written under public/. The layout follows the inspected
 * column order in English because the PDF font does not include Bengali.
 */
final class MushakPdfRenderer
{
    public function __construct(
        private readonly QuotationPdfRenderer $letterhead,
        private readonly CompanySnapshot $companies,
        private readonly VatPriceCalculator $calculator,
    ) {}

    public function download(Mushak63Document $document): Response
    {
        $binary = $this->render($this->data($document));
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $document->displayNumber()) ?: 'mushak-63';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$safe.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Mushak63Document $document): array
    {
        $document->loadMissing(['lines.rateEnteredBy', 'invoice']);
        $draft = $document->stringAttribute('status') === DocumentStatus::DRAFT;
        $snapshot = $document->getAttribute('company_snapshot');

        return [
            'document' => $document,
            'company' => $draft || ! is_array($snapshot)
                ? $this->letterhead->letterhead()
                : $this->companies->forIssued($snapshot),
            'logo' => $draft ? $this->letterhead->logoDataUri() : null,
            'rates' => $this->rateLabels($document),
            'issuedAt' => $this->issuedAt($document),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function rateLabels(Mushak63Document $document): array
    {
        $labels = [];

        foreach ($document->lines as $line) {
            $labels[(int) $line->getKey()] = $this->calculator->displayRate($line->nullableDecimal('vat_rate_percent'));
        }

        return $labels;
    }

    private function issuedAt(Mushak63Document $document): ?string
    {
        $issued = $document->getAttribute('issued_at');

        if ($issued instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($issued))
                ->timezone('Asia/Dhaka')
                ->format('d M Y H:i');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(array $data): string
    {
        $html = view('tax.mushak.pdf', $data)->render();
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
