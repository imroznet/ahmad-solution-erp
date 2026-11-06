<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\RetireTaxRuleRequest;
use App\Http\Requests\SaveTaxRuleRequest;
use App\Models\TaxRuleVersion;
use App\Tax\TaxRuleMatcher;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxRuleWriter;
use App\Tax\TaxType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRuleController extends Controller
{
    use AttemptsTax;

    public function index(Request $request, TaxRuleMatcher $matcher): View
    {
        $user = $this->taxActor();
        abort_unless($user->hasPermission('tax_rules.view'), 403);
        $type = $request->string('tax_type')->toString();
        $query = TaxRuleVersion::query()->with('rule')->orderByDesc('id');

        if (in_array($type, TaxType::all(), true)) {
            $query->whereHas('rule', fn ($inner) => $inner->where('tax_type', $type));
        }

        $preview = null;
        $previewDate = $request->string('on')->toString();

        if ($previewDate !== '' && in_array($type, TaxType::all(), true)) {
            $preview = $matcher->explain($type, $request->string('classification')->toString(), $previewDate);
        }

        return view('tax-rules.index', [
            'versions' => $query->paginate(15)->withQueryString(),
            'types' => TaxType::all(),
            'filterType' => $type,
            'preview' => $preview,
            'previewDate' => $previewDate,
            'classification' => $request->string('classification')->toString(),
            'canCreate' => $user->hasPermission('tax_rules.create'),
        ]);
    }

    public function create(): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.create'), 403);

        return view('tax-rules.form', [
            'version' => null,
            'types' => TaxType::all(),
            'amending' => false,
        ]);
    }

    public function store(SaveTaxRuleRequest $request, TaxRuleWriter $writer): RedirectResponse
    {
        $version = $this->attemptTax(fn (): TaxRuleVersion => $writer->create($this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $version)->with('status', 'Draft tax rule saved. It does not apply a percentage to any document.');
    }

    public function show(TaxRuleVersion $version): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.view'), 403);
        $version->load('rule', 'createdBy', 'approvedBy', 'retiredBy');
        $user = $this->taxActor();

        return view('tax-rules.show', [
            'version' => $version,
            'canUpdate' => $version->stringAttribute('status') === TaxRuleStatus::DRAFT && $user->hasPermission('tax_rules.update'),
            'canApprove' => $version->stringAttribute('status') === TaxRuleStatus::DRAFT && $user->hasPermission('tax_rules.approve') && (int) $version->created_by !== (int) $user->getKey(),
            'canRetire' => $version->stringAttribute('status') === TaxRuleStatus::APPROVED && $user->hasPermission('tax_rules.retire'),
            'canAmend' => $version->stringAttribute('status') !== TaxRuleStatus::DRAFT && $user->hasPermission('tax_rules.create'),
        ]);
    }

    public function edit(TaxRuleVersion $version): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.update'), 403);
        abort_unless($version->stringAttribute('status') === TaxRuleStatus::DRAFT, 403);
        $version->load('rule');

        return view('tax-rules.form', [
            'version' => $version,
            'types' => TaxType::all(),
            'amending' => false,
        ]);
    }

    public function update(SaveTaxRuleRequest $request, TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.update'), 403);
        $this->attemptTax(fn () => $writer->update($this->taxActor(), $version, $request->validated()));

        return redirect()->route('tax-rules.show', $version)->with('status', 'Draft tax rule updated.');
    }

    public function amend(TaxRuleVersion $version): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.create'), 403);
        abort_if($version->stringAttribute('status') === TaxRuleStatus::DRAFT, 403);
        $version->load('rule');

        return view('tax-rules.form', [
            'version' => $version,
            'types' => TaxType::all(),
            'amending' => true,
        ]);
    }

    public function storeAmendment(SaveTaxRuleRequest $request, TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.create'), 403);
        $created = $this->attemptTax(fn (): TaxRuleVersion => $writer->amend($this->taxActor(), $version, $request->validated()));

        return redirect()->route('tax-rules.show', $created)->with('status', 'Amendment saved as a new draft version. The previous version was not rewritten.');
    }

    public function approve(TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.approve'), 403);
        $this->attemptTax(fn () => $writer->approve($this->taxActor(), $version));

        return redirect()->route('tax-rules.show', $version)->with('status', 'Tax rule version approved. Its percentage is still not applied to documents.');
    }

    public function retire(RetireTaxRuleRequest $request, TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        $this->attemptTax(fn () => $writer->retire($this->taxActor(), $version, $request->validated()));

        return redirect()->route('tax-rules.show', $version)->with('status', 'Tax rule version retired. Issued documents that cited it are unchanged.');
    }
}
