<?php

namespace App\Company;

use App\Models\CompanyDocument;
use Illuminate\Database\Eloquent\Builder;

final class DocumentExpiry
{
    /**
     * @var list<string>
     */
    public const WINDOWS = ['expired', '7', '15', '30', '60'];

    /**
     * @return array{expired: int, within_7: int, within_15: int, within_30: int, within_60: int}
     */
    public function counts(): array
    {
        return [
            'expired' => $this->apply(CompanyDocument::query(), 'expired')->count(),
            'within_7' => $this->apply(CompanyDocument::query(), '7')->count(),
            'within_15' => $this->apply(CompanyDocument::query(), '15')->count(),
            'within_30' => $this->apply(CompanyDocument::query(), '30')->count(),
            'within_60' => $this->apply(CompanyDocument::query(), '60')->count(),
        ];
    }

    /**
     * @param  Builder<CompanyDocument>  $query
     * @return Builder<CompanyDocument>
     */
    public function apply(Builder $query, ?string $window): Builder
    {
        $today = $this->today();

        if ($window === 'expired') {
            return $query->whereNotNull('expires_on')->whereDate('expires_on', '<', $today);
        }

        if (in_array($window, ['7', '15', '30', '60'], true)) {
            $until = now()->timezone((string) config('app.timezone'))->addDays((int) $window)->toDateString();

            return $query
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '>=', $today)
                ->whereDate('expires_on', '<=', $until);
        }

        return $query;
    }

    public function today(): string
    {
        return now()->timezone((string) config('app.timezone'))->toDateString();
    }
}
