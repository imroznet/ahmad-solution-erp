<?php

namespace App\Http\Controllers;

use App\Documents\DocumentStatus;
use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\IssueMushakRequest;
use App\Http\Requests\SaveMushakRequest;
use App\Http\Requests\VoidMushakRequest;
use App\Models\CommercialInvoice;
use App\Models\MushakInvoice;
use App\Models\MushakLine;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Services\AuditLogger;
use App\Tax\MushakPdfRenderer;
use App\Tax\MushakWriter;
use App\Tax\RoundingMethod;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxRuleException;
use App\Tax\TaxRuleMatcher;
use App\Tax\VatPriceCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MushakController extends Controller
{
    use AttemptsTax;

    public function __construct(private readonly TaxAuthorizer $authorizer) {}

    public function index(Request $request, QuotationAuthorizer $quotations): View
    {
        $user = $this->taxActor();
        abort_unless($user->hasPermission('mushak.view'), 403);
        $query = MushakInvoice::query()->with('commercialInvoice');

        if (! $quotations->canAccessCustomerQuotations($user) || ! $user->hasPermission('invoices.view')) {
            $query->whereRaw('1 = 0');
        }

        $status = $request->string('status')->toString();

        if (in_array($status, DocumentStatus::all(), true)) {
            $query->where('status', $status);
        }

        $search = trim($request->string('q')->toString());

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('number', 'like', '%'.$search.'%')
                    ->orWhere('buyer_name', 'like', '%'.$search.'%');
            });
        }

        return view('mushak.index', [
            'invoices' => $query->orderByDesc('id')->paginate(15)->withQueryString(),
            'filters' => ['status' => $status, 'q' => $search],
            'canCreate' => $user->hasPermission('mushak.create'),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->taxActor();
        abort_unless($user->hasPermission('mushak.create'), 403);
        $commercial = CommercialInvoice::query()->find($request->integer('invoice'));

        if (! $commercial instanceof CommercialInvoice || ! $this->authorizer->canCreate($user, $commercial)) {
            return view('mushak.pick', [
                'invoices' => $this->issuedInvoices($user),
            ]);
        }

        return view('mushak.form', $this->formData($commercial, null));
    }

    public function store(SaveMushakRequest $request, MushakWriter $writer): RedirectResponse
    {
        /** @var MushakInvoice $invoice */
        $invoice = $this->attemptTax(fn (): MushakInvoice => $writer->create($this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $invoice)->with('status', 'Draft Mushak 6.3 saved. The VAT percentage is the one you entered.');
    }

    public function show(MushakInvoice $mushak, TaxRuleMatcher $rules, VatPriceCalculator $prices): View
    {
        $mushak = $this->visible($mushak);
        $mushak->load(['lines', 'events.actor', 'commercialInvoice', 'taxRuleVersion.rule', 'issuedBy']);
        $user = $this->taxActor();
        $match = $rules->explain('output_vat', $mushak->stringAttribute('supply_classification'), $mushak->dateAttribute('document_date') ?? '');

        return view('mushak.show', [
            'invoice' => $mushak,
            'match' => $match,
            'prices' => $prices,
            'canUpdate' => $this->authorizer->canUpdate($user, $mushak),
            'canIssue' => $this->authorizer->canIssue($user, $mushak),
            'canVoid' => $this->authorizer->canVoid($user, $mushak),
            'canPdf' => $this->authorizer->canDownload($user, $mushak),
        ]);
    }

    public function edit(MushakInvoice $mushak): View
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canUpdate($this->taxActor(), $mushak), 403);
        $mushak->load('commercialInvoice.lines', 'lines');
        $commercial = $mushak->commercialInvoice;
        abort_unless($commercial instanceof CommercialInvoice, 404);

        return view('mushak.form', $this->formData($commercial, $mushak));
    }

    public function update(SaveMushakRequest $request, MushakInvoice $mushak, MushakWriter $writer): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        $this->attemptTax(fn () => $writer->update($this->taxActor(), $mushak, $request->validated()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Draft Mushak 6.3 updated.');
    }

    public function calculate(Request $request, VatPriceCalculator $calculator): JsonResponse
    {
        $user = $this->taxActor();
        abort_unless($user->hasPermission('mushak.create') || $user->hasPermission('mushak.update'), 403);

        try {
            $precision = (int) $request->input('calculation_precision');
            $method = (string) $request->input('rounding_method');
            $edited = (string) $request->input('edited', 'base');
            $rate = $request->input('vat_rate');
            $rate = is_string($rate) ? $rate : null;
            $price = match ($edited) {
                'final' => $calculator->fromFinal(
                    (string) $request->input('final_amount', ''),
                    $rate,
                    (string) $request->input('adjustment_amount', '0'),
                    $precision,
                    $method,
                ),
                'adjustment' => $calculator->fromAdjustment(
                    (string) $request->input('base_amount', ''),
                    $rate,
                    is_string($request->input('vat_amount')) ? (string) $request->input('vat_amount') : null,
                    (string) $request->input('adjustment_amount', '0'),
                    $precision,
                    $method,
                ),
                default => $calculator->fromBase(
                    (string) $request->input('base_amount', ''),
                    $rate,
                    (string) $request->input('adjustment_amount', '0'),
                    $precision,
                    $method,
                ),
            };
        } catch (TaxRuleException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'field' => $exception->field,
            ], 422);
        }

        return response()->json([
            'base_amount' => $price->baseAmount,
            'vat_rate' => $price->rate,
            'vat_amount' => $price->vatAmount,
            'adjustment_amount' => $price->adjustmentAmount,
            'final_amount' => $price->finalAmount,
            'residual_amount' => $price->residualAmount,
            'origin' => $price->origin,
            'display_base' => $calculator->display($price->baseAmount, $price->roundingMethod),
            'display_vat' => $price->vatAmount === null ? null : $calculator->display($price->vatAmount, $price->roundingMethod),
            'display_adjustment' => $calculator->display($price->adjustmentAmount, $price->roundingMethod),
            'display_final' => $price->finalAmount === null ? null : $calculator->display($price->finalAmount, $price->roundingMethod),
            'explanation' => $calculator->explain($price),
        ]);
    }

    public function issue(IssueMushakRequest $request, MushakInvoice $mushak, MushakWriter $writer): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        $this->attemptTax(fn () => $writer->issue($this->taxActor(), $mushak, $request->validated()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 issued. The commercial invoice was not changed.');
    }

    public function void(VoidMushakRequest $request, MushakInvoice $mushak, MushakWriter $writer): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        $this->attemptTax(fn () => $writer->void($this->taxActor(), $mushak, (string) $request->validated('void_reason')));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 voided. This is not a credit note.');
    }

    public function pdf(MushakInvoice $mushak, MushakPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canDownload($this->taxActor(), $mushak), 403);
        $audit->record('mushak_pdf_downloaded', $mushak, newValues: [
            'number' => $mushak->displayNumber(),
            'status' => $mushak->stringAttribute('status'),
        ], actor: $this->taxActor());

        return $renderer->download($mushak);
    }

    private function visible(MushakInvoice $invoice): MushakInvoice
    {
        abort_unless($this->authorizer->canView($this->taxActor(), $invoice), 404);

        return $invoice;
    }

    /**
     * @return LengthAwarePaginator<int, CommercialInvoice>
     */
    private function issuedInvoices(User $user)
    {
        if (! app(QuotationAuthorizer::class)->canAccessCustomerQuotations($user)) {
            return CommercialInvoice::query()->whereRaw('1 = 0')->paginate(15);
        }

        return CommercialInvoice::query()
            ->where('status', DocumentStatus::ISSUED)
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CommercialInvoice $commercial, ?MushakInvoice $mushak): array
    {
        $commercial->loadMissing('lines', 'quotation');
        $saved = [];

        if ($mushak instanceof MushakInvoice) {
            $mushak->loadMissing('lines');

            foreach ($mushak->lines as $line) {
                $saved[(int) $line->commercial_invoice_line_id] = $line;
            }
        }

        $rows = [];

        foreach ($commercial->lines as $line) {
            $current = $saved[(int) $line->getKey()] ?? null;
            $rows[] = [
                'commercial_invoice_line_id' => $line->id,
                'description' => $current instanceof MushakLine ? $current->stringAttribute('description') : $line->stringAttribute('description'),
                'unit' => $current instanceof MushakLine ? $current->stringAttribute('unit') : $line->stringAttribute('unit'),
                'quantity' => $current instanceof MushakLine ? $this->trimNumber($current->decimalAttribute('quantity')) : $this->trimNumber($line->decimalAttribute('quantity')),
                'base_amount' => $current instanceof MushakLine ? $this->trimNumber($current->decimalAttribute('base_amount')) : $this->trimNumber($line->decimalAttribute('line_total')),
                'vat_rate' => $current instanceof MushakLine ? $this->rateInput($current->rateValue()) : '',
                'vat_amount' => $current instanceof MushakLine && $current->vat_amount !== null ? $this->trimNumber($current->decimalAttribute('vat_amount')) : '',
                'adjustment_amount' => $current instanceof MushakLine ? $this->trimNumber($current->decimalAttribute('adjustment_amount')) : '0',
                'adjustment_reason' => $current instanceof MushakLine ? (string) $current->adjustment_reason : '',
                'final_amount' => $current instanceof MushakLine && $current->final_amount !== null ? $this->trimNumber($current->decimalAttribute('final_amount')) : '',
                'edited' => 'base',
            ];
        }

        return [
            'commercial' => $commercial,
            'mushak' => $mushak,
            'rows' => $rows,
            'methods' => RoundingMethod::all(),
            'today' => now()->timezone('Asia/Dhaka')->toDateString(),
            'partyId' => $this->suggestedPartyId($mushak, $commercial),
        ];
    }

    private function suggestedPartyId(?MushakInvoice $mushak, CommercialInvoice $commercial): ?int
    {
        if ($mushak instanceof MushakInvoice && $mushak->party_id !== null) {
            return $mushak->party_id;
        }

        $quotation = $commercial->quotation;

        return $quotation instanceof Quotation ? $quotation->party_id : null;
    }

    private function rateInput(?string $rate): string
    {
        if ($rate === null) {
            return '';
        }

        $trimmed = rtrim(rtrim($rate, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function trimNumber(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
