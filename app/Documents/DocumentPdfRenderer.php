<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Quotations\QuotationPdfRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a challan or commercial invoice in memory.
 * Issued documents use the company snapshot stored at issue.
 * Remote fetching and PHP inside the HTML are disabled. The file is not written to public/.
 */
final class DocumentPdfRenderer
{
    public function __construct(
        private readonly QuotationPdfRenderer $letterhead,
        private readonly CompanySnapshot $companies,
    ) {}

    public function downloadChallan(DeliveryChallan $challan): Response
    {
        $challan->loadMissing(['lines', 'quotation']);
        $binary = $this->render('challans.pdf', $this->challanData($challan));

        return $this->response($binary, $this->filename($challan->displayNumber(), 'challan', (int) $challan->getKey()));
    }

    public function downloadInvoice(CommercialInvoice $invoice): Response
    {
        $invoice->loadMissing(['lines.challanLine.challan', 'quotation']);
        $binary = $this->render('invoices.pdf', $this->invoiceData($invoice));

        return $this->response($binary, $this->filename($invoice->displayNumber(), 'invoice', (int) $invoice->getKey()));
    }

    /**
     * @return array<string, mixed>
     */
    public function challanData(DeliveryChallan $challan): array
    {
        $challan->loadMissing(['lines', 'quotation']);
        $draft = $challan->stringAttribute('status') === DocumentStatus::DRAFT;

        return [
            'challan' => $challan,
            'company' => $draft
                ? $this->letterhead->letterhead()
                : $this->companies->forIssued($this->snapshot($challan)),
            'logo' => $draft ? $this->letterhead->logoDataUri() : null,
            'notice' => $this->notice('asl.delivery_challans.notice', 'This delivery challan is not a commercial invoice, a tax invoice, or a Mushak form. Generating this PDF does not record dispatch or receipt.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceData(CommercialInvoice $invoice): array
    {
        $invoice->loadMissing(['lines.challanLine.challan', 'quotation']);
        $draft = $invoice->stringAttribute('status') === DocumentStatus::DRAFT;

        return [
            'invoice' => $invoice,
            'company' => $draft
                ? $this->letterhead->letterhead()
                : $this->companies->forIssued($this->snapshot($invoice)),
            'logo' => $draft ? $this->letterhead->logoDataUri() : null,
            'notice' => $invoice->stringAttribute('tax_notice') !== ''
                ? $invoice->stringAttribute('tax_notice')
                : $this->notice('asl.commercial_invoices.notice', 'This is a commercial invoice. Tax is excluded and is not calculated. This document is not a tax invoice or a Mushak form.'),
        ];
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

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot(DeliveryChallan|CommercialInvoice $document): ?array
    {
        $snapshot = $document->getAttribute('company_snapshot');

        return is_array($snapshot) ? $snapshot : null;
    }

    private function notice(string $key, string $fallback): string
    {
        $notice = config($key);

        return is_string($notice) && $notice !== '' ? $notice : $fallback;
    }

    private function filename(string $number, string $kind, int $id): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) ?: $kind.'-'.$id;

        return $safe.'.pdf';
    }
}
