<?php

namespace App\Tax;

use App\Documents\MpdfFactory;
use App\Models\MushakInvoice;
use App\Quotations\QuotationPdfRenderer;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private PDFs. Remote fetching and PHP inside the HTML are disabled.
 * The file is not written under public/.
 */
final class TaxPdfRenderer
{
    public function __construct(
        private readonly MpdfFactory $pdf,
        private readonly QuotationPdfRenderer $letterhead,
    ) {}

    public function mushak(MushakInvoice $invoice): Response
    {
        $invoice->loadMissing(['lines', 'commercialInvoice']);
        $binary = $this->render('tax.mushak.pdf', [
            'invoice' => $invoice,
            'lines' => $invoice->lines,
            'draftCompany' => $invoice->stringAttribute('status') === 'draft' ? $this->letterhead->letterhead() : null,
        ]);

        return $this->response($binary, $this->filename($invoice->displayNumber(), 'mushak', (int) $invoice->getKey()));
    }

    public function deduction(string $kind, Model $record): Response
    {
        $binary = $this->render('tax.deductions.pdf', [
            'kind' => $kind,
            'title' => $kind === 'vds' ? 'VDS record' : 'AIT record',
            'record' => $record,
        ]);

        return $this->response($binary, $this->filename(strtoupper($kind).'-'.$record->getKey(), $kind, (int) $record->getKey()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data): string
    {
        return $this->pdf->render($view, $data);
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

    private function filename(string $number, string $kind, int $id): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) ?: $kind.'-'.$id;

        return $safe.'.pdf';
    }
}
