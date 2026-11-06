<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveWithholdingRequest;
use App\Http\Requests\VoidTaxRecordRequest;
use App\Models\AitRecord;
use App\Models\Party;
use App\Tax\TaxAuthorizer;
use App\Tax\TaxType;
use App\Tax\WithholdingDirection;
use App\Tax\WithholdingWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AitRecordController extends Controller
{
    use AttemptsTax;

    public function __construct(private readonly TaxAuthorizer $authorizer) {}

    public function index(): View
    {
        $user = $this->taxActor();
        $records = AitRecord::query()->latest('id')->paginate(20);
        $records->setCollection($records->getCollection()->filter(fn (AitRecord $record): bool => $this->authorizer->canViewAit($user, $record))->values());

        return view('tax.withholding.index', [
            'title' => 'AIT records',
            'intro' => 'Income-tax withholding. These amounts are not VAT and are not added to a Mushak 6.3 VAT total. No withholding rate is applied automatically.',
            'records' => $records,
            'createRoute' => 'ait.create',
            'showRoute' => 'ait.show',
            'canCreate' => $user->hasPermission('ait.create'),
        ]);
    }

    public function create(): View
    {
        return view('tax.withholding.form', [
            'title' => 'Record AIT',
            'kind' => TaxType::AIT,
            'action' => route('ait.store'),
            'directions' => WithholdingDirection::all(),
            'parties' => Party::query()->where('status', 'active')->orderBy('legal_name')->limit(200)->get(),
        ]);
    }

    public function store(SaveWithholdingRequest $request, WithholdingWriter $writer): RedirectResponse
    {
        $record = $this->attemptTax(fn (): AitRecord => $writer->createAit($this->taxActor(), $request->validated()));

        return redirect()->route('ait.show', $record)->with('status', 'AIT recorded. The amount was stored as entered and was not added to VAT.');
    }

    public function show(AitRecord $ait): View
    {
        abort_unless($this->authorizer->canViewAit($this->taxActor(), $ait), 404);

        return view('tax.withholding.show', [
            'title' => 'AIT record',
            'record' => $ait,
            'kindLabel' => 'AIT',
            'voidRoute' => 'ait.void',
            'canVoid' => $this->taxActor()->hasPermission('ait.void') && $ait->stringAttribute('status') === 'recorded',
            'separation' => 'This income-tax amount is not output VAT and is not included in a Mushak 6.3 VAT total.',
        ]);
    }

    public function void(VoidTaxRecordRequest $request, AitRecord $ait, WithholdingWriter $writer): RedirectResponse
    {
        abort_unless($this->authorizer->canViewAit($this->taxActor(), $ait), 404);
        $this->attemptTax(fn () => $writer->voidAit($ait, $this->taxActor(), (string) $request->validated('void_reason')));

        return redirect()->route('ait.show', $ait)->with('status', 'AIT record voided. The amount was kept.');
    }
}
