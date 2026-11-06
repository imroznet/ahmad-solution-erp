<?php

namespace App\Tax;

use App\Models\Party;
use App\Models\PartyAddress;

final class BuyerSnapshot
{
    /**
     * @return array{party_id: int, buyer_name: string, buyer_bin: ?string, buyer_tin: ?string, buyer_address: ?string, buyer_phone: ?string, buyer_email: ?string}
     */
    public function fromParty(Party $party): array
    {
        $party->loadMissing('addresses');
        $address = $party->addresses
            ->filter(fn (PartyAddress $row): bool => (bool) $row->getAttribute('is_active'))
            ->sortByDesc(fn (PartyAddress $row): int => (int) $row->getAttribute('is_default_billing'))
            ->first();
        $lines = $address instanceof PartyAddress ? $address->lines() : [];

        return [
            'party_id' => (int) $party->id,
            'buyer_name' => $party->stringAttribute('legal_name'),
            'buyer_bin' => $party->nullableAttribute('bin'),
            'buyer_tin' => $party->nullableAttribute('tin'),
            'buyer_address' => $lines === [] ? null : implode("\n", $lines),
            'buyer_phone' => $party->nullableAttribute('phone'),
            'buyer_email' => $party->nullableAttribute('email'),
        ];
    }
}
