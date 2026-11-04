<?php

namespace App\Http\Controllers;

use App\Company\DocumentExpiry;
use App\Models\AuditLog;
use App\Models\Party;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationListing;
use App\Quotations\QuotationStatus;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DocumentExpiry $expiry, QuotationAuthorizer $quotations, QuotationListing $listing): View
    {
        $user = request()->user();
        $documentExpiry = null;
        $partyCounts = null;
        $quotationCounts = null;

        if ($user instanceof User && $user->hasPermission('company.documents.view')) {
            $documentExpiry = $expiry->counts();
        }

        if ($user instanceof User && ($user->hasPermission('customers.view') || $user->hasPermission('suppliers.view') || $user->hasPermission('parties.view'))) {
            $partyCounts = [
                'customers' => ($user->hasPermission('customers.view') || $user->hasPermission('parties.view'))
                    ? Party::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('role', 'customer'))->count()
                    : null,
                'suppliers' => ($user->hasPermission('suppliers.view') || $user->hasPermission('parties.view'))
                    ? Party::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('role', 'supplier'))->count()
                    : null,
            ];
        }

        if ($user instanceof User && $quotations->canAccessCustomerQuotations($user)) {
            $visible = $listing->base($user);
            $quotationCounts = [
                'open' => (clone $visible)->whereNotIn('quotations.status', QuotationStatus::TERMINAL)->count(),
                'awaiting' => $user->hasPermission('quotations.approve')
                    ? (clone $visible)->where('quotations.status', QuotationStatus::SUBMITTED)->count()
                    : null,
            ];
        }

        return view('dashboard.index', [
            'userCount' => User::query()->count(),
            'activeUserCount' => User::query()->where('is_active', true)->count(),
            'auditCount' => AuditLog::query()->count(),
            'recentActivity' => AuditLog::query()->with('user')->latest('id')->limit(8)->get(),
            'documentExpiry' => $documentExpiry,
            'partyCounts' => $partyCounts,
            'quotationCounts' => $quotationCounts,
        ]);
    }
}
