<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsQuotation;
use App\Http\Requests\ShareQuotationRequest;
use App\Models\Quotation;
use App\Models\QuotationShareToken;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationShareService;
use Illuminate\Http\RedirectResponse;

class QuotationShareController extends Controller
{
    use AttemptsQuotation;

    public function __construct(private readonly QuotationAuthorizer $authorizer) {}

    public function store(ShareQuotationRequest $request, Quotation $quotation, QuotationShareService $shares): RedirectResponse
    {
        $days = $request->validated('days');
        $days = is_numeric($days) ? (int) $days : (int) config('asl.quotations.share_default_days', 14);
        $raw = $this->attemptQuotation(fn (): string => $shares->create($quotation, $this->quotationActor(), $days));
        abort_unless(is_string($raw), 500);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('status', 'Share link created. Copy it now. It will not be shown again.')
            ->with('quotation_share_url', route('quotations.public.show', ['token' => $raw]));
    }

    public function revoke(Quotation $quotation, QuotationShareToken $share, QuotationShareService $shares): RedirectResponse
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        abort_unless($this->authorizer->canRevokeShare($this->quotationActor(), $quotation), 403);
        $this->attemptQuotation(fn () => $shares->revoke($quotation, $share, $this->quotationActor()));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Share link revoked.');
    }
}
