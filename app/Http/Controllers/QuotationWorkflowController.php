<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsQuotation;
use App\Http\Requests\CancelQuotationRequest;
use App\Http\Requests\QuotationDecisionRequest;
use App\Http\Requests\RespondQuotationRequest;
use App\Http\Requests\SendQuotationRequest;
use App\Models\Quotation;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuotationWorkflowController extends Controller
{
    use AttemptsQuotation;

    public function __construct(private readonly QuotationAuthorizer $authorizer) {}

    public function submit(Request $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        $actor = $this->quotationActor();
        abort_unless($this->authorizer->canSubmit($actor, $quotation), 403);
        $remarks = $request->input('remarks');

        if (is_string($remarks) && mb_strlen(trim($remarks)) > 2000) {
            throw ValidationException::withMessages(['remarks' => 'Remarks must be 2000 characters or fewer.']);
        }

        $this->attemptQuotation(fn () => $workflow->submit($quotation, $actor, is_string($remarks) ? trim($remarks) : null));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation submitted for approval.');
    }

    public function approve(QuotationDecisionRequest $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $actor = $this->quotationActor();
        $this->attemptQuotation(fn () => $workflow->approve($quotation, $actor, $request->validated('remarks')));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation approved.');
    }

    public function returnToDraft(QuotationDecisionRequest $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $actor = $this->quotationActor();
        $remarks = $request->validated('remarks');
        $this->attemptQuotation(fn () => $workflow->returnToDraft($quotation, $actor, is_string($remarks) ? $remarks : ''));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation returned to draft.');
    }

    public function send(SendQuotationRequest $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $actor = $this->quotationActor();
        $validated = $request->validated();
        $this->attemptQuotation(fn () => $workflow->send(
            $quotation,
            $actor,
            is_string($validated['recipient_label'] ?? null) ? $validated['recipient_label'] : null,
            is_string($validated['remarks'] ?? null) ? $validated['remarks'] : null,
        ));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Send recorded. No email was sent.');
    }

    public function cancel(CancelQuotationRequest $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $reason = $request->validated('cancel_reason');
        $this->attemptQuotation(fn () => $workflow->cancel($quotation, $this->quotationActor(), is_string($reason) ? $reason : ''));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation cancelled. The record was kept.');
    }

    public function expire(Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        $actor = $this->quotationActor();
        abort_unless($this->authorizer->canExpire($actor, $quotation), 403);
        $this->attemptQuotation(fn () => $workflow->expire($quotation, $actor));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation marked expired.');
    }

    public function respond(RespondQuotationRequest $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $actor = $this->quotationActor();
        $validated = $request->validated();
        $decision = is_string($validated['decision'] ?? null) ? $validated['decision'] : '';
        $this->attemptQuotation(fn () => $workflow->respond(
            $quotation,
            $actor,
            $decision,
            'internal',
            is_string($validated['respondent_name'] ?? null) ? $validated['respondent_name'] : '',
            is_string($validated['respondent_designation'] ?? null) ? $validated['respondent_designation'] : null,
            is_string($validated['remarks'] ?? null) ? $validated['remarks'] : null,
            null,
            $request->ip(),
            $request->userAgent(),
        ));

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('status', $decision === 'accepted' ? 'Acceptance recorded.' : 'Rejection recorded.');
    }
}
