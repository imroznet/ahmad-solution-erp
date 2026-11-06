<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveTaxRuleRequest;
use App\Http\Requests\VoidTaxDocumentRequest;
use App\Models\TaxRuleVersion;
use App\Tax\IssuanceGate;
use App\Tax\TaxCalculator;
use App\Tax\TaxRuleSelector;
use App\Tax\TaxRuleStatus;
use App\Tax\TaxRuleWriter;
use App\Tax\TaxText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRuleController extends Controller
{
    use AttemptsTax;

    public function index(): View
    {
        return view('tax-rules.index', [
            'rules' => TaxRuleVersion::query()->orderByDesc('id')->limit(100)->get(),
            'blockers' => IssuanceGate::blockers(),
        ]);
    }

    public function create(): View
    {
        return view('tax-rules.form', ['rule' => null]);
    }

    public function store(SaveTaxRuleRequest $request, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        $rule = $this->attemptTax(fn () => $writer->create($this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'Draft tax rule saved. It does not apply until someone else approves it.');
    }

    public function show(TaxRuleVersion $taxRule, Request $request, TaxRuleSelector $selector, TaxCalculator $calculator): View
    {
        $taxRule->load(['events.actor', 'createdBy', 'approvedBy']);
        $preview = null;
        $onDate = $request->string('on_date')->toString();
        $base = $request->string('base')->toString();

        if ($onDate !== '') {
            $selection = $selector->select(
                $taxRule->stringAttribute('tax_type'),
                $taxRule->stringAttribute('supply_classification'),
                $taxRule->stringAttribute('rule_code'),
                $onDate,
            );
            $assessment = null;

            if ($selection->matched() && $selection->version instanceof TaxRuleVersion && $base !== '') {
                $assessment = $calculator->fromSnapshot(
                    $taxRule->stringAttribute('tax_type'),
                    $base,
                    $selection->version->calculationSnapshot(),
                );
            }

            $preview = ['selection' => $selection, 'assessment' => $assessment];
        }

        $user = $this->taxActor();

        return view('tax-rules.show', [
            'rule' => $taxRule,
            'preview' => $preview,
            'blockers' => IssuanceGate::blockers(),
            'canApprove' => $user->hasPermission('tax_rules.approve') && $taxRule->stringAttribute('approval_status') === TaxRuleStatus::DRAFT && (int) $taxRule->created_by !== (int) $user->getKey(),
            'canRetire' => $user->hasPermission('tax_rules.retire') && $taxRule->stringAttribute('approval_status') === TaxRuleStatus::APPROVED,
            'canAmend' => $user->hasPermission('tax_rules.manage') && $taxRule->stringAttribute('approval_status') === TaxRuleStatus::APPROVED,
            'canEdit' => $user->hasPermission('tax_rules.manage') && $taxRule->stringAttribute('approval_status') === TaxRuleStatus::DRAFT,
        ]);
    }

    public function edit(TaxRuleVersion $taxRule): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        abort_unless($taxRule->stringAttribute('approval_status') === TaxRuleStatus::DRAFT, 403);

        return view('tax-rules.form', ['rule' => $taxRule]);
    }

    public function update(SaveTaxRuleRequest $request, TaxRuleVersion $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        $this->attemptTax(fn () => $writer->updateDraft($taxRule, $this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $taxRule)->with('status', 'Draft tax rule updated.');
    }

    public function amendForm(TaxRuleVersion $taxRule): View
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        abort_unless($taxRule->stringAttribute('approval_status') === TaxRuleStatus::APPROVED, 403);

        return view('tax-rules.form', ['rule' => $taxRule, 'amending' => true]);
    }

    public function amend(SaveTaxRuleRequest $request, TaxRuleVersion $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.manage'), 403);
        $created = $this->attemptTax(fn () => $writer->amend($taxRule, $this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $created)->with('status', 'Draft amendment saved. The approved version is unchanged until this draft is approved.');
    }

    public function approve(TaxRuleVersion $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.approve'), 403);
        $this->attemptTax(fn () => $writer->approve($taxRule, $this->taxActor()));

        return redirect()->route('tax-rules.show', $taxRule)->with('status', 'Tax rule approved. It still does not issue a Mushak, VDS certificate, or AIT certificate.');
    }

    public function retire(VoidTaxDocumentRequest $request, TaxRuleVersion $taxRule, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless($this->taxActor()->hasPermission('tax_rules.retire'), 403);
        $this->attemptTax(fn () => $writer->retire($taxRule, $this->taxActor(), (string) $request->validated('reason')));

        return redirect()->route('tax-rules.show', $taxRule)->with('status', 'Tax rule retired. Pinned snapshots on existing papers are kept.');
    }

    public function preview(Request $request, TaxRuleSelector $selector): RedirectResponse
    {
        $date = $request->string('on_date')->toString();
        $classification = TaxText::classification($request->string('supply_classification')->toString());
        $type = $request->string('tax_type')->toString();
        $rule = TaxRuleVersion::query()->find($request->integer('tax_rule'));

        if (! $rule instanceof TaxRuleVersion) {
            $rule = TaxRuleVersion::query()
                ->where('tax_type', $type)
                ->where('supply_classification', $classification)
                ->orderByDesc('id')
                ->first();
        }

        abort_unless($rule instanceof TaxRuleVersion, 404);

        return redirect()->route('tax-rules.show', [
            'taxRule' => $rule,
            'on_date' => $date,
            'base' => $request->string('base')->toString(),
        ]);
    }
}
