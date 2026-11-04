<?php

namespace App\Parties;

use App\Models\Party;

/**
 * Warns when another party already uses the same name, TIN, BIN, phone, or email.
 * The caller decides whether the user confirmed a separate record. Nothing is merged.
 */
final class PartyDuplicateFinder
{
    /**
     * @return list<array{legal_name: string, reasons: list<string>}>
     */
    public function find(
        string $legalName,
        ?string $tradingName,
        ?string $tin,
        ?string $bin,
        ?string $email,
        ?string $phone,
        ?int $ignoreId = null,
    ): array {
        $legal = mb_strtolower(trim($legalName));
        $trading = mb_strtolower(trim((string) $tradingName));
        $tin = $this->clean($tin);
        $bin = $this->clean($bin);
        $email = $this->clean($email) !== null ? mb_strtolower((string) $this->clean($email)) : null;
        $phone = $this->clean($phone);

        if ($legal === '' && $trading === '' && $tin === null && $bin === null && $email === null && $phone === null) {
            return [];
        }

        $parties = Party::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(function ($query) use ($legal, $trading, $tin, $bin, $email, $phone): void {
                $query->whereRaw('1 = 0');

                if ($legal !== '') {
                    $query->orWhereRaw('LOWER(legal_name) = ?', [$legal])
                        ->orWhereRaw('LOWER(trading_name) = ?', [$legal]);
                }

                if ($trading !== '') {
                    $query->orWhereRaw('LOWER(legal_name) = ?', [$trading])
                        ->orWhereRaw('LOWER(trading_name) = ?', [$trading]);
                }

                if ($tin !== null) {
                    $query->orWhere('tin', $tin);
                }

                if ($bin !== null) {
                    $query->orWhere('bin', $bin);
                }

                if ($email !== null) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->orderBy('legal_name')
            ->limit(5)
            ->get();

        $matches = [];

        foreach ($parties as $party) {
            $reasons = [];
            $partyLegal = mb_strtolower($party->stringAttribute('legal_name'));
            $partyTrading = mb_strtolower((string) $party->nullableAttribute('trading_name'));

            if ($legal !== '' && ($partyLegal === $legal || $partyTrading === $legal)) {
                $reasons[] = 'name';
            }

            if ($trading !== '' && ($partyLegal === $trading || $partyTrading === $trading) && ! in_array('name', $reasons, true)) {
                $reasons[] = 'name';
            }

            if ($tin !== null && $party->nullableAttribute('tin') === $tin) {
                $reasons[] = 'TIN';
            }

            if ($bin !== null && $party->nullableAttribute('bin') === $bin) {
                $reasons[] = 'BIN';
            }

            if ($email !== null && mb_strtolower((string) $party->nullableAttribute('email')) === $email) {
                $reasons[] = 'email';
            }

            if ($phone !== null && $party->nullableAttribute('phone') === $phone) {
                $reasons[] = 'phone';
            }

            if ($reasons === []) {
                continue;
            }

            $matches[] = [
                'legal_name' => $party->stringAttribute('legal_name'),
                'reasons' => $reasons,
            ];
        }

        return $matches;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
