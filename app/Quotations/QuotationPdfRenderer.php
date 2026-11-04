<?php

namespace App\Quotations;

use App\Models\CompanyAddress;
use App\Models\CompanyProfile;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders one immutable revision to PDF in memory.
 * Remote fetching and PHP inside the HTML are disabled. The file is not written to public/.
 */
final class QuotationPdfRenderer
{
    public function download(Quotation $quotation, QuotationRevision $revision): Response
    {
        $revision->loadMissing('lines');
        $binary = $this->binary($quotation, $revision);
        $filename = $this->filename($quotation, $revision);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function binary(Quotation $quotation, QuotationRevision $revision): string
    {
        $html = view('quotations.pdf', [
            'quotation' => $quotation,
            'revision' => $revision,
            'company' => $this->letterhead(),
            'logo' => $this->logoDataUri(),
        ])->render();

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

    /**
     * @return array{legal_name: string, trading_name: ?string, tin: ?string, bin: ?string, email: ?string, phone: ?string, website: ?string, address: list<string>}
     */
    public function letterhead(): array
    {
        $profile = CompanyProfile::primary();
        $fallback = config('asl.company.legal_name');
        $legalName = $profile?->getAttribute('legal_name');

        if (! is_string($legalName) || $legalName === '') {
            $legalName = is_string($fallback) ? $fallback : 'Ahmad Solution Ltd.';
        }

        $profile?->loadMissing('addresses');
        $address = $profile?->registeredOffice() ?? $profile?->corporateOffice();

        return [
            'legal_name' => $legalName,
            'trading_name' => $this->text($profile, 'trading_name'),
            'tin' => $this->text($profile, 'tin'),
            'bin' => $this->text($profile, 'bin'),
            'email' => $this->text($profile, 'email'),
            'phone' => $this->text($profile, 'phone'),
            'website' => $this->text($profile, 'website'),
            'address' => $address instanceof CompanyAddress ? $address->lines() : [],
        ];
    }

    public function logoDataUri(): ?string
    {
        $profile = CompanyProfile::primary();
        $path = $profile?->getAttribute('logo_path');

        if (! is_string($path) || ! str_starts_with($path, 'company-logo/') || str_contains($path, '..')) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };

        if ($mime === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function text(?CompanyProfile $profile, string $key): ?string
    {
        $value = $profile?->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function filename(Quotation $quotation, QuotationRevision $revision): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]/', '', $quotation->stringAttribute('number')) ?: 'quotation';

        return $number.'-R'.(int) $revision->revision_number.'.pdf';
    }
}
