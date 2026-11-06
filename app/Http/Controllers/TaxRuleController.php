<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveTaxRuleRequest;
use App\Models\TaxRule;
use App\Models\TaxRuleVersion;
use App\Tax\TaxRuleException;
use App\Tax\TaxRuleMatcher;
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
        $type = is_string($request->query('tax_type')) ? $request->query('tax_type') : TaxType::OUTPUT_VAT;
        $on = is_string($request->query('on')) ? $request->query('on') : now()->timezone('Asia/Dhaka')->toDateString();
        $explanation = null;
        $explanationError = null;

        if ($request->query->has('on')) {
            try {
                $explanation = $matcher->explain($type, $on);
            } catch (TaxRuleException $exception) {
                $explanationError = $exception->getMessage();
            }
        }

        return view('tax.rules.index', [
            'rules' => TaxRule::query()->with('versions')->orderBy('code')->paginate(20)->withQueryString(),
            'taxTypes' => TaxType::all(),
            'type' => $type,
            'on' => $on,
            'explanation' => $explanation,
            'explanationError' => $explanationError,
            'canCreate' => $this->taxActor()->hasPermission('tax_rules.create'),
        ]);
    }

    public function create(): View
    {
        return view('tax.rules.form', [
            'rule' => null,
            'taxTypes' => TaxType::all(),
        ]);
    }

    public function store(SaveTaxRuleRequest $request, TaxRuleWriter $writer): RedirectResponse
    {
        $rule = $this->attemptTax(fn (): TaxRule => $writer->create($this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'Draft tax rule saved. It does not apply a percentage until a different person approves a version, and approval still does not copy a rate onto a document.');
    }

    public function show(TaxRule $rule): View
    {
        $rule->load(['versions.createdBy', 'versions.approvedBy']);
        $user = $this->taxActor();

        return view('tax.rules.show', [
            'rule' => $rule,
            'canUpdate' => $user->hasPermission('tax_rules.update') && $rule->stringAttribute('status') === 'active',
            'canApprove' => $user->hasPermission('tax_rules.approve'),
            'canRetire' => $user->hasPermission('tax_rules.retire'),
        ]);
    }

    public function storeVersion(SaveTaxRuleRequest $request, TaxRule $rule, TaxRuleWriter $writer): RedirectResponse
    {
        $this->attemptTax(fn () => $writer->addVersion($rule, $this->taxActor(), $request->validated()));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'New draft version saved. The previous version was not changed.');
    }

    public function approve(TaxRule $rule, TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless((int) $version->tax_rule_id === (int) $rule->getKey(), 404);
        $this->attemptTax(fn () => $writer->approve($version, $this->taxActor()));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'Version approved. Its percentage is not applied to Mushak 6.3.');
    }

    public function retire(TaxRule $rule, TaxRuleWriter $writer): RedirectResponse
    {
        $this->attemptTax(fn () => $writer->retireRule($rule, $this->taxActor()));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'Rule retired. Existing versions were not rewritten.');
    }

    public function retireVersion(TaxRule $rule, TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless((int) $version->tax_rule_id === (int) $rule->getKey(), 404);
        $this->attemptTax(fn () => $writer->retire($version, $this->taxActor()));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'Version retired. Documents that already snapshotted it are unchanged.');
    }

    public function close(Request $request, TaxRule $rule, TaxRuleVersion $version, TaxRuleWriter $writer): RedirectResponse
    {
        abort_unless((int) $version->tax_rule_id === (int) $rule->getKey(), 404);
        abort_unless($this->taxActor()->hasPermission('tax_rules.update'), 403);
        $end = $request->string('effective_to')->toString();
        $this->attemptTax(fn () => $writer->close($version, $this->taxActor(), $end));

        return redirect()->route('tax-rules.show', $rule)->with('status', 'Window closed. The end date is inclusive.');
    }
}
