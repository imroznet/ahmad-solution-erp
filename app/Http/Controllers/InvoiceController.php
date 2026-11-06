<?php

namespace App\Http\Controllers;

use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentBalance;
use App\Documents\DocumentListing;
use App\Documents\DocumentPdfRenderer;
use App\Documents\DocumentStatus;
use App\Documents\InvoiceWorkflow;
use App\Documents\InvoiceWriter;
use App\Http\Controllers\Concerns\AttemptsDocument;
use App\Http\Requests\SaveInvoiceRequest;
use App\Http\Requests\VoidDocumentRequest;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallanLine;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationStatus;
use App\Services\AuditLogger;
use App\Support\Decimal;
use App\Tax\TaxAuthorizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use AttemptsDocument;

    public function __construct(private readonly DocumentAuthorizer $authorizer) {}

    public function index(Request $request, DocumentListing $listing): View
    {
        $user = $this->documentActor();

        return view('invoices.index', [
            'invoices' => $listing->invoices($request, $user),
            'filters' => $listing->filters($request),
            'canCreate' => $user->hasPermission('invoices.create'),
        ]);
    }

    public function create(Request $request, QuotationAuthorizer $quotations, DocumentBalance $balances): View
    {
        $user = $this->documentActor();
        abort_unless($user->hasPermission('invoices.create'), 403);
        $quotation = $this->selectedQuotation($request, $quotations);

        if (! $quotation instanceof Quotation) {
            return view('invoices.pick', [
                'quotations' => $this->acceptedQuotations($quotations, $user),
                'message' => null,
            ]);
        }

        if ($quotation->stringAttribute('status') !== QuotationStatus::ACCEPTED) {
            return view('invoices.pick', [
                'quotations' => $this->acceptedQuotations($quotations, $user),
                'message' => 'Only an accepted quotation can be used. This quotation is '.$quotation->statusLabel().'.',
            ]);
        }

        return $this->form($quotation, null, $balances);
    }

    public function store(SaveInvoiceRequest $request, InvoiceWriter $writer): RedirectResponse
    {
        /** @var CommercialInvoice $invoice */
        $invoice = $this->attemptDocument(fn (): CommercialInvoice => $writer->create($this->documentActor(), $request->validated()));

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft commercial invoice saved. Amounts are calculated again when it is issued.');
    }

    public function show(CommercialInvoice $invoice): View
    {
        $invoice = $this->visibleInvoice($invoice, $this->authorizer);
        $invoice->load(['lines.challanLine.challan', 'events.actor', 'quotation', 'revision', 'createdBy', 'issuedBy', 'voidedBy']);
        $user = $this->documentActor();

        return view('invoices.show', [
            'invoice' => $invoice,
            'canUpdate' => $this->authorizer->canUpdateInvoice($user, $invoice),
            'canIssue' => $this->authorizer->canIssueInvoice($user, $invoice),
            'canVoid' => $this->authorizer->canVoidInvoice($user, $invoice),
            'canPdf' => $this->authorizer->canDownloadInvoice($user, $invoice),
            'canMushak' => $invoice->stringAttribute('status') === DocumentStatus::ISSUED && app(TaxAuthorizer::class)->canCreate($user, $invoice),
        ]);
    }

    public function edit(CommercialInvoice $invoice, DocumentBalance $balances): View
    {
        $invoice = $this->visibleInvoice($invoice, $this->authorizer);
        abort_unless($this->authorizer->canUpdateInvoice($this->documentActor(), $invoice), 403);
        $invoice->load('quotation');
        $quotation = $invoice->quotation;
        abort_unless($quotation instanceof Quotation, 404);

        return $this->form($quotation, $invoice, $balances);
    }

    public function update(SaveInvoiceRequest $request, CommercialInvoice $invoice, InvoiceWriter $writer): RedirectResponse
    {
        $invoice = $this->visibleInvoice($invoice, $this->authorizer);
        $this->attemptDocument(fn () => $writer->updateDraft($invoice, $this->documentActor(), $request->validated()));

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice updated.');
    }

    public function issue(CommercialInvoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $invoice = $this->visibleInvoice($invoice, $this->authorizer);
        abort_unless($this->documentActor()->hasPermission('invoices.issue'), 403);
        $this->attemptDocument(fn () => $workflow->issue($invoice, $this->documentActor()));

        return redirect()->route('invoices.show', $invoice)->with('status', 'Commercial invoice issued. It is not a tax invoice.');
    }

    public function void(VoidDocumentRequest $request, CommercialInvoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $invoice = $this->visibleInvoice($invoice, $this->authorizer);
        $this->attemptDocument(fn () => $workflow->void($invoice, $this->documentActor(), (string) $request->validated('void_reason')));

        return redirect()->route('invoices.show', $invoice)->with('status', 'Commercial invoice voided. The challan was not voided.');
    }

    public function pdf(CommercialInvoice $invoice, DocumentPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $invoice = $this->visibleInvoice($invoice, $this->authorizer);
        abort_unless($this->authorizer->canDownloadInvoice($this->documentActor(), $invoice), 403);
        $audit->record('commercial_invoice_pdf_downloaded', $invoice, newValues: [
            'number' => $invoice->displayNumber(),
            'status' => $invoice->stringAttribute('status'),
        ], actor: $this->documentActor());

        return $renderer->downloadInvoice($invoice);
    }

    private function form(Quotation $quotation, ?CommercialInvoice $invoice, DocumentBalance $balances): View
    {
        $quotation->loadMissing('currentRevision');
        $revision = $quotation->currentRevision;
        abort_unless($revision instanceof QuotationRevision, 404);
        $saved = [];

        if ($invoice instanceof CommercialInvoice) {
            $invoice->loadMissing('lines');

            foreach ($invoice->lines as $line) {
                $saved[(int) $line->delivery_challan_line_id] = $line->quantityLabel();
            }
        }

        $old = [];
        $posted = old('lines');

        if (is_array($posted)) {
            foreach ($posted as $line) {
                if (is_array($line) && isset($line['delivery_challan_line_id'])) {
                    $old[(int) $line['delivery_challan_line_id']] = trim((string) ($line['quantity'] ?? ''));
                }
            }
        }

        $challanLines = DeliveryChallanLine::query()
            ->whereHas('challan', fn ($query) => $query->where('quotation_id', $quotation->getKey())->where('status', DocumentStatus::ISSUED))
            ->with('challan')
            ->orderBy('id')
            ->get();
        $rows = [];

        foreach ($challanLines as $line) {
            $lineId = (int) $line->getKey();
            $invoiced = $balances->invoicedQuantityForChallanLine($lineId, $invoice instanceof CommercialInvoice ? (int) $invoice->getKey() : null);
            $remaining = Decimal::of($line->decimalAttribute('quantity'))->minus(Decimal::of($invoiced));

            if ($remaining->isNegative()) {
                $remaining = Decimal::zero();
            }

            $default = $invoice instanceof CommercialInvoice
                ? ($saved[$lineId] ?? '0')
                : QuotationCalculator::formatQuantity((string) $remaining->toScale(4));
            $rows[] = [
                'id' => $lineId,
                'challan' => $line->challan?->displayNumber() ?? 'Challan',
                'description' => $line->stringAttribute('description'),
                'unit' => $line->stringAttribute('unit'),
                'delivered' => $line->quantityLabel(),
                'invoiced' => QuotationCalculator::formatQuantity($invoiced),
                'quantity' => $old[$lineId] ?? $default,
            ];
        }

        return view('invoices.form', [
            'quotation' => $quotation,
            'revision' => $revision,
            'invoice' => $invoice,
            'rows' => $rows,
            'today' => now()->timezone('Asia/Dhaka')->toDateString(),
        ]);
    }

    private function selectedQuotation(Request $request, QuotationAuthorizer $quotations): ?Quotation
    {
        $value = $request->query('quotation', $request->input('quotation_id'));

        if (! is_scalar($value) || preg_match('/^[1-9][0-9]*$/', (string) $value) !== 1) {
            return null;
        }

        $quotation = Quotation::query()->find((int) $value);

        if (! $quotation instanceof Quotation || ! $quotations->canView($this->documentActor(), $quotation)) {
            abort(404);
        }

        return $quotation;
    }

    /**
     * @return Collection<int, Quotation>
     */
    private function acceptedQuotations(QuotationAuthorizer $quotations, User $user): Collection
    {
        if (! $quotations->canAccessCustomerQuotations($user)) {
            return new Collection;
        }

        return Quotation::query()
            ->where('status', QuotationStatus::ACCEPTED)
            ->where('party_role', 'customer')
            ->with('currentRevision:id,quotation_id,party_legal_name')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }
}
