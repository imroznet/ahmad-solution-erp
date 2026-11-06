<?php

namespace App\Http\Controllers;

use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentStatus;
use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveMushakRequest;
use App\Http\Requests\VoidTaxDocumentRequest;
use App\Models\CommercialInvoice;
use App\Models\Mushak63Document;
use App\Models\Mushak63Event;
use App\Models\TaxRuleVersion;
use App\Services\AuditLogger;
use App\Tax\IssuanceGate;
use App\Tax\MushakWriter;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxPdfRenderer;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MushakController extends Controller
{
    use AttemptsTax;

    public function index(TaxAuthorizer $authorizer): View
    {
        $user = $this->taxActor();
        $documents = Mushak63Document::query()->with('invoice')->orderByDesc('id')->limit(100)->get()
            ->filter(fn (Mushak63Document $document): bool => $authorizer->canViewMushak($user, $document))
            ->values();

        return view('mushak.index', [
            'documents' => $documents,
            'blockers' => IssuanceGate::blockers(),
        ]);
    }

    public function create(Request $request, DocumentAuthorizer $documents, TaxAuthorizer $authorizer): View
    {
        $invoice = null;
        $invoiceId = $request->integer('invoice');

        if ($invoiceId > 0) {
            $candidate = CommercialInvoice::query()->find($invoiceId);
            abort_unless($candidate instanceof CommercialInvoice && $authorizer->canPrepareMushak($this->taxActor(), $candidate), 404);
            $invoice = $candidate;
        }

        $choices = CommercialInvoice::query()
            ->where('status', DocumentStatus::ISSUED)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->filter(fn (CommercialInvoice $row): bool => $documents->canViewInvoice($this->taxActor(), $row))
            ->values();

        return view('mushak.form', [
            'document' => null,
            'invoice' => $invoice,
            'invoices' => $choices,
            'rules' => $this->rules(),
        ]);
    }

    public function store(SaveMushakRequest $request, MushakWriter $writer, TaxAuthorizer $authorizer): RedirectResponse
    {
        $invoice = CommercialInvoice::query()->find($request->integer('commercial_invoice_id'));
        abort_unless($invoice instanceof CommercialInvoice && $authorizer->canPrepareMushak($this->taxActor(), $invoice), 404);
        $document = $this->attemptTax(fn () => $writer->prepare($invoice, $this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $document)->with('status', 'Mushak working paper saved. It is not an issued tax invoice, and the commercial invoice was not changed.');
    }

    public function show(Mushak63Document $mushak, TaxAuthorizer $authorizer): View
    {
        abort_unless($authorizer->canViewMushak($this->taxActor(), $mushak), 404);
        $mushak->load(['lines', 'events.actor', 'invoice', 'pinnedRule']);
        $user = $this->taxActor();

        return view('mushak.show', [
            'document' => $mushak,
            'blockers' => IssuanceGate::blockers(),
            'canUpdate' => $authorizer->canUpdateMushak($user, $mushak),
            'canIssue' => $authorizer->canAttemptMushakIssue($user, $mushak),
            'canVoid' => $authorizer->canVoidMushak($user, $mushak),
            'canPdf' => $authorizer->canDownloadMushak($user, $mushak),
        ]);
    }

    public function edit(Mushak63Document $mushak, TaxAuthorizer $authorizer): View
    {
        abort_unless($authorizer->canUpdateMushak($this->taxActor(), $mushak), 403);
        $mushak->load('invoice');

        return view('mushak.form', [
            'document' => $mushak,
            'invoice' => $mushak->invoice,
            'invoices' => collect(),
            'rules' => $this->rules(),
        ]);
    }

    public function update(SaveMushakRequest $request, Mushak63Document $mushak, MushakWriter $writer, TaxAuthorizer $authorizer): RedirectResponse
    {
        abort_unless($authorizer->canUpdateMushak($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $writer->updateDraft($mushak, $this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak working paper updated.');
    }

    public function issue(Mushak63Document $mushak, MushakWriter $writer, TaxAuthorizer $authorizer): RedirectResponse
    {
        abort_unless($authorizer->canAttemptMushakIssue($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $writer->issue($mushak, $this->taxActor()));

        return redirect()->route('mushak.show', $mushak);
    }

    public function void(VoidTaxDocumentRequest $request, Mushak63Document $mushak, MushakWriter $writer, TaxAuthorizer $authorizer): RedirectResponse
    {
        abort_unless($authorizer->canVoidMushak($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $writer->void($mushak, $this->taxActor(), (string) $request->validated('reason')));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak working paper voided. The commercial invoice was not voided.');
    }

    public function pdf(Mushak63Document $mushak, TaxAuthorizer $authorizer, TaxPdfRenderer $renderer, AuditLogger $audit): Response
    {
        abort_unless($authorizer->canDownloadMushak($this->taxActor(), $mushak), 404);
        Mushak63Event::query()->create([
            'mushak63_document_id' => $mushak->getKey(),
            'event' => Mushak63Event::PDF_DOWNLOADED,
            'actor_id' => $this->taxActor()->getKey(),
            'remarks' => 'Working paper downloaded.',
            'created_at' => now(),
        ]);
        $audit->record('mushak_pdf_downloaded', $mushak, newValues: [
            'status' => $mushak->stringAttribute('status'),
        ], actor: $this->taxActor());

        return $renderer->downloadMushak($mushak);
    }

    /**
     * @return Collection<int, TaxRuleVersion>
     */
    private function rules(): Collection
    {
        return TaxRuleVersion::query()
            ->where('tax_type', TaxType::OUTPUT_VAT)
            ->where('approval_status', TaxRuleStatus::APPROVED)
            ->orderBy('supply_classification')
            ->get();
    }
}
