<?php

namespace App\Quotations;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class QuotationListing
{
    public function __construct(private readonly QuotationAuthorizer $authorizer) {}

    /**
     * @return LengthAwarePaginator<int, Quotation>
     */
    public function paginate(Request $request, User $user, ?string $forcedStatus = null): LengthAwarePaginator
    {
        $filters = $this->filters($request, $user, $forcedStatus);
        $query = $this->base($user);

        if ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $query->where('quotations.status', $filters['status']);
        }

        if ($filters['number'] !== '') {
            $query->where('quotations.number', 'like', addcslashes($filters['number'], '\\%_').'%');
        }

        if ($filters['party'] !== null) {
            $query->where('quotations.party_id', $filters['party']);
        }

        if ($filters['customer'] !== '') {
            $like = '%'.addcslashes($filters['customer'], '\\%_').'%';
            $query->where(function (Builder $matches) use ($like): void {
                $matches->whereHas('party', fn (Builder $party) => $party->where('legal_name', 'like', $like))
                    ->orWhere('listing_revision.party_legal_name', 'like', $like);
            });
        }

        if ($filters['created_by'] !== null) {
            $query->where('quotations.created_by', $filters['created_by']);
        }

        if ($filters['date_from'] !== null) {
            $query->whereDate('listing_revision.quotation_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== null) {
            $query->whereDate('listing_revision.quotation_date', '<=', $filters['date_to']);
        }

        $sortColumn = match ($filters['sort']) {
            'number' => 'quotations.number',
            'status' => 'quotations.status',
            'quotation_date' => 'listing_revision.quotation_date',
            default => 'quotations.created_at',
        };

        return $query
            ->orderBy($sortColumn, $filters['direction'])
            ->orderBy('quotations.id', $filters['direction'])
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return Builder<Quotation>
     */
    public function base(User $user): Builder
    {
        $query = Quotation::query()
            ->select('quotations.*')
            ->leftJoin('quotation_revisions as listing_revision', 'listing_revision.id', '=', 'quotations.current_revision_id')
            ->with(['party:id,legal_name,status', 'currentRevision', 'createdBy:id,name']);

        if (! $this->authorizer->canAccessCustomerQuotations($user)) {
            $query->whereIn('quotations.id', []);
        } else {
            $query->where('quotations.party_role', 'customer');
        }

        return $query;
    }

    /**
     * @return array{number: string, customer: string, party: ?int, status: string, created_by: ?int, date_from: ?string, date_to: ?string, sort: string, direction: string}
     */
    public function filters(Request $request, User $user, ?string $forcedStatus = null): array
    {
        $status = $forcedStatus ?? (string) $request->query('status', '');

        if (! in_array($status, ['', 'all', ...QuotationStatus::all()], true)) {
            $status = '';
        }

        $sort = (string) $request->query('sort', 'created_at');

        if (! in_array($sort, ['number', 'status', 'quotation_date', 'created_at'], true)) {
            $sort = 'created_at';
        }

        $createdBy = $request->query('created_by');

        if ($createdBy === 'me') {
            $createdBy = $user->getKey();
        }

        return [
            'number' => mb_substr(trim((string) $request->query('number', '')), 0, 32),
            'customer' => mb_substr(trim((string) $request->query('customer', '')), 0, 100),
            'party' => $this->positiveInt($request->query('party')),
            'status' => $status,
            'created_by' => $this->positiveInt($createdBy),
            'date_from' => $this->date($request->query('date_from')),
            'date_to' => $this->date($request->query('date_to')),
            'sort' => $sort,
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $value ? $value : null;
    }
}
