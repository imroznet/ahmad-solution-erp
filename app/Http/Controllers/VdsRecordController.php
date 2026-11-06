<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveWithholdingRequest;
use App\Http\Requests\VoidTaxRecordRequest;
use App\Models\Party;
use App\Models\VdsRecord;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxType;
use App\Tax\WithholdingDirection;
use App\Tax\WithholdingWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VdsRecordController extends Controller
{
    use AttemptsTax;

    public function __construct(private readonly TaxAuthorizer $authorizer) {}

    public function index(): View
    {
        $user = $this->taxActor();
        $records = VdsRecord::query()->latest('id')->paginate(20);
        $records->setCollection($records->getCollection()->filter(fn (VdsRecord $record): bool => $this->authorizer->canViewVds($user, $record))->values());

        return view('tax.withholding.index', [
            'title' => 'VDS records',
            'intro' => 'VAT deducted or collected at source. These amounts are not output VAT and are not added to a Mushak 6.3 VAT total. Mushak 6.6 is not generated.',
            'records' => $records,
            'createRoute' => 'vds.create',
            'showRoute' => 'vds.show',
            'canCreate' => $user->hasPermission('vds.create'),
        ]);
    }

    public function create(): View
    {
        return view('tax.withholding.form', [
            'title' => 'Record VDS',
            'kind' => TaxType::VDS,
            'action' => route('vds.store'),
            'directions' => WithholdingDirection::all(),
            'parties' => Party::query()->where('status', 'active')->orderBy('legal_name')->limit(200)->get(),
        ]);
    }

    public function store(SaveWithholdingRequest $request, WithholdingWriter $writer): RedirectResponse
    {
        $record = $this->attemptTax(fn (): VdsRecord => $writer->createVds($this->taxActor(), $request->validated()));

        return redirect()->route('vds.show', $record)->with('status', 'VDS recorded. The amount was stored as entered and was not added to output VAT.');
    }

    public function show(VdsRecord $vds): View
    {
        abort_unless($this->authorizer->canViewVds($this->taxActor(), $vds), 404);

        return view('tax.withholding.show', [
            'title' => 'VDS record',
            'record' => $vds,
            'kindLabel' => 'VDS',
            'voidRoute' => 'vds.void',
            'canVoid' => $this->taxActor()->hasPermission('vds.void') && $vds->stringAttribute('status') === 'recorded',
            'separation' => 'This VDS amount is not output VAT and is not included in a Mushak 6.3 VAT total.',
        ]);
    }

    public function void(VoidTaxRecordRequest $request, VdsRecord $vds, WithholdingWriter $writer): RedirectResponse
    {
        abort_unless($this->authorizer->canViewVds($this->taxActor(), $vds), 404);
        $this->attemptTax(fn () => $writer->voidVds($vds, $this->taxActor(), (string) $request->validated('void_reason')));

        return redirect()->route('vds.show', $vds)->with('status', 'VDS record voided. The amount was kept.');
    }
}
