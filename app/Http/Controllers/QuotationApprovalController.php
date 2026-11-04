<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsQuotation;
use App\Quotations\QuotationListing;
use App\Quotations\QuotationStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationApprovalController extends Controller
{
    use AttemptsQuotation;

    public function index(Request $request, QuotationListing $listing): View
    {
        $user = $this->quotationActor();

        return view('quotations.approvals', [
            'quotations' => $listing->paginate($request, $user, QuotationStatus::SUBMITTED),
        ]);
    }
}
