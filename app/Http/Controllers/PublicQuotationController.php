<?php

namespace App\Http\Controllers;

use App\Http\Requests\RespondQuotationRequest;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\QuotationShareToken;
use App\Quotations\QuotationPdfRenderer;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationShareService;
use App\Quotations\QuotationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicQuotationController extends Controller
{
    public function show(Request $request, QuotationShareService $shares): View|Response
    {
        $token = $this->token($request, $shares);

        if (! $token instanceof QuotationShareToken) {
            return $this->unavailable();
        }

        try {
            $revision = $shares->recordView($token, false);
        } catch (QuotationRuleException) {
            return $this->unavailable();
        }

        return view('quotations.public', [
            ...$this->pageData($request, $revision),
            'company' => app(QuotationPdfRenderer::class)->letterhead(),
            'logo' => app(QuotationPdfRenderer::class)->logoDataUri(),
        ]);
    }

    public function pdf(Request $request, QuotationShareService $shares, QuotationPdfRenderer $renderer): Response
    {
        $token = $this->token($request, $shares);

        if (! $token instanceof QuotationShareToken) {
            return $this->unavailable();
        }

        try {
            $revision = $shares->recordView($token, true);
        } catch (QuotationRuleException) {
            return $this->unavailable();
        }

        $quotation = $revision->quotation;

        if (! $quotation instanceof Quotation) {
            return $this->unavailable();
        }

        return $renderer->download($quotation, $revision);
    }

    public function respond(RespondQuotationRequest $request, QuotationShareService $shares): RedirectResponse|Response
    {
        $token = $this->token($request, $shares);

        if (! $token instanceof QuotationShareToken) {
            return $this->unavailable();
        }

        $validated = $request->validated();
        $decision = is_string($validated['decision'] ?? null) ? $validated['decision'] : '';

        try {
            $shares->respond(
                $token,
                $decision,
                is_string($validated['respondent_name'] ?? null) ? $validated['respondent_name'] : '',
                is_string($validated['respondent_designation'] ?? null) ? $validated['respondent_designation'] : null,
                is_string($validated['remarks'] ?? null) ? $validated['remarks'] : null,
            );
        } catch (QuotationRuleException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('quotations.public.show', ['token' => $request->route('token')])
            ->with('status', $decision === 'accepted' ? 'Thank you. This quotation is accepted.' : 'Thank you. This quotation is rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Request $request, QuotationRevision $revision): array
    {
        $quotation = $revision->quotation;
        $isCurrent = $quotation instanceof Quotation && (int) $quotation->current_revision_id === (int) $revision->getKey();
        $canRespond = $isCurrent && $revision->stringAttribute('status') === QuotationStatus::SENT && $revision->response === null;

        return [
            'quotation' => $quotation,
            'revision' => $revision,
            'canRespond' => $canRespond,
            'token' => (string) $request->route('token'),
            'superseded' => ! $isCurrent,
        ];
    }

    private function token(Request $request, QuotationShareService $shares): ?QuotationShareToken
    {
        $hash = $request->attributes->get('quotation_share_hash');

        if (! is_string($hash)) {
            return null;
        }

        return $shares->resolve($hash);
    }

    private function unavailable(): Response
    {
        return response()->view('quotations.unavailable', [], 404);
    }
}
