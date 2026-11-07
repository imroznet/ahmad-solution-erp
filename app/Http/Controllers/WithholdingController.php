<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveWithholdingRequest;
use App\Http\Requests\VoidTaxDocumentRequest;
use App\Models\AitRecord;
use App\Models\AitRecordEvent;
use App\Models\Party;
use App\Models\TaxRuleVersion;
use App\Models\VdsRecord;
use App\Models\VdsRecordEvent;
use App\Models\WithholdingRecord;
use App\Services\AuditLogger;
use App\Tax\IssuanceGate;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxDocumentStatus;
use App\Tax\TaxPdfRenderer;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxType;
use App\Tax\WithholdingWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WithholdingController extends Controller
{
    use AttemptsTax;

    public function index(TaxAuthorizer $authorizer): View
    {
        $taxType = $this->taxType();
        $user = $this->taxActor();
        $records = $this->query($taxType)->with('party')->orderByDesc('id')->limit(100)->get()
            ->filter(fn (WithholdingRecord $record): bool => $authorizer->canViewWithholding($user, $record))
            ->values();

        return view('withholding.index', [
            'taxType' => $taxType,
            'records' => $records,
            'blockers' => IssuanceGate::blockers(),
        ]);
    }

    public function create(): View
    {
        $taxType = $this->taxType();

        return view('withholding.form', [
            'taxType' => $taxType,
            'record' => null,
            'parties' => Party::query()->where('status', 'active')->orderBy('legal_name')->get(),
            'rules' => $this->rules($taxType),
        ]);
    }

    public function store(SaveWithholdingRequest $request, WithholdingWriter $writer): RedirectResponse
    {
        $taxType = $this->taxType();
        abort_unless($this->taxActor()->hasPermission($taxType === TaxType::VDS ? 'vds.manage' : 'ait.manage'), 403);
        $record = $this->attemptTax(fn () => $writer->create($taxType, $this->taxActor(), $request->validated()));

        return redirect()->route($this->routeName($taxType, 'show'), $record)->with('status', TaxType::label($taxType).' record saved. It is not a certificate and it is not output VAT.');
    }

    public function show(TaxAuthorizer $authorizer): View
    {
        $taxType = $this->taxType();
        $model = $this->find($taxType, $this->recordId());
        abort_unless($authorizer->canViewWithholding($this->taxActor(), $model), 404);
        $user = $this->taxActor();
        $manage = $taxType === TaxType::VDS ? 'vds.manage' : 'ait.manage';

        return view('withholding.show', [
            'taxType' => $taxType,
            'record' => $model,
            'blockers' => IssuanceGate::blockers(),
            'canUpdate' => $user->hasPermission($manage) && $model->stringAttribute('status') === TaxDocumentStatus::DRAFT,
            'canPdf' => $authorizer->canDownloadWithholding($user, $model),
        ]);
    }

    public function edit(TaxAuthorizer $authorizer): View
    {
        $taxType = $this->taxType();
        $model = $this->find($taxType, $this->recordId());
        $manage = $taxType === TaxType::VDS ? 'vds.manage' : 'ait.manage';
        abort_unless($this->taxActor()->hasPermission($manage) && $authorizer->canViewWithholding($this->taxActor(), $model), 403);
        abort_unless($model->stringAttribute('status') === TaxDocumentStatus::DRAFT, 403);

        return view('withholding.form', [
            'taxType' => $taxType,
            'record' => $model,
            'parties' => Party::query()->where('status', 'active')->orderBy('legal_name')->get(),
            'rules' => $this->rules($taxType),
        ]);
    }

    public function update(SaveWithholdingRequest $request, WithholdingWriter $writer, TaxAuthorizer $authorizer): RedirectResponse
    {
        $taxType = $this->taxType();
        $model = $this->find($taxType, $this->recordId());
        abort_unless($authorizer->canViewWithholding($this->taxActor(), $model), 404);
        abort_unless($this->taxActor()->hasPermission($taxType === TaxType::VDS ? 'vds.manage' : 'ait.manage'), 403);
        $this->attemptTax(fn () => $writer->update($model, $this->taxActor(), $request->validated()));

        return redirect()->route($this->routeName($taxType, 'show'), $model)->with('status', 'Record updated.');
    }

    public function void(VoidTaxDocumentRequest $request, WithholdingWriter $writer, TaxAuthorizer $authorizer): RedirectResponse
    {
        $taxType = $this->taxType();
        $model = $this->find($taxType, $this->recordId());
        abort_unless($authorizer->canViewWithholding($this->taxActor(), $model), 404);
        abort_unless($this->taxActor()->hasPermission($taxType === TaxType::VDS ? 'vds.manage' : 'ait.manage'), 403);
        $this->attemptTax(fn () => $writer->void($model, $this->taxActor(), (string) $request->validated('reason')));

        return redirect()->route($this->routeName($taxType, 'show'), $model)->with('status', 'Record voided. No certificate was issued.');
    }

    public function pdf(TaxAuthorizer $authorizer, TaxPdfRenderer $renderer, AuditLogger $audit): Response
    {
        $taxType = $this->taxType();
        $model = $this->find($taxType, $this->recordId());
        abort_unless($authorizer->canDownloadWithholding($this->taxActor(), $model), 404);
        $payload = [
            'event' => 'pdf_downloaded',
            'actor_id' => $this->taxActor()->getKey(),
            'remarks' => 'Working paper downloaded.',
            'created_at' => now(),
        ];

        if ($model instanceof VdsRecord) {
            VdsRecordEvent::query()->create(['vds_record_id' => $model->getKey(), ...$payload]);
        } else {
            AitRecordEvent::query()->create(['ait_record_id' => $model->getKey(), ...$payload]);
        }

        $audit->record($taxType.'_pdf_downloaded', $model, newValues: [
            'status' => $model->stringAttribute('status'),
        ], actor: $this->taxActor());

        return $renderer->downloadWithholding($model);
    }

    private function find(string $taxType, int $id): WithholdingRecord
    {
        $record = $this->query($taxType)->find($id);
        abort_unless($record instanceof WithholdingRecord, 404);

        return $record;
    }

    /**
     * @return Builder<VdsRecord>|Builder<AitRecord>
     */
    private function query(string $taxType): Builder
    {
        abort_unless(in_array($taxType, [TaxType::VDS, TaxType::AIT], true), 404);

        return $taxType === TaxType::VDS ? VdsRecord::query() : AitRecord::query();
    }

    /**
     * @return Collection<int, TaxRuleVersion>
     */
    private function rules(string $taxType): Collection
    {
        return TaxRuleVersion::query()
            ->where('tax_type', $taxType)
            ->where('approval_status', TaxRuleStatus::APPROVED)
            ->orderBy('supply_classification')
            ->get();
    }

    private function routeName(string $taxType, string $action): string
    {
        return ($taxType === TaxType::VDS ? 'vds.' : 'ait.').$action;
    }

    private function taxType(): string
    {
        $name = request()->route()?->getName();

        return is_string($name) && str_starts_with($name, 'ait.') ? TaxType::AIT : TaxType::VDS;
    }

    private function recordId(): int
    {
        $record = request()->route('record');

        return is_numeric($record) ? (int) $record : 0;
    }
}
