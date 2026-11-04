<?php

namespace App\Quotations;

use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyContact;

/**
 * Copies the customer identity used by one revision.
 * Later edits to the party do not change a saved snapshot.
 */
final class QuotationSnapshotter
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function capture(Party $party, array $input): array
    {
        $party->loadMissing(['contacts', 'addresses']);
        $contact = $this->contact($party, $input['contact_id'] ?? null);
        $billing = $this->address($party, $input['billing_address_id'] ?? null, 'billing_address_id', 'is_default_billing');
        $shipping = $this->address($party, $input['shipping_address_id'] ?? null, 'shipping_address_id', 'is_default_shipping');

        $contactSnapshot = $contact?->auditSnapshot() ?? [];

        return [
            'party_legal_name' => $party->stringAttribute('legal_name'),
            'party_trading_name' => $party->nullableAttribute('trading_name'),
            'party_kind' => $party->stringAttribute('party_kind'),
            'party_tin' => $party->nullableAttribute('tin'),
            'party_bin' => $party->nullableAttribute('bin'),
            'party_vat_registration_number' => $party->nullableAttribute('vat_registration_number'),
            'party_email' => $party->nullableAttribute('email'),
            'party_phone' => $party->nullableAttribute('phone'),
            'contact_name' => $this->text($contactSnapshot, 'name'),
            'contact_designation' => $this->text($contactSnapshot, 'designation'),
            'contact_email' => $this->text($contactSnapshot, 'email'),
            'contact_phone' => $this->text($contactSnapshot, 'phone'),
            'billing_address' => $billing instanceof PartyAddress ? $this->addressPayload($billing) : null,
            'shipping_address' => $shipping instanceof PartyAddress ? $this->addressPayload($shipping) : null,
        ];
    }

    private function contact(Party $party, mixed $contactId): ?PartyContact
    {
        if ($contactId === null || $contactId === '') {
            $primary = $party->contacts->first(
                fn (PartyContact $contact): bool => $contact->is_primary && $contact->is_active,
            );

            return $primary instanceof PartyContact ? $primary : null;
        }

        $contact = $party->contacts->first(
            fn (PartyContact $row): bool => (int) $row->getKey() === (int) $contactId && $row->is_active,
        );

        if (! $contact instanceof PartyContact) {
            throw new QuotationRuleException('contact_id', 'Choose an active contact of this customer.');
        }

        return $contact;
    }

    private function address(Party $party, mixed $addressId, string $field, string $defaultFlag): ?PartyAddress
    {
        if ($addressId === null || $addressId === '') {
            $default = $party->addresses->first(
                fn (PartyAddress $address): bool => (bool) $address->getAttribute($defaultFlag) && (bool) $address->is_active,
            );

            return $default instanceof PartyAddress ? $default : null;
        }

        $address = $party->addresses->first(
            fn (PartyAddress $row): bool => (int) $row->getKey() === (int) $addressId && (bool) $row->is_active,
        );

        if (! $address instanceof PartyAddress) {
            throw new QuotationRuleException($field, 'Choose an active address of this customer.');
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(PartyAddress $address): array
    {
        $snapshot = $address->auditSnapshot();

        return [
            'address_type' => $this->text($snapshot, 'address_type'),
            'address_line_1' => $this->text($snapshot, 'address_line_1'),
            'address_line_2' => $this->text($snapshot, 'address_line_2'),
            'area' => $this->text($snapshot, 'area'),
            'upazila' => $this->text($snapshot, 'upazila'),
            'district' => $this->text($snapshot, 'district'),
            'division' => $this->text($snapshot, 'division'),
            'postcode' => $this->text($snapshot, 'postcode'),
            'country' => $this->text($snapshot, 'country'),
            'lines' => $address->lines(),
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
}
