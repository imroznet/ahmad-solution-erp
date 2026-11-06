<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveTaxRuleRequest;
use App\Models\TaxRule;
use App\Tax\TaxRuleSelector;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxRuleWriter;
use App\Tax\TaxType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRuleController extends Controller
{
    use AttemptsTax;

    public function index(Request $request): View
    {
        $user = $this->taxActor();
        $rules = TaxRule::query()
            ->when($request->string('tax_type')->toString() !== '', fn ($query) => $query->where('tax_type', $request->string('tax_type')->toString()))
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('tax.rules.index', [
            'rules' => $rules,
            'filters' => [
                'tax_type' => $request->string('tax_type')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'canManage' => $user->hasPermission('tax_rules.manage'),
        ]);
    }

    public function create(): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);

        return view('tax.rules.form', ['rule' => null]);
    }

    public function store(SaveTaxRuleRequest $request, TaxRuleWriter $writer): RedirectResponse
    {
        $rule = $this->attemptTax(fn (): TaxRule => $writer->create($this->taxActor(), $request->validated()));

        return redirect()
            ->route('tax-rules.show', $rule)
            ->with('status', 'Draft tax rule saved. It is not applied to any document.');
    }

    public function show(TaxRule $taxRule, Request $request, TaxRuleSelector $selector): View
    {
        $taxRule->load(['events.actor', 'supersededRule']);
        $preview = null;
        $user = $this->taxActor();

        if ($request->filled('on')) {
            $preview = $this->attemptTax(fn (): array => $selector->explain(
                $request->string('tax_type')->toString() !== '' ? $request->string('tax_type')->toString() : $taxRule->stringAttribute('tax_type'),
                $request->string('on')->toString(),
                $request->string('rule_key')->toString() !== '' ? $request->string('rule_key')->toString() : $taxRule->stringAttribute('rule_key'),
                $request->string('supply_classification')->toString() !== '' ? $request->string('supply_classification')->toString() : null,
            ));
        }

        return view('tax.rules.show', [
            'rule' => $taxRule,
            'preview' => $preview,
            'canManage' => $user->hasPermission('tax_rules.manage'),
            'canApprove' => $user->hasPermission('tax_rules.approve'),
        ]);
    }

    public function edit(TaxRule $taxRule): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        abort_unless($taxRule->stringAttribute('status') === TaxRuleStatus::DRAFT, 403);

        return view('tax.rules.form', ['rule' => $taxRule]);
    }

    public function update(SaveTaxRuleRequest $request, TaxRule $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        $this->attemptTax(fn () => $writer->updateDraft($taxRule, $this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $taxRule)->with('status', 'Draft tax rule updated. It is still not applied to any document.');
    }

    public function approve(TaxRule $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.approve'), 403);
        $this->attemptTax(fn () => $writer->approve($taxRule, $this->taxActor()));

        return redirect()->route('tax-rules.show', $taxRule)->with('status', 'Tax rule approved. Mushak 6.3 still uses the VAT figures a person types.');
    }

    public function amend(Request $request, TaxRule $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        $draft = $this->attemptTax(fn (): TaxRule => $writer->amend($taxRule, $this->taxActor(), $request->all()));

        return redirect()->route('tax-rules.edit', $draft)->with('status', 'Draft amendment opened. The previous version was not rewritten.');
    }

    public function retire(Request $request, TaxRule $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.approve'), 403);
        $reason = $request->string('retire_reason')->toString();
        $this->attemptTax(fn () => $writer->retire($taxRule, $this->taxActor(), $reason));

        return redirect()->route('tax-rules.show', $taxRule)->with('status', 'Tax rule retired. Issued documents that named it keep their snapshot.');
    }

    public function preview(Request $request, TaxRuleSelector $selector): View
    {
        $preview = null;

        if ($request->filled('on') && $request->filled('tax_type')) {
            $preview = $this->attemptTax(fn (): array => $selector->explain(
                $request->string('tax_type')->toString(),
                $request->string('on')->toString(),
                $request->string('rule_key')->toString() !== '' ? $request->string('rule_key')->toString() : null,
                $request->string('supply_classification')->toString() !== '' ? $request->string('supply_classification')->toString() : null,
            ));
        }

        return view('tax.rules.preview', [
            'preview' => $preview,
            'types' => TaxType::options(),
            'filters' => [
                'tax_type' => $request->string('tax_type')->toString(),
                'on' => $request->string('on')->toString(),
                'rule_key' => $request->string('rule_key')->toString(),
                'supply_classification' => $request->string('supply_classification')->toString(),
            ],
        ]);
    }
}
