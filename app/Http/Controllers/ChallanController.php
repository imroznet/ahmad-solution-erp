<?php

namespace App\Http\Controllers;

use App\Documents\ChallanWorkflow;
use App\Documents\ChallanWriter;
use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentBalance;
use App\Documents\DocumentListing;
use App\Documents\DocumentPdfRenderer;
use App\Http\Controllers\Concerns\AttemptsDocument;
use App\Http\Requests\AcknowledgeChallanRequest;
use App\Http\Requests\DispatchChallanRequest;
use App\Http\Requests\SaveChallanRequest;
use App\Http\Requests\VoidDocumentRequest;
use App\Models\DeliveryChallan;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\User;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationStatus;
use App\Services\AuditLogger;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ChallanController extends Controller
{
    use AttemptsDocument;

    public function __construct(private readonly DocumentAuthorizer $authorizer) {}

    public function index(Request $request, DocumentListing $listing): View
    {
        $user = $this->documentActor();

        return view('challans.index', [
            'challans' => $listing->challans($request, $user),
            'filters' => $listing->filters($request),
            'canCreate' => $user->hasPermission('challans.create'),
        ]);
    }

    public function create(Request $request, QuotationAuthorizer $quotations, DocumentBalance $balances): View
    {
        $user = $this->documentActor();
        abort_unless($user->hasPermission('challans.create'), 403);
        $quotation = $this->selectedQuotation($request, $quotations);

        if (! $quotation instanceof Quotation) {
            return view('challans.pick', [
                'quotations' => $this->acceptedQuotations($quotations, $user),
                'message' => null,
            ]);
        }

        if ($quotation->stringAttribute('status') !== QuotationStatus::ACCEPTED) {
            return view('challans.pick', [
                'quotations' => $this->acceptedQuotations($quotations, $user),
                'message' => 'Only an accepted quotation can be used. This quotation is '.$quotation->statusLabel().'.',
            ]);
        }

        return $this->form($quotation, null, $balances);
    }

    public function store(SaveChallanRequest $request, ChallanWriter $writer): RedirectResponse
    {
        /** @var DeliveryChallan $challan */
        $challan = $this->attemptDocument(fn (): DeliveryChallan => $writer->create($this->documentActor(), $request->validated()));

        return redirect()->route('challans.show', $challan)->with('status', 'Draft delivery challan saved. It has no number until it is issued.');
    }

    public function show(DeliveryChallan $challan): View
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        $challan->load(['lines', 'events.actor', 'quotation', 'revision', 'createdBy', 'issuedBy', 'dispatchedBy', 'acknowledgedBy', 'voidedBy']);
        $user = $this->documentActor();

        return view('challans.show', [
            'challan' => $challan,
            'canUpdate' => $this->authorizer->canUpdateChallan($user, $challan),
            'canIssue' => $this->authorizer->canIssueChallan($user, $challan),
            'canDispatch' => $this->authorizer->canDispatchChallan($user, $challan),
            'canAcknowledge' => $this->authorizer->canAcknowledgeChallan($user, $challan),
            'canVoid' => $this->authorizer->canVoidChallan($user, $challan),
            'canPdf' => $this->authorizer->canDownloadChallan($user, $challan),
        ]);
    }

    public function edit(DeliveryChallan $challan, DocumentBalance $balances): View
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        abort_unless($this->authorizer->canUpdateChallan($this->documentActor(), $challan), 403);
        $challan->load('quotation.currentRevision.lines');
        $quotation = $challan->quotation;
        abort_unless($quotation instanceof Quotation, 404);

        return $this->form($quotation, $challan, $balances);
    }

    public function update(SaveChallanRequest $request, DeliveryChallan $challan, ChallanWriter $writer): RedirectResponse
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        $this->attemptDocument(fn () => $writer->updateDraft($challan, $this->documentActor(), $request->validated()));

        return redirect()->route('challans.show', $challan)->with('status', 'Draft challan updated.');
    }

    public function issue(DeliveryChallan $challan, ChallanWorkflow $workflow): RedirectResponse
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        abort_unless($this->documentActor()->hasPermission('challans.issue'), 403);
        $this->attemptDocument(fn () => $workflow->issue($challan, $this->documentActor()));

        return redirect()->route('challans.show', $challan)->with('status', 'Delivery challan issued. Downloading the PDF does not record dispatch or receipt.');
    }

    public function dispatch(DispatchChallanRequest $request, DeliveryChallan $challan, ChallanWorkflow $workflow): RedirectResponse
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        $this->attemptDocument(fn () => $workflow->dispatch($challan, $this->documentActor(), $request->validated()));

        return redirect()->route('challans.show', $challan)->with('status', 'Dispatch recorded.');
    }

    public function acknowledge(AcknowledgeChallanRequest $request, DeliveryChallan $challan, ChallanWorkflow $workflow): RedirectResponse
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        $this->attemptDocument(fn () => $workflow->acknowledge($challan, $this->documentActor(), $request->validated()));

        return redirect()->route('challans.show', $challan)->with('status', 'Acknowledgment recorded.');
    }

    public function void(VoidDocumentRequest $request, DeliveryChallan $challan, ChallanWorkflow $workflow): RedirectResponse
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        $this->attemptDocument(fn () => $workflow->void($challan, $this->documentActor(), (string) $request->validated('void_reason')));

        return redirect()->route('challans.show', $challan)->with('status', 'Delivery challan voided. The record was kept.');
    }

    public function pdf(DeliveryChallan $challan, DocumentPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $challan = $this->visibleChallan($challan, $this->authorizer);
        abort_unless($this->authorizer->canDownloadChallan($this->documentActor(), $challan), 403);
        $audit->record('delivery_challan_pdf_downloaded', $challan, newValues: [
            'number' => $challan->displayNumber(),
            'status' => $challan->stringAttribute('status'),
        ], actor: $this->documentActor());

        return $renderer->downloadChallan($challan);
    }

    private function form(Quotation $quotation, ?DeliveryChallan $challan, DocumentBalance $balances): View
    {
        $quotation->loadMissing('currentRevision.lines');
        $revision = $quotation->currentRevision;
        abort_unless($revision instanceof QuotationRevision, 404);
        $saved = [];

        if ($challan instanceof DeliveryChallan) {
            $challan->loadMissing('lines');

            foreach ($challan->lines as $line) {
                $saved[(int) $line->quotation_line_id] = $line->quantityLabel();
            }
        }

        $old = $this->oldQuantities('quotation_line_id');
        $rows = [];

        foreach ($revision->lines as $line) {
            $lineId = (int) $line->getKey();
            $delivered = $balances->deliveredQuantity($lineId, $challan instanceof DeliveryChallan ? (int) $challan->getKey() : null);
            $remaining = Decimal::of($line->decimalAttribute('quantity'))->minus(Decimal::of($delivered));

            if ($remaining->isNegative()) {
                $remaining = Decimal::zero();
            }

            $default = $challan instanceof DeliveryChallan
                ? ($saved[$lineId] ?? '0')
                : QuotationCalculator::formatQuantity((string) $remaining->toScale(4));
            $rows[] = [
                'id' => $lineId,
                'description' => $line->description,
                'unit' => $line->unit,
                'accepted' => $line->quantityLabel(),
                'delivered' => QuotationCalculator::formatQuantity($delivered),
                'quantity' => $old[$lineId] ?? $default,
            ];
        }

        return view('challans.form', [
            'quotation' => $quotation,
            'revision' => $revision,
            'challan' => $challan,
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

    /**
     * @return array<int, string>
     */
    private function oldQuantities(string $idKey): array
    {
        $old = old('lines');
        $mapped = [];

        if (! is_array($old)) {
            return $mapped;
        }

        foreach ($old as $line) {
            if (! is_array($line) || ! isset($line[$idKey])) {
                continue;
            }

            $mapped[(int) $line[$idKey]] = trim((string) ($line['quantity'] ?? ''));
        }

        return $mapped;
    }
}
