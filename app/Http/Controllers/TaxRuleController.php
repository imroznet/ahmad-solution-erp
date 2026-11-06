<?php

namespace App\Http\Controllers;

use App\Models\TaxRule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tax\TaxRuleException;
use App\Tax\TaxRulePreview;
use App\Tax\TaxRuleWriter;
use App\Tax\TaxType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaxRuleController extends Controller
{
    public function __construct(
        private readonly TaxRuleWriter $writer,
        private readonly TaxRulePreview $preview,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();

        return view('tax.rules.index', [
            'rules' => TaxRule::query()
                ->when(in_array($type, TaxType::ALL, true), fn ($query) => $query->where('tax_type', $type))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'type' => $type,
        ]);
    }

    public function create(): View
    {
        return view('tax.rules.form', ['rule' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = $this->attempt(fn (): TaxRule => $this->writer->create($this->actor($request), $request->all()));
        $this->audit->record('tax_rule_created', $rule, newValues: ['rule_code' => $rule->stringAttribute('rule_code'), 'tax_type' => $rule->stringAttribute('tax_type')], actor: $this->actor($request));

        return redirect()->route('tax.rules.show', $rule)->with('status', 'Draft tax rule saved. It does not set a VAT percentage.');
    }

    public function show(Request $request, TaxRule $rule): View
    {
        $explanation = null;

        if ($request->filled('on_date')) {
            $explanation = $this->attempt(fn (): array => $this->preview->explain(
                $rule->stringAttribute('tax_type'),
                $request->string('classification')->toString(),
                $request->string('on_date')->toString(),
            ));
        }

        return view('tax.rules.show', [
            'rule' => $rule->load('predecessor'),
            'explanation' => $explanation,
        ]);
    }

    public function edit(TaxRule $rule): View
    {
        abort_unless($rule->stringAttribute('approval_status') === 'draft', 404);

        return view('tax.rules.form', ['rule' => $rule]);
    }

    public function update(Request $request, TaxRule $rule): RedirectResponse
    {
        $this->attempt(fn (): TaxRule => $this->writer->update($this->actor($request), $rule, $request->all()));
        $this->audit->record('tax_rule_updated', $rule, newValues: ['rule_code' => $rule->stringAttribute('rule_code')], actor: $this->actor($request));

        return redirect()->route('tax.rules.show', $rule)->with('status', 'Draft tax rule updated.');
    }

    public function approve(Request $request, TaxRule $rule): RedirectResponse
    {
        $this->attempt(fn (): TaxRule => $this->writer->approve($this->actor($request), $rule));
        $this->audit->record('tax_rule_approved', $rule, newValues: ['version' => $rule->fresh()?->getAttribute('version')], actor: $this->actor($request));

        return redirect()->route('tax.rules.show', $rule)->with('status', 'Tax rule approved. It still does not fill a Mushak percentage.');
    }

    public function retire(Request $request, TaxRule $rule): RedirectResponse
    {
        $this->attempt(fn (): TaxRule => $this->writer->retire($this->actor($request), $rule));
        $this->audit->record('tax_rule_retired', $rule, actor: $this->actor($request));

        return redirect()->route('tax.rules.show', $rule)->with('status', 'Tax rule retired. Issued documents keep their snapshots.');
    }

    public function amend(Request $request, TaxRule $rule): RedirectResponse
    {
        $amendment = $this->attempt(fn (): TaxRule => $this->writer->amend($this->actor($request), $rule, $request->all()));
        $this->audit->record('tax_rule_amended', $amendment, newValues: ['supersedes_rule_id' => $rule->id], actor: $this->actor($request));

        return redirect()->route('tax.rules.edit', $amendment)->with('status', 'Amendment saved as a new draft. The approved rule was not rewritten.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function attempt(callable $action): mixed
    {
        try {
            return $action();
        } catch (TaxRuleException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }
    }
}
