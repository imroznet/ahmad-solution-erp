<?php

namespace App\Http\Controllers;

use App\Documents\DocumentStatus;
use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\RecordMushakTreatmentRequest;
use App\Http\Requests\SaveMushakDocumentRequest;
use App\Models\CommercialInvoice;
use App\Models\MushakDocument;
use App\Models\Party;
use App\Models\TaxRule;
use App\Services\AuditLogger;
use App\Tax\MushakAuthorizer;
use App\Tax\MushakPdfRenderer;
use App\Tax\MushakWorkflow;
use App\Tax\MushakWriter;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MushakDocumentController extends Controller
{
    use AttemptsTax;

    public function __construct(private readonly MushakAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $user = $this->taxActor();
        $documents = MushakDocument::query()
            ->with('party')
            ->where(function ($query) use ($user): void {
                $query->whereNull('quotation_id');

                if ($user->hasPermission('quotations.view') && ($user->hasPermission('customers.view') || $user->hasPermission('customers.manage') || $user->hasPermission('parties.view'))) {
                    $query->orWhereHas('quotation', fn ($quotation) => $quotation->where('party_role', 'customer'));
                }
            })
            ->when($request->string('number')->toString() !== '', fn ($query) => $query->where('number', 'like', '%'.$request->string('number')->toString().'%'))
            ->when($request->string('buyer')->toString() !== '', fn ($query) => $query->where('buyer_name', 'like', '%'.$request->string('buyer')->toString().'%'))
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('tax.mushak.index', [
            'documents' => $documents,
            'filters' => [
                'number' => $request->string('number')->toString(),
                'buyer' => $request->string('buyer')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'canCreate' => $user->hasPermission('mushak.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->taxActor()->hasPermission('mushak.create'), 403);

        return view('tax.mushak.form', $this->formData(null, $request));
    }

    public function store(SaveMushakDocumentRequest $request, MushakWriter $writer): RedirectResponse
    {
        $document = $this->attemptTax(fn (): MushakDocument => $writer->create($this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $document)->with('status', 'Draft Mushak 6.3 saved. VAT was stored only where you typed it.');
    }

    public function show(MushakDocument $mushak): View
    {
        $mushak = $this->visible($mushak);
        $mushak->load(['lines', 'events.actor', 'invoice', 'party', 'vdsRecords', 'aitRecords', 'treatmentRule']);
        $user = $this->taxActor();
        $rules = TaxRule::query()
            ->where('tax_type', TaxType::OUTPUT_VAT)
            ->where('status', TaxRuleStatus::APPROVED)
            ->orderBy('rule_key')
            ->orderBy('version')
            ->get();

        return view('tax.mushak.show', [
            'document' => $mushak,
            'rules' => $rules,
            'canUpdate' => $this->authorizer->canUpdate($user, $mushak),
            'canIssue' => $this->authorizer->canIssue($user, $mushak),
            'canVoid' => $this->authorizer->canVoid($user, $mushak),
            'canPdf' => $this->authorizer->canDownload($user, $mushak),
            'canRecordTreatment' => $this->authorizer->canRecordTreatment($user, $mushak),
        ]);
    }

    public function edit(Request $request, MushakDocument $mushak): View
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canUpdate($this->taxActor(), $mushak), 403);

        return view('tax.mushak.form', $this->formData($mushak, $request));
    }

    public function update(SaveMushakDocumentRequest $request, MushakDocument $mushak, MushakWriter $writer): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        $this->attemptTax(fn () => $writer->updateDraft($mushak, $this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Draft Mushak 6.3 updated.');
    }

    public function issue(MushakDocument $mushak, MushakWorkflow $workflow): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canIssue($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $workflow->issue($mushak, $this->taxActor()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 issued. It stays marked for tax review. The ERP did not determine the VAT rate.');
    }

    public function void(Request $request, MushakDocument $mushak, MushakWorkflow $workflow): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canVoid($this->taxActor(), $mushak), 403);
        $reason = $request->string('void_reason')->toString();
        $this->attemptTax(fn () => $workflow->void($mushak, $this->taxActor(), $reason));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 voided. The issued figures were not edited, and no statutory credit note was created.');
    }

    public function treatment(RecordMushakTreatmentRequest $request, MushakDocument $mushak, MushakWorkflow $workflow): RedirectResponse
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canRecordTreatment($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $workflow->recordTreatment($mushak, $this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Treatment recorded. The typed VAT figures were not changed.');
    }

    public function pdf(Request $request, MushakDocument $mushak, MushakPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $mushak = $this->visible($mushak);
        abort_unless($this->authorizer->canDownload($this->taxActor(), $mushak), 403);
        $copy = $request->string('copy')->toString();
        $copy = $copy === '' ? 'original' : $copy;
        abort_unless(in_array($copy, ['original', 'duplicate'], true), 404);
        $audit->record('mushak_pdf_downloaded', $mushak, [], [
            'number' => $mushak->nullableAttribute('number'),
            'status' => $mushak->stringAttribute('status'),
            'copy' => $copy,
        ], $this->taxActor());

        return $this->attemptTax(fn (): Response => $renderer->download($mushak, $copy));
    }

    private function visible(MushakDocument $document): MushakDocument
    {
        abort_unless($this->authorizer->canView($this->taxActor(), $document), 404);

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?MushakDocument $document, Request $request): array
    {
        $invoice = null;
        $invoiceId = $document instanceof MushakDocument
            ? (int) $document->commercial_invoice_id
            : $request->integer('invoice');

        if ($invoiceId > 0) {
            $candidate = CommercialInvoice::query()->with(['lines', 'quotation'])->find($invoiceId);

            if ($candidate instanceof CommercialInvoice
                && $candidate->stringAttribute('status') === DocumentStatus::ISSUED
                && $this->authorizer->documents()->canViewInvoice($this->taxActor(), $candidate)) {
                $invoice = $candidate;
            }
        }

        $lines = [];

        if ($document instanceof MushakDocument) {
            foreach ($document->lines as $line) {
                $lines[] = [
                    'commercial_invoice_line_id' => $line->commercial_invoice_line_id,
                    'description' => $line->text('description'),
                    'unit' => $line->text('unit'),
                    'quantity' => $line->nullableDecimal('quantity'),
                    'unit_price' => $line->nullableDecimal('unit_price'),
                    'line_value' => $line->nullableDecimal('line_value'),
                    'supplementary_duty' => $line->nullableDecimal('supplementary_duty'),
                    'vat_rate' => $line->nullableDecimal('vat_rate'),
                    'specific_tax_note' => $line->text('specific_tax_note'),
                    'vat_amount' => $line->nullableDecimal('vat_amount'),
                    'inclusive_value' => $line->nullableDecimal('inclusive_value'),
                ];
            }
        } elseif ($invoice instanceof CommercialInvoice) {
            foreach ($invoice->lines as $line) {
                $lines[] = [
                    'commercial_invoice_line_id' => $line->id,
                    'description' => $line->stringAttribute('description'),
                    'unit' => $line->stringAttribute('unit'),
                    'quantity' => $line->decimalAttribute('quantity'),
                    'unit_price' => $line->decimalAttribute('unit_price'),
                    'line_value' => $line->decimalAttribute('line_total'),
                    'supplementary_duty' => null,
                    'vat_rate' => null,
                    'specific_tax_note' => null,
                    'vat_amount' => null,
                    'inclusive_value' => null,
                ];
            }
        }

        if ($lines === []) {
            $lines[] = [
                'commercial_invoice_line_id' => null,
                'description' => null,
                'unit' => null,
                'quantity' => null,
                'unit_price' => null,
                'line_value' => null,
                'supplementary_duty' => null,
                'vat_rate' => null,
                'specific_tax_note' => null,
                'vat_amount' => null,
                'inclusive_value' => null,
            ];
        }

        return [
            'document' => $document,
            'invoice' => $invoice,
            'lines' => $lines,
            'customers' => Party::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->where('role', 'customer'))
                ->orderBy('legal_name')
                ->get(),
        ];
    }
}
