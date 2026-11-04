<?php

namespace App\Parties;

use App\Models\Party;
use App\Models\PartyAddress;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class PartyListing
{
    /**
     * @return LengthAwarePaginator<int, Party>
     */
    public function paginate(Request $request, ?string $fixedRole = null): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));
        $search = mb_substr($search, 0, 100);
        $status = (string) $request->query('status', 'active');
        $role = $fixedRole ?? (string) $request->query('role', '');
        $district = trim((string) $request->query('district', ''));
        $district = mb_substr($district, 0, 120);
        $sort = (string) $request->query('sort', 'legal_name');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['legal_name', 'status', 'created_at', 'district'], true)) {
            $sort = 'legal_name';
        }

        if (! in_array($status, ['active', 'inactive', 'archived', 'all'], true)) {
            $status = 'active';
        }

        if (! in_array($role, ['', 'customer', 'supplier'], true)) {
            $role = '';
        }

        $query = Party::query()
            ->select('parties.*')
            ->with(['roles', 'primaryContact'])
            ->withListDistrict();

        if ($status !== 'all') {
            $query->where('parties.status', $status);
        }

        if ($role !== '') {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('role', $role));
        }

        if ($district !== '') {
            $query->whereHas('addresses', function (Builder $addresses) use ($district): void {
                $addresses->where('is_active', true)->where('district', $district);
            });
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '\\%_').'%';
            $query->where(function (Builder $matches) use ($like): void {
                $matches->where('legal_name', 'like', $like)
                    ->orWhere('trading_name', 'like', $like)
                    ->orWhere('legal_name_bn', 'like', $like)
                    ->orWhere('trading_name_bn', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('tin', 'like', $like)
                    ->orWhere('bin', 'like', $like)
                    ->orWhereHas('contacts', function (Builder $contacts) use ($like): void {
                        $contacts->where('phone', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        if ($sort === 'district') {
            $query->orderBy(
                PartyAddress::query()
                    ->select('district')
                    ->whereColumn('party_addresses.party_id', 'parties.id')
                    ->where('is_active', true)
                    ->orderByDesc('is_default_billing')
                    ->orderByDesc('is_default_shipping')
                    ->orderBy('id')
                    ->limit(1),
                $direction,
            );
        } else {
            $query->orderBy('parties.'.$sort, $direction);
        }

        return $query->orderBy('parties.id')->paginate(15)->withQueryString();
    }

    /**
     * @return list<string>
     */
    public function districts(): array
    {
        $districts = [];

        foreach (PartyAddress::query()
            ->where('is_active', true)
            ->where('district', '!=', '')
            ->distinct()
            ->orderBy('district')
            ->pluck('district') as $district) {
            if (is_string($district) && $district !== '') {
                $districts[] = $district;
            }
        }

        return $districts;
    }
}
