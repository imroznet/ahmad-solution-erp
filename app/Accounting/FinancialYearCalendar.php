<?php

namespace App\Accounting;

use App\Models\CompanyProfile;
use App\Models\FinancialYear;
use Illuminate\Support\Carbon;

/**
 * Resolves a journal date to a financial year the company has entered.
 * Nothing here assumes a July–June year or any other fixed period.
 */
final class FinancialYearCalendar
{
    /**
     * @return array{starts: string, ends: string}
     */
    public function boundsFor(string $date): array
    {
        $day = $this->day($date);
        $row = FinancialYear::query()
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->orderBy('starts_on')
            ->first();

        if ($row instanceof FinancialYear) {
            return ['starts' => $row->startsOn(), 'ends' => $row->endsOn()];
        }

        $profile = $this->profileBounds();

        if ($profile !== null && $day >= $profile['starts'] && $day <= $profile['ends']) {
            return $profile;
        }

        throw new AccountingException('No financial year covers this date. Add that year on the Financial years screen. A year is not fixed in the software.');
    }

    public function create(string $starts, string $ends): FinancialYear
    {
        $starts = $this->day($starts);
        $ends = $this->day($ends);
        $this->assertSpan($starts, $ends);
        $this->assertNoOverlap($starts, $ends, null, true);
        $year = new FinancialYear([
            'starts_on' => Carbon::parse($starts),
            'ends_on' => Carbon::parse($ends),
        ]);
        $year->save();

        return $year;
    }

    public function update(FinancialYear $year, string $starts, string $ends): FinancialYear
    {
        $starts = $this->day($starts);
        $ends = $this->day($ends);
        $this->assertSpan($starts, $ends);
        $this->assertNoOverlap($starts, $ends, (int) $year->id, true);
        $year->starts_on = Carbon::parse($starts);
        $year->ends_on = Carbon::parse($ends);
        $year->save();

        return $year;
    }

    public function syncProfile(mixed $previousStart, mixed $previousEnd, string $starts, string $ends): void
    {
        $starts = $this->day($starts);
        $ends = $this->day($ends);
        $this->assertSpan($starts, $ends);
        $previous = $this->findExact($previousStart, $previousEnd);
        $this->assertNoOverlap($starts, $ends, $previous instanceof FinancialYear ? (int) $previous->id : null, false);

        if ($previous instanceof FinancialYear) {
            $previous->starts_on = Carbon::parse($starts);
            $previous->ends_on = Carbon::parse($ends);
            $previous->save();

            return;
        }

        if ($this->findExact($starts, $ends) instanceof FinancialYear) {
            return;
        }

        $year = new FinancialYear([
            'starts_on' => Carbon::parse($starts),
            'ends_on' => Carbon::parse($ends),
        ]);
        $year->save();
    }

    /**
     * @return array{starts: string, ends: string}|null
     */
    public function profileBounds(): ?array
    {
        $profile = CompanyProfile::query()->orderBy('id')->first();
        $start = $this->asDate($profile?->getAttribute('financial_year_starts_on'));
        $end = $this->asDate($profile?->getAttribute('financial_year_ends_on'));

        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return null;
        }

        return [
            'starts' => $start->toDateString(),
            'ends' => $end->toDateString(),
        ];
    }

    private function assertSpan(string $starts, string $ends): void
    {
        if ($ends <= $starts) {
            throw new AccountingException('The financial year end must be after the start.');
        }

        if (Carbon::parse($starts)->diffInDays(Carbon::parse($ends)) > 366) {
            throw new AccountingException('The financial year must not be longer than 366 days.');
        }
    }

    private function assertNoOverlap(string $starts, string $ends, ?int $ignoreId, bool $checkProfile): void
    {
        $query = FinancialYear::query()
            ->whereDate('starts_on', '<=', $ends)
            ->whereDate('ends_on', '>=', $starts);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new AccountingException('That financial year overlaps one that is already saved.');
        }

        if (! $checkProfile) {
            return;
        }

        $profile = $this->profileBounds();

        if ($profile === null || $starts > $profile['ends'] || $ends < $profile['starts']) {
            return;
        }

        $same = $this->findExact($profile['starts'], $profile['ends']);

        if ($same instanceof FinancialYear && $ignoreId !== null && (int) $same->id === $ignoreId) {
            return;
        }

        throw new AccountingException('That financial year overlaps the year on the company profile.');
    }

    private function findExact(mixed $starts, mixed $ends): ?FinancialYear
    {
        $start = $this->asDate($starts);
        $end = $this->asDate($ends);

        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return null;
        }

        $year = FinancialYear::query()
            ->whereDate('starts_on', $start->toDateString())
            ->whereDate('ends_on', $end->toDateString())
            ->first();

        return $year instanceof FinancialYear ? $year : null;
    }

    private function day(string $value): string
    {
        $date = $this->asDate($value);

        if (! $date instanceof Carbon) {
            throw new AccountingException('Enter a valid financial year date.');
        }

        return $date->toDateString();
    }

    private function asDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }
}
