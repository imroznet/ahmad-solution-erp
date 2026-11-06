<?php

namespace App\Tax;

use App\Models\Party;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationSnapshotter;

/**
 * Copies the buyer onto the Mushak draft.
 * Manual entry does not create a party record.
 */
final class MushakBuyer
{
    public function __construct(private readonly QuotationSnapshotter $snapshots) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function capture(array $input): array
    {
        $source = is_string($input['buyer_source'] ?? null) ? $input['buyer_source'] : '';

        if ($source === 'party') {
            return $this->fromParty($input);
        }

        if ($source === 'manual') {
            return $this->manual($input);
        }

        throw new TaxRuleException('buyer_source', 'Choose a saved customer or enter the buyer manually.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function fromParty(array $input): array
    {
        $partyId = is_numeric($input['party_id'] ?? null) ? (int) $input['party_id'] : 0;
        $party = Party::query()->find($partyId);

        if (! $party instanceof Party || $party->stringAttribute('status') !== 'active' || ! $party->hasRole('customer')) {
            throw new TaxRuleException('party_id', 'Choose an active customer. A manual buyer is not saved as a customer.');
        }

        try {
            $captured = $this->snapshots->capture($party, []);
        } catch (QuotationRuleException $exception) {
            throw new TaxRuleException($exception->field, $exception->getMessage());
        }

        $address = $captured['billing_address'] ?? null;
        $lines = is_array($address) && is_array($address['lines'] ?? null) ? $this->lines($address['lines']) : [];

        if ($lines === []) {
            throw new TaxRuleException('party_id', 'This customer has no billing address to copy. Enter the buyer manually, including the address.');
        }

        return [
            'buyer_source' => 'party',
            'party_id' => $party->getKey(),
            'buyer_legal_name' => $captured['party_legal_name'],
            'buyer_trading_name' => $captured['party_trading_name'],
            'buyer_bin' => $captured['party_bin'],
            'buyer_tin' => $captured['party_tin'],
            'buyer_phone' => $captured['party_phone'],
            'buyer_email' => $captured['party_email'],
            'buyer_address' => ['lines' => $lines],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function manual(array $input): array
    {
        $name = trim(is_string($input['buyer_legal_name'] ?? null) ? $input['buyer_legal_name'] : '');

        if ($name === '' || mb_strlen($name) > 200) {
            throw new TaxRuleException('buyer_legal_name', 'Enter the buyer name. Manual entry does not create a customer record.');
        }

        $address = trim(is_string($input['buyer_address'] ?? null) ? $input['buyer_address'] : '');
        $lines = $this->lines(preg_split('/\r\n|\r|\n/', $address) ?: []);

        if ($lines === []) {
            throw new TaxRuleException('buyer_address', 'Enter the buyer address. The inspected Mushak 6.3 form has a buyer destination.');
        }

        return [
            'buyer_source' => 'manual',
            'party_id' => null,
            'buyer_legal_name' => $name,
            'buyer_trading_name' => $this->limited($input, 'buyer_trading_name', 200),
            'buyer_bin' => $this->limited($input, 'buyer_bin', 30),
            'buyer_tin' => $this->limited($input, 'buyer_tin', 30),
            'buyer_phone' => $this->limited($input, 'buyer_phone', 40),
            'buyer_email' => $this->email($input),
            'buyer_address' => ['lines' => $lines],
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<string>
     */
    private function lines(array $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            if (! is_string($row)) {
                continue;
            }

            $line = trim($row);

            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) > 200) {
                throw new TaxRuleException('buyer_address', 'Each address line must be 200 characters or fewer.');
            }

            $lines[] = $line;
        }

        if (count($lines) > 8) {
            throw new TaxRuleException('buyer_address', 'Use at most 8 address lines.');
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function limited(array $input, string $key, int $limit): ?string
    {
        $value = trim(is_string($input[$key] ?? null) ? $input[$key] : '');

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $limit) {
            throw new TaxRuleException($key, 'Use at most '.$limit.' characters.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function email(array $input): ?string
    {
        $email = $this->limited($input, 'buyer_email', 255);

        if ($email === null) {
            return null;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new TaxRuleException('buyer_email', 'Enter a valid email address or leave it blank.');
        }

        return strtolower($email);
    }
}
