<?php

namespace App\Http\Controllers;

use App\Documents\DocumentStatus;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Services\AuditLogger;
use App\Tax\MushakWriter;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxPdfRenderer;
use App\Tax\TaxRuleException;
use App\Tax\VatLineCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MushakInvoiceController extends Controller
{
    public function __construct(
        private readonly TaxAuthorizer $authorizer,
        private readonly MushakWriter $writer,
        private readonly TaxPdfRenderer $pdfs,
        private readonly AuditLogger $audit,
        private readonly QuotationAuthorizer $quotations,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->actor($request);
        $query = MushakInvoice::query()->with('commercialInvoice')->latest('id');

        if (! $this->quotations->canAccessCustomerQuotations($user)) {
            $query->whereIn('id', []);
        }

        return view('tax.mushak.index', [
            'invoices' => $query->paginate(20),
            'canCreate' => $user->hasPermission('mushak.create') && $this->quotations->canAccessCustomerQuotations($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->actor($request);
        abort_unless($this->quotations->canAccessCustomerQuotations($user), 404);
        $query = CommercialInvoice::query()
            ->where('status', DocumentStatus::ISSUED)
            ->whereDoesntHave('mushakInvoices', fn ($query) => $query->whereIn('status', ['draft', 'issued']))
            ->latest('id');

        return view('tax.mushak.pick', [
            'invoices' => $query->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $invoice = CommercialInvoice::query()->with('quotation')->find($request->input('commercial_invoice_id'));
        abort_unless($invoice instanceof CommercialInvoice, 404);
        $user = $this->actor($request);
        abort_unless($invoice->quotation !== null && $this->quotations->canView($user, $invoice->quotation), 404);
        $mushak = $this->attempt(fn (): MushakInvoice => $this->writer->start($user, $invoice));

        return redirect()->route('mushak.edit', $mushak)->with('status', 'Draft Mushak 6.3 prepared. Enter a VAT percentage. None is filled in for you.');
    }

    public function show(MushakInvoice $mushak): View
    {
        $user = $this->actor(request());
        abort_unless($this->authorizer->canViewMushak($user, $mushak), 404);
        $mushak->load('lines', 'commercialInvoice', 'events');

        return view('tax.mushak.show', [
            'invoice' => $mushak,
            'canUpdate' => $this->authorizer->canUpdateMushak($user, $mushak),
            'canIssue' => $this->authorizer->canIssueMushak($user, $mushak),
            'canVoid' => $this->authorizer->canVoidMushak($user, $mushak),
            'canPdf' => $this->authorizer->canDownloadMushak($user, $mushak),
        ]);
    }

    public function edit(MushakInvoice $mushak): View
    {
        $user = $this->actor(request());
        abort_unless($this->authorizer->canViewMushak($user, $mushak), 404);
        abort_unless($this->authorizer->canUpdateMushak($user, $mushak), 403);
        $mushak->load('lines', 'commercialInvoice');

        return view('tax.mushak.form', ['invoice' => $mushak]);
    }

    public function update(Request $request, MushakInvoice $mushak): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->authorizer->canViewMushak($user, $mushak), 404);
        abort_unless($this->authorizer->canUpdateMushak($user, $mushak), 403);
        $this->attempt(fn (): MushakInvoice => $this->writer->update($user, $mushak, $request->all()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak amounts were calculated from the percentage you entered. The system did not choose that percentage.');
    }

    /**
     * Live preview for the draft Mushak form. This only calculates; it
     * saves nothing. The same VatLineCalculator runs again on submit, so
     * the preview can never smuggle a different amount into the document.
     * No Mushak id is needed: the arithmetic is stateless, and the caller
     * still needs mushak.update, the same permission as editing a draft.
     */
    public function calculate(Request $request, VatLineCalculator $calculator): JsonResponse
    {
        $user = $this->actor($request);
        abort_unless($user->hasPermission('mushak.update'), 403);

        try {
            $result = $calculator->calculate(
                base: $this->decimalInput($request, 'base_amount', ''),
                rate: $this->nullableDecimalInput($request, 'vat_rate'),
                adjustment: $this->decimalInput($request, 'adjustment_amount', '0'),
                final: $this->nullableDecimalInput($request, 'final_amount'),
                driver: $this->stringInput($request, 'driver', 'forward'),
                precision: (int) $request->input('calculation_precision', 2),
                roundingMethod: $this->stringInput($request, 'rounding_method', 'half_up'),
            );
        } catch (TaxRuleException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'field' => $exception->field,
            ], 422);
        }

        return response()->json([
            'base_amount' => $result->base,
            'vat_rate' => $result->rate,
            'vat_amount' => $result->vat,
            'forward_vat_amount' => $result->forwardVat,
            'residual_applied' => $result->residualApplied,
            'adjustment_amount' => $result->adjustment,
            'final_amount' => $result->final,
            'precision' => $result->precision,
            'rounding_method' => $result->roundingMethod,
            'driver' => $result->driver,
        ]);
    }

    public function issue(Request $request, MushakInvoice $mushak): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->authorizer->canIssueMushak($user, $mushak), 404);
        $this->attempt(fn (): MushakInvoice => $this->writer->issue($user, $mushak));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 issued. The commercial invoice was not changed.');
    }

    public function void(Request $request, MushakInvoice $mushak): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->authorizer->canVoidMushak($user, $mushak), 404);
        $reason = $request->string('void_reason')->toString();
        $this->attempt(fn (): MushakInvoice => $this->writer->void($user, $mushak, $reason));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 voided. The record was kept.');
    }

    public function pdf(Request $request, MushakInvoice $mushak): Response
    {
        $user = $this->actor($request);
        abort_unless($this->authorizer->canDownloadMushak($user, $mushak), $user->hasPermission('mushak.pdf') ? 404 : 403);
        $this->audit->record('mushak_pdf_downloaded', $mushak, newValues: ['number' => $mushak->displayNumber()], actor: $user);

        return $this->pdfs->mushak($mushak);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function decimalInput(Request $request, string $field, string $default): string
    {
        $value = $request->input($field, $default);

        return is_string($value) || is_int($value) ? trim((string) $value) : $default;
    }

    private function nullableDecimalInput(Request $request, string $field): ?string
    {
        $value = $this->decimalInput($request, $field, '');

        return $value === '' ? null : $value;
    }

    private function stringInput(Request $request, string $field, string $default): string
    {
        $value = $request->input($field, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function attempt(callable $action): mixed
    {
        try {
            return $action();
        } catch (TaxRuleException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }
    }
}
