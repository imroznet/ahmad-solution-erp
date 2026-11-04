<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsQuotation;
use App\Http\Requests\SaveQuotationRequest;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Quotations\QuotationAuthorizer;
use App\Quotations\QuotationListing;
use App\Quotations\QuotationPdfRenderer;
use App\Quotations\QuotationWriter;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    use AttemptsQuotation;

    public function __construct(private readonly QuotationAuthorizer $authorizer) {}

    public function index(Request $request, QuotationListing $listing): View
    {
        $user = $this->quotationActor();

        return view('quotations.index', [
            'quotations' => $listing->paginate($request, $user),
            'filters' => $listing->filters($request, $user),
            'canCreate' => $this->authorizer->canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->quotationActor();
        abort_unless($this->authorizer->canCreate($user), 403);
        $party = $this->selectedCustomer($request);

        if (! $party instanceof Party) {
            return view('quotations.pick-customer', [
                'customers' => $this->customers($request),
                'search' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            ]);
        }

        $party->load(['contacts' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')->orderBy('name'), 'addresses' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_default_billing')->orderBy('id')]);

        return view('quotations.form', [
            'quotation' => null,
            'revision' => null,
            'mode' => 'create',
            'party' => $party,
            'customers' => $this->withParty($this->customers($request), $party),
            'lines' => [['description' => '', 'unit' => '', 'quantity' => '1', 'unit_price' => '0.00']],
        ]);
    }

    public function store(SaveQuotationRequest $request, QuotationWriter $writer): RedirectResponse
    {
        /** @var Quotation $quotation */
        $quotation = $this->attemptQuotation(fn (): Quotation => $writer->create($this->quotationActor(), $request->validated()));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Draft quotation saved.');
    }

    public function show(Quotation $quotation): View
    {
        return $this->renderShow($this->visibleQuotation($quotation, $this->authorizer), null);
    }

    public function revision(Quotation $quotation, int $revision): View
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);

        return $this->renderShow($quotation, $this->findRevision($quotation, $revision));
    }

    public function edit(Quotation $quotation): View
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        $user = $this->quotationActor();

        if ($this->authorizer->canUpdate($user, $quotation)) {
            return $this->form($quotation, 'draft');
        }

        abort_unless($this->authorizer->canRevise($user, $quotation), 403);

        return $this->form($quotation, 'revise');
    }

    public function update(SaveQuotationRequest $request, Quotation $quotation, QuotationWriter $writer): RedirectResponse
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        $this->attemptQuotation(fn () => $writer->updateDraft($quotation, $this->quotationActor(), $request->validated()));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Draft updated.');
    }

    public function storeRevision(SaveQuotationRequest $request, Quotation $quotation, QuotationWriter $writer): RedirectResponse
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        $this->attemptQuotation(fn () => $writer->revise($quotation, $this->quotationActor(), $request->validated()));

        return redirect()->route('quotations.show', $quotation)->with('status', 'New draft revision saved. The previous revision was kept.');
    }

    public function pdf(Quotation $quotation, int $revision, QuotationPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $quotation = $this->visibleQuotation($quotation, $this->authorizer);
        $record = $this->findRevision($quotation, $revision);
        $audit->record('quotation_pdf_downloaded', $quotation, newValues: [
            'number' => $quotation->stringAttribute('number'),
            'revision_number' => $record->revision_number,
        ], actor: $this->quotationActor());

        return $renderer->download($quotation, $record);
    }

    private function renderShow(Quotation $quotation, ?QuotationRevision $selected): View
    {
        $quotation->load([
            'party',
            'createdBy',
            'currentRevision.lines',
            'currentRevision.approvals.user',
            'currentRevision.sends.sender',
            'currentRevision.response',
            'currentRevision.shareTokens.createdBy',
            'revisions',
        ]);
        $revision = $selected ?? $quotation->currentRevision;
        abort_unless($revision instanceof QuotationRevision, 404);

        if ($selected instanceof QuotationRevision) {
            $revision->load(['lines', 'approvals.user', 'sends.sender', 'response', 'shareTokens.createdBy']);
        }

        $user = $this->quotationActor();

        return view('quotations.show', [
            'quotation' => $quotation,
            'revision' => $revision,
            'isCurrent' => (int) $quotation->current_revision_id === (int) $revision->getKey(),
            'shareUrl' => session('quotation_share_url'),
            'canUpdate' => $this->authorizer->canUpdate($user, $quotation),
            'canRevise' => $this->authorizer->canRevise($user, $quotation),
            'canSubmit' => $this->authorizer->canSubmit($user, $quotation),
            'canApprove' => $this->authorizer->canApprove($user, $quotation),
            'canReturn' => $this->authorizer->canReturn($user, $quotation),
            'canSend' => $this->authorizer->canSend($user, $quotation),
            'canCancel' => $this->authorizer->canCancel($user, $quotation),
            'canExpire' => $this->authorizer->canExpire($user, $quotation),
            'canRespond' => $this->authorizer->canRespond($user, $quotation),
            'canShare' => $this->authorizer->canShare($user, $quotation),
            'canRevokeShare' => $this->authorizer->canRevokeShare($user, $quotation),
            'shareDays' => (int) config('asl.quotations.share_default_days', 14),
            'shareMaxDays' => (int) config('asl.quotations.share_max_days', 90),
        ]);
    }

    private function form(Quotation $quotation, string $mode): View
    {
        $quotation->load(['currentRevision.lines', 'party']);
        $requestedParty = request()->query('party');
        $rememberedParty = old('party_id');
        $partyId = $mode === 'revise'
            ? (int) $quotation->party_id
            : (int) (is_string($requestedParty) && $requestedParty !== ''
                ? $requestedParty
                : (is_scalar($rememberedParty) && (string) $rememberedParty !== '' ? $rememberedParty : $quotation->party_id));
        $party = Party::query()->whereKey($partyId)->where('status', 'active')->where('currency_code', Money::DEFAULT_CURRENCY)->first();

        if (! $party instanceof Party || ! $party->hasRole('customer')) {
            $party = $quotation->party;
        }

        abort_unless($party instanceof Party, 404);
        $party->load([
            'contacts' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')->orderBy('name'),
            'addresses' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_default_billing')->orderBy('id'),
        ]);
        $revision = $quotation->currentRevision;
        $lines = [];

        if ($revision instanceof QuotationRevision) {
            foreach ($revision->lines as $line) {
                $lines[] = [
                    'description' => $line->description,
                    'unit' => $line->unit,
                    'quantity' => $line->quantityLabel(),
                    'unit_price' => $line->decimalAttribute('unit_price'),
                ];
            }
        }

        return view('quotations.form', [
            'quotation' => $quotation,
            'revision' => $revision,
            'mode' => $mode,
            'party' => $party,
            'customers' => $mode === 'revise' ? collect([$party]) : $this->withParty($this->customers(request()), $party),
            'lines' => $lines === [] ? [['description' => '', 'unit' => '', 'quantity' => '1', 'unit_price' => '0.00']] : $lines,
        ]);
    }

    private function findRevision(Quotation $quotation, int $number): QuotationRevision
    {
        $revision = $quotation->revisions()->where('revision_number', $number)->first();
        abort_unless($revision instanceof QuotationRevision, 404);

        return $revision;
    }

    private function selectedCustomer(Request $request): ?Party
    {
        $partyId = $request->query('party');

        if (! is_string($partyId) || preg_match('/^[1-9][0-9]*$/', $partyId) !== 1) {
            return null;
        }

        $party = Party::query()->whereKey($partyId)->where('status', 'active')->where('currency_code', Money::DEFAULT_CURRENCY)->first();

        if (! $party instanceof Party || ! $party->hasRole('customer')) {
            abort(404);
        }

        return $party;
    }

    /**
     * @return Collection<int, Party>
     */
    private function customers(Request $request): Collection
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $query = Party::query()
            ->select(['id', 'legal_name', 'trading_name'])
            ->where('status', 'active')
            ->where('currency_code', Money::DEFAULT_CURRENCY)
            ->whereHas('roles', fn ($roles) => $roles->where('role', 'customer'))
            ->orderBy('legal_name');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '\\%_').'%';
            $query->where('legal_name', 'like', $like);
        }

        return $query->limit(100)->get();
    }

    /**
     * @param  Collection<int, Party>  $customers
     * @return Collection<int, Party>
     */
    private function withParty(Collection $customers, Party $party): Collection
    {
        if (! $customers->contains(fn (Party $row): bool => (int) $row->getKey() === (int) $party->getKey())) {
            $customers->prepend($party);
        }

        return $customers;
    }
}
