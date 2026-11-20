<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsQuotation;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationListing;
use App\Quotations\QuotationStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationApprovalController extends Controller
{
    use AttemptsQuotation;

    public function index(Request $request, QuotationListing $listing, QuotationAuthorizer $authorizer): View
    {
        $user = $this->quotationActor();
        $quotations = $listing->paginate($request, $user, QuotationStatus::SUBMITTED);
        $canApprove = [];

        foreach ($quotations as $quotation) {
            $canApprove[(int) $quotation->getKey()] = $authorizer->canApprove($user, $quotation);
        }

        return view('quotations.approvals', [
            'quotations' => $quotations,
            'canApprove' => $canApprove,
        ]);
    }
}
