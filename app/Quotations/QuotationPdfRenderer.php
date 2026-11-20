<?php

namespace App\Quotations;

use App\Documents\LetterheadAddresses;
use App\Documents\PdfFactory;
use App\Models\CompanyProfile;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders one immutable revision to PDF in memory.
 * Remote fetching and PHP inside the HTML are disabled. The file is not written to public/.
 */
final class QuotationPdfRenderer
{
    public function __construct(
        private readonly PdfFactory $pdf,
        private readonly LetterheadAddresses $addresses,
    ) {}

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
        return $this->pdf->render('quotations.pdf', [
            'quotation' => $quotation,
            'revision' => $revision,
            'company' => $this->letterhead(),
            'logo' => $this->logoDataUri(),
        ]);
    }

    /**
     * @return array{legal_name: string, trading_name: ?string, tin: ?string, bin: ?string, email: ?string, phone: ?string, website: ?string, address: list<string>, address_layout: string}
     */
    public function letterhead(): array
    {
        $profile = CompanyProfile::primary();
        $fallback = config('asl.company.legal_name');
        $legalName = $profile?->getAttribute('legal_name');

        if (! is_string($legalName) || $legalName === '') {
            $legalName = is_string($fallback) ? $fallback : 'Ahmad Solution Ltd.';
        }

        return [
            'legal_name' => $legalName,
            'trading_name' => $this->text($profile, 'trading_name'),
            'tin' => $this->text($profile, 'tin'),
            'bin' => $this->text($profile, 'bin'),
            'email' => $this->text($profile, 'email'),
            'phone' => $this->text($profile, 'phone'),
            'website' => $this->text($profile, 'website'),
            'address' => $this->addresses->lines($profile),
            'address_layout' => LetterheadAddresses::LAYOUT_LABELLED,
        ];
    }

    public function logoDataUri(): ?string
    {
        $stored = $this->readLiveLogo();

        if ($stored === null) {
            return null;
        }

        return 'data:'.$stored['mime'].';base64,'.base64_encode($stored['bytes']);
    }

    /**
     * Copies the live logo onto the private disk under a content hash.
     * Replacing or deleting the company profile file does not change this copy.
     *
     * @return array{path: string, mime: string}|null
     */
    public function snapshotLogo(): ?array
    {
        $stored = $this->readLiveLogo();

        if ($stored === null) {
            return null;
        }

        $hash = hash('sha256', $stored['bytes']);
        $path = 'company-logo-snapshots/'.$hash.'.'.$stored['extension'];
        $disk = Storage::disk('local');

        if (! $disk->exists($path) && $disk->put($path, $stored['bytes']) !== true) {
            return null;
        }

        return [
            'path' => $path,
            'mime' => $stored['mime'],
        ];
    }

    /**
     * @param  array<string, mixed>  $logo
     */
    public function logoDataUriFromSnapshot(array $logo): ?string
    {
        $path = $logo['path'] ?? null;

        if (! is_string($path) || preg_match('#^company-logo-snapshots/[a-f0-9]{64}\.(png|jpe?g)$#', $path) !== 1) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $this->mimeFor($extension);
        $disk = Storage::disk('local');

        if ($mime === null || ! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);

        if (! is_string($bytes) || $bytes === '' || hash('sha256', $bytes) !== pathinfo($path, PATHINFO_FILENAME)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * @return array{bytes: string, extension: string, mime: string}|null
     */
    private function readLiveLogo(): ?array
    {
        $profile = CompanyProfile::primary();
        $path = $profile?->getAttribute('logo_path');

        if (! is_string($path) || ! str_starts_with($path, 'company-logo/') || str_contains($path, '..')) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $this->mimeFor($extension);

        if ($mime === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'mime' => $mime,
        ];
    }

    private function mimeFor(string $extension): ?string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
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
