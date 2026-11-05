<?php

namespace App\Documents;

use App\Quotations\QuotationPdfRenderer;

/**
 * Freezes the company identity printed on an issued challan or invoice.
 * A later company-profile edit does not change a stored snapshot.
 * The logo is omitted so the PDF can be reproduced without the image file.
 */
final class CompanySnapshot
{
    public function __construct(private readonly QuotationPdfRenderer $letterhead) {}

    /**
     * @return array{legal_name: string, trading_name: ?string, tin: ?string, bin: ?string, email: ?string, phone: ?string, website: ?string, address_lines: list<string>}
     */
    public function capture(): array
    {
        $live = $this->letterhead->letterhead();

        return [
            'legal_name' => $live['legal_name'],
            'trading_name' => $live['trading_name'],
            'tin' => $live['tin'],
            'bin' => $live['bin'],
            'email' => $live['email'],
            'phone' => $live['phone'],
            'website' => $live['website'],
            'address_lines' => $live['address'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array{legal_name: string, trading_name: ?string, tin: ?string, bin: ?string, email: ?string, phone: ?string, website: ?string, address: list<string>}
     */
    public function forIssued(?array $snapshot): array
    {
        if (! is_array($snapshot)) {
            return $this->fallback();
        }

        $legalName = $snapshot['legal_name'] ?? null;

        if (! is_string($legalName) || $legalName === '') {
            return $this->fallback();
        }

        $address = [];

        foreach (is_array($snapshot['address_lines'] ?? null) ? $snapshot['address_lines'] : [] as $line) {
            if (is_string($line) && $line !== '') {
                $address[] = $line;
            }
        }

        return [
            'legal_name' => $legalName,
            'trading_name' => $this->text($snapshot, 'trading_name'),
            'tin' => $this->text($snapshot, 'tin'),
            'bin' => $this->text($snapshot, 'bin'),
            'email' => $this->text($snapshot, 'email'),
            'phone' => $this->text($snapshot, 'phone'),
            'website' => $this->text($snapshot, 'website'),
            'address' => $address,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function text(array $snapshot, string $key): ?string
    {
        $value = $snapshot[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{legal_name: string, trading_name: ?string, tin: ?string, bin: ?string, email: ?string, phone: ?string, website: ?string, address: list<string>}
     */
    private function fallback(): array
    {
        $configured = config('asl.company.legal_name');

        return [
            'legal_name' => is_string($configured) && $configured !== '' ? $configured : 'Ahmad Solution Ltd.',
            'trading_name' => null,
            'tin' => null,
            'bin' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'address' => [],
        ];
    }
}
