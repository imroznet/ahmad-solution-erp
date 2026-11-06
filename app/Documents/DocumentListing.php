<?php

namespace App\Documents;

use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class DocumentListing
{
    public function __construct(private readonly QuotationAuthorizer $quotations) {}

    /**
     * @return LengthAwarePaginator<int, DeliveryChallan>
     */
    public function challans(Request $request, User $user): LengthAwarePaginator
    {
        $filters = $this->filters($request);
        $query = DeliveryChallan::query()->with(['quotation:id,number,party_id', 'createdBy:id,name']);
        $this->restrict($query, $user, 'delivery_challans');
        $this->apply($query, $filters, 'delivery_challans');

        return $query->orderByDesc('delivery_challans.id')->paginate(15)->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, CommercialInvoice>
     */
    public function invoices(Request $request, User $user): LengthAwarePaginator
    {
        $filters = $this->filters($request);
        $query = CommercialInvoice::query()->with(['quotation:id,number,party_id', 'createdBy:id,name']);
        $this->restrict($query, $user, 'commercial_invoices');
        $this->apply($query, $filters, 'commercial_invoices');

        return $query->orderByDesc('commercial_invoices.id')->paginate(15)->withQueryString();
    }

    /**
     * @return array{number: string, customer: string, status: string, quotation: string, party: string}
     */
    public function filters(Request $request): array
    {
        $quotation = trim((string) $request->query('quotation', ''));
        $party = trim((string) $request->query('party', ''));

        return [
            'number' => mb_substr(trim((string) $request->query('number', '')), 0, 32),
            'customer' => mb_substr(trim((string) $request->query('customer', '')), 0, 200),
            'status' => in_array((string) $request->query('status', ''), DocumentStatus::all(), true) ? (string) $request->query('status') : '',
            'quotation' => preg_match('/^[1-9][0-9]*$/', $quotation) === 1 ? $quotation : '',
            'party' => preg_match('/^[1-9][0-9]*$/', $party) === 1 ? $party : '',
        ];
    }

    /**
     * @param  Builder<DeliveryChallan>|Builder<CommercialInvoice>  $query
     */
    private function restrict(Builder $query, User $user, string $table): void
    {
        if (! $this->quotations->canAccessCustomerQuotations($user)) {
            $query->whereIn($table.'.id', []);

            return;
        }

        $query->whereHas('quotation', fn (Builder $quotation) => $quotation->where('party_role', 'customer'));
    }

    /**
     * @param  Builder<DeliveryChallan>|Builder<CommercialInvoice>  $query
     * @param  array{number: string, customer: string, status: string, quotation: string, party: string}  $filters
     */
    private function apply(Builder $query, array $filters, string $table): void
    {
        if ($filters['status'] !== '') {
            $query->where($table.'.status', $filters['status']);
        }

        if ($filters['number'] !== '') {
            $query->where($table.'.number', 'like', addcslashes($filters['number'], '\\%_').'%');
        }

        if ($filters['customer'] !== '') {
            $query->where($table.'.party_legal_name', 'like', '%'.addcslashes($filters['customer'], '\\%_').'%');
        }

        if ($filters['quotation'] !== '') {
            $query->where($table.'.quotation_id', (int) $filters['quotation']);
        }

        if ($filters['party'] !== '') {
            $query->whereHas('quotation', fn (Builder $quotation) => $quotation->where('party_id', (int) $filters['party']));
        }
    }
}
