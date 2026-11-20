<?php

namespace App\Documents;

use App\Company\AddressType;
use App\Models\CompanyAddress;
use App\Models\CompanyProfile;

/**
 * Addresses printed on a new PDF. Each active address with show_on_pdf
 * is one labelled line. A stored snapshot is not rebuilt from this list.
 */
final class LetterheadAddresses
{
    public const LAYOUT_JOINED = 'joined';

    public const LAYOUT_LABELLED = 'labelled';

    /**
     * @return list<string>
     */
    public function lines(?CompanyProfile $profile): array
    {
        if (! $profile instanceof CompanyProfile) {
            return [];
        }

        $profile->loadMissing('addresses');
        $order = array_keys(AddressType::options());
        $addresses = $profile->addresses
            ->filter(fn ($address): bool => $address->is_active && $address->show_on_pdf)
            ->sortBy(function (CompanyAddress $address) use ($order): string {
                $rank = array_search($address->address_type, $order, true);

                return sprintf('%02d-%010d', $rank === false ? 99 : $rank, (int) $address->getKey());
            });

        $lines = [];

        foreach ($addresses as $address) {
            $body = implode(', ', $address->lines());

            if ($body === '') {
                continue;
            }

            $lines[] = $this->label((string) $address->address_type).': '.$body;
        }

        return $lines;
    }

    public function label(string $type): string
    {
        return match ($type) {
            AddressType::REGISTERED_OFFICE => 'Registered Office',
            AddressType::CORPORATE_OFFICE => 'Corporate Office',
            default => AddressType::label($type),
        };
    }
}
