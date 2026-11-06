<?php

namespace App\Http\Controllers;

use App\Documents\DocumentAuthorizer;
use App\Documents\DocumentStatus;
use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveMushakRequest;
use App\Http\Requests\VoidTaxRecordRequest;
use App\Models\AitRecord;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceLine;
use App\Models\Mushak63Document;
use App\Models\Mushak63Line;
use App\Models\Party;
use App\Models\TaxRuleVersion;
use App\Models\VdsRecord;
use App\Services\AuditLogger;
use App\Tax\MushakPdfRenderer;
use App\Tax\MushakWorkflow;
use App\Tax\MushakWriter;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxType;
use App\Tax\VatPriceCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MushakController extends Controller
{
    use AttemptsTax;

    public function __construct(private readonly TaxAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $user = $this->taxActor();
        $documents = Mushak63Document::query()->whereKey([]);

        if ($this->authorizer->canListMushak($user)) {
            $documents = Mushak63Document::query()
                ->with('invoice.quotation')
                ->when(is_string($request->query('status')) && $request->query('status') !== '', function ($query) use ($request): void {
                    $query->where('status', $request->query('status'));
                })
                ->orderByDesc('id');
        }

        $documents = $documents->paginate(20)->withQueryString();

        return view('tax.mushak.index', [
            'documents' => $documents,
            'canCreate' => $user->hasPermission('mushak.create'),
            'status' => is_string($request->query('status')) ? $request->query('status') : '',
        ]);
    }

    public function create(Request $request, DocumentAuthorizer $invoices): View
    {
        $invoice = $this->selectedInvoice($request, $invoices);

        if (! $invoice instanceof CommercialInvoice) {
            return view('tax.mushak.pick', [
                'invoices' => $this->issuedInvoices($invoices),
            ]);
        }

        return $this->form($invoice, null);
    }

    public function store(SaveMushakRequest $request, MushakWriter $writer, DocumentAuthorizer $invoices): RedirectResponse
    {
        $this->visibleInvoice((int) $request->validated('commercial_invoice_id'), $invoices);
        /** @var Mushak63Document $document */
        $document = $this->attemptTax(fn (): Mushak63Document => $writer->create($this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $document)->with('status', 'Draft Mushak 6.3 saved. VAT amounts were calculated from the percentages you entered.');
    }

    public function calculate(SaveMushakRequest $request, MushakWriter $writer, DocumentAuthorizer $invoices): JsonResponse
    {
        $this->visibleInvoice((int) $request->validated('commercial_invoice_id'), $invoices);

        /** @var array<string, mixed> $result */
        $result = $this->attemptTax(fn (): array => $writer->calculate($this->taxActor(), $request->validated()));

        return response()->json($result);
    }

    public function show(Mushak63Document $mushak): View
    {
        $mushak = $this->visibleMushak($mushak);
        $mushak->load(['lines.rateEnteredBy', 'invoice', 'events.actor', 'createdBy', 'issuedBy', 'voidedBy', 'treatmentConfirmedBy']);
        $user = $this->taxActor();

        return view('tax.mushak.show', [
            'document' => $mushak,
            'canUpdate' => $this->authorizer->canUpdateMushak($user, $mushak),
            'canIssue' => $this->authorizer->canIssueMushak($user, $mushak),
            'canVoid' => $this->authorizer->canVoidMushak($user, $mushak),
            'canPdf' => $this->authorizer->canDownloadMushak($user, $mushak),
            'vdsRecords' => $user->hasPermission('vds.view') ? VdsRecord::query()->where('mushak_63_document_id', $mushak->getKey())->orderByDesc('id')->get() : collect(),
            'aitRecords' => $user->hasPermission('ait.view') ? AitRecord::query()->where('mushak_63_document_id', $mushak->getKey())->orderByDesc('id')->get() : collect(),
            'rates' => $this->rateLabels($mushak),
        ]);
    }

    public function edit(Mushak63Document $mushak): View
    {
        $mushak = $this->visibleMushak($mushak);
        abort_unless($this->authorizer->canUpdateMushak($this->taxActor(), $mushak), 403);
        $invoice = $mushak->invoice;
        abort_unless($invoice instanceof CommercialInvoice, 404);

        return $this->form($invoice, $mushak);
    }

    public function update(SaveMushakRequest $request, Mushak63Document $mushak, MushakWriter $writer): RedirectResponse
    {
        $mushak = $this->visibleMushak($mushak);
        abort_unless($this->authorizer->canUpdateMushak($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $writer->updateDraft($mushak, $this->taxActor(), $request->validated()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Draft updated. VAT amounts were calculated again from the percentages you entered.');
    }

    public function issue(Mushak63Document $mushak, MushakWorkflow $workflow): RedirectResponse
    {
        $mushak = $this->visibleMushak($mushak);
        abort_unless($this->authorizer->canIssueMushak($this->taxActor(), $mushak), 403);
        $this->attemptTax(fn () => $workflow->issue($mushak, $this->taxActor()));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 issued. The commercial invoice was not changed. The percentage was not certified.');
    }

    public function void(VoidTaxRecordRequest $request, Mushak63Document $mushak, MushakWorkflow $workflow): RedirectResponse
    {
        $mushak = $this->visibleMushak($mushak);
        $this->attemptTax(fn () => $workflow->void($mushak, $this->taxActor(), (string) $request->validated('void_reason')));

        return redirect()->route('mushak.show', $mushak)->with('status', 'Mushak 6.3 voided. The amounts and the commercial invoice were kept.');
    }

    public function pdf(Mushak63Document $mushak, MushakPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $mushak = $this->visibleMushak($mushak);
        abort_unless($this->authorizer->canDownloadMushak($this->taxActor(), $mushak), 403);
        $audit->record('mushak_63_pdf_downloaded', $mushak, newValues: [
            'number' => $mushak->displayNumber(),
            'status' => $mushak->stringAttribute('status'),
        ], actor: $this->taxActor());

        return $renderer->download($mushak);
    }

    private function form(CommercialInvoice $invoice, ?Mushak63Document $document): View
    {
        $invoice->loadMissing('lines');
        $saved = [];

        if ($document instanceof Mushak63Document) {
            $document->loadMissing('lines');

            foreach ($document->lines as $line) {
                $id = $line->getAttribute('commercial_invoice_line_id');

                if (is_numeric($id)) {
                    $saved[(int) $id] = $line;
                }
            }
        }

        $oldLines = [];
        $posted = old('lines');

        if (is_array($posted)) {
            foreach ($posted as $line) {
                if (is_array($line) && isset($line['commercial_invoice_line_id'])) {
                    $oldLines[(int) $line['commercial_invoice_line_id']] = $line;
                }
            }
        }

        $rows = [];

        foreach ($invoice->lines as $line) {
            $rows[] = $this->row($line, $saved[(int) $line->getKey()] ?? null, $oldLines[(int) $line->getKey()] ?? null);
        }

        return view('tax.mushak.form', [
            'invoice' => $invoice,
            'document' => $document,
            'rows' => $rows,
            'customers' => Party::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->where('role', 'customer'))
                ->orderBy('legal_name')
                ->get(),
            'ruleVersions' => TaxRuleVersion::query()
                ->with('rule')
                ->where('approval_status', 'approved')
                ->whereHas('rule', fn ($query) => $query->where('tax_type', TaxType::OUTPUT_VAT)->where('status', 'active'))
                ->orderBy('tax_rule_id')
                ->orderBy('version_number')
                ->get(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @return array<string, string>
     */
    private function row(CommercialInvoiceLine $line, ?Mushak63Line $saved, ?array $old): array
    {
        $value = function (string $key, string $fallback) use ($old): string {
            if (is_array($old) && array_key_exists($key, $old)) {
                return trim((string) $old[$key]);
            }

            return $fallback;
        };

        return [
            'commercial_invoice_line_id' => (string) $line->getKey(),
            'description' => $value('description', $saved?->stringAttribute('description') ?: $line->stringAttribute('description')),
            'unit' => $value('unit', $saved?->stringAttribute('unit') ?: $line->stringAttribute('unit')),
            'quantity' => $value('quantity', $saved?->quantityLabel() ?: $line->quantityLabel()),
            'unit_price' => $value('unit_price', $saved?->decimalAttribute('unit_price') ?: $line->decimalAttribute('unit_price')),
            'base_amount' => $value('base_amount', $saved?->decimalAttribute('base_amount') ?: $line->decimalAttribute('line_total')),
            'vat_rate' => $value('vat_rate', $saved instanceof Mushak63Line ? app(VatPriceCalculator::class)->displayRate($saved->nullableDecimal('vat_rate_percent')) : ''),
            'vat_amount' => $value('vat_amount', $saved?->nullableDecimal('vat_amount') ?? ''),
            'inclusive_amount' => $value('inclusive_amount', $saved?->nullableDecimal('inclusive_amount') ?? ''),
            'supplementary_duty_amount' => $value('supplementary_duty_amount', $saved?->nullableDecimal('supplementary_duty_amount') ?? ''),
            'driver' => $value('driver', $saved?->stringAttribute('calculation_driver') ?: 'base'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function rateLabels(Mushak63Document $document): array
    {
        $calculator = app(VatPriceCalculator::class);
        $labels = [];

        foreach ($document->lines as $line) {
            $labels[(int) $line->getKey()] = $calculator->displayRate($line->nullableDecimal('vat_rate_percent'));
        }

        return $labels;
    }

    private function selectedInvoice(Request $request, DocumentAuthorizer $invoices): ?CommercialInvoice
    {
        $id = $request->integer('invoice');

        if ($id < 1) {
            return null;
        }

        $invoice = CommercialInvoice::query()->find($id);

        if (! $invoice instanceof CommercialInvoice || ! $invoices->canViewInvoice($this->taxActor(), $invoice) || $invoice->stringAttribute('status') !== DocumentStatus::ISSUED) {
            return null;
        }

        return $invoice;
    }

    /**
     * @return Collection<int, CommercialInvoice>
     */
    private function issuedInvoices(DocumentAuthorizer $invoices): Collection
    {
        return CommercialInvoice::query()
            ->with('quotation')
            ->where('status', DocumentStatus::ISSUED)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->filter(fn (CommercialInvoice $invoice): bool => $invoices->canViewInvoice($this->taxActor(), $invoice))
            ->values();
    }

    private function visibleInvoice(int $id, DocumentAuthorizer $invoices): CommercialInvoice
    {
        $invoice = CommercialInvoice::query()->find($id);
        abort_unless($invoice instanceof CommercialInvoice && $invoices->canViewInvoice($this->taxActor(), $invoice), 404);

        return $invoice;
    }

    private function visibleMushak(Mushak63Document $document): Mushak63Document
    {
        abort_unless($this->authorizer->canViewMushak($this->taxActor(), $document), 404);

        return $document;
    }
}
