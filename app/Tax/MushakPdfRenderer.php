<?php

namespace App\Tax;

use App\Documents\CompanySnapshot;
use App\Documents\DocumentStatus;
use App\Models\MushakInvoice;
use App\Models\MushakLine;
use App\Quotations\QuotationPdfRenderer;
use App\Support\Decimal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders Mushak 6.3 in memory. The file is not written under public/.
 * Bangla labels use Noto Sans Bengali. The national emblem is not copied.
 */
final class MushakPdfRenderer
{
    public function __construct(
        private readonly QuotationPdfRenderer $letterhead,
        private readonly CompanySnapshot $companies,
        private readonly VatPriceCalculator $prices,
    ) {}

    public function download(MushakInvoice $invoice): Response
    {
        $binary = $this->binary($invoice);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->displayNumber()) ?: 'mushak-'.$invoice->getKey();

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
    public function data(MushakInvoice $invoice): array
    {
        $invoice->loadMissing('lines');
        $draft = $invoice->stringAttribute('status') === DocumentStatus::DRAFT;
        $method = $invoice->stringAttribute('rounding_method');
        $storedCompany = $invoice->getAttribute('company_snapshot');
        $company = $draft
            ? $this->letterhead->letterhead()
            : $this->companies->forIssued(is_array($storedCompany) ? $storedCompany : null);

        return [
            'invoice' => $invoice,
            'company' => $company,
            'prices' => $this->prices,
            'method' => $method,
            'font' => base_path('resources/fonts/NotoSansBengali-Regular.ttf'),
            'lines' => $invoice->lines->map(function (MushakLine $line) use ($method): array {
                $vat = $line->vat_amount === null ? null : $line->decimalAttribute('vat_amount');
                $final = $line->final_amount === null ? null : $line->decimalAttribute('final_amount');

                return [
                    'line' => $line,
                    'display_base' => $this->prices->display($line->decimalAttribute('base_amount'), $method),
                    'display_vat' => $vat === null ? null : $this->prices->display($vat, $method),
                    'display_adjustment' => $this->prices->display($line->decimalAttribute('adjustment_amount'), $method),
                    'display_final' => $final === null ? null : $this->prices->display($final, $method),
                    'display_unit' => $this->unitPrice($line, $method),
                ];
            })->all(),
        ];
    }

    public function binary(MushakInvoice $invoice): string
    {
        $html = view('mushak.pdf', $this->data($invoice))->render();
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setChroot(base_path());
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function unitPrice(MushakLine $line, string $method): ?string
    {
        $quantity = $line->decimalAttribute('quantity');

        if (Decimal::of($quantity)->isZero()) {
            return null;
        }

        $unit = Decimal::of($line->decimalAttribute('base_amount'))->dividedBy($quantity, 8, RoundingMethod::mode($method));

        return $this->prices->display((string) $unit, $method);
    }
}
