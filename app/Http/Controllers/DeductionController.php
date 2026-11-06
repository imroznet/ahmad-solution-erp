<?php

namespace App\Http\Controllers;

use App\Models\AitRecord;
use App\Models\User;
use App\Models\VdsRecord;
use App\Services\AuditLogger;
use App\Tax\DeductionWriter;
use App\Tax\TaxPdfRenderer;
use App\Tax\TaxRuleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DeductionController extends Controller
{
    public function __construct(
        private readonly DeductionWriter $writer,
        private readonly TaxPdfRenderer $pdfs,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, string $kind): View
    {
        $this->guard($request, $kind, 'view');

        return view('tax.deductions.index', [
            'kind' => $kind,
            'title' => $this->title($kind),
            'records' => $this->query($kind)->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request, string $kind): View
    {
        $this->guard($request, $kind, 'create');

        return view('tax.deductions.form', [
            'kind' => $kind,
            'title' => $this->title($kind),
            'record' => null,
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $this->guard($request, $kind, 'create');
        $record = $this->attempt(fn (): Model => $this->writer->create($kind, $this->actor($request), $request->all()));

        return redirect()->route('deductions.show', ['kind' => $kind, 'record' => $record->getKey()])
            ->with('status', $this->title($kind).' draft saved. The amount was stored as entered and was not calculated.');
    }

    public function show(Request $request, string $kind, int $record): View
    {
        $this->guard($request, $kind, 'view');
        $model = $this->find($kind, $record);
        $user = $this->actor($request);

        return view('tax.deductions.show', [
            'kind' => $kind,
            'title' => $this->title($kind),
            'record' => $model,
            'canUpdate' => $model->getAttribute('status') === 'draft' && $user->hasPermission($kind.'.update'),
            'canRecord' => $model->getAttribute('status') === 'draft' && $user->hasPermission($kind.'.update'),
            'canVoid' => $model->getAttribute('status') !== 'void' && $user->hasPermission($kind.'.void'),
        ]);
    }

    public function edit(Request $request, string $kind, int $record): View
    {
        $this->guard($request, $kind, 'update');
        $model = $this->find($kind, $record);
        abort_unless($model->getAttribute('status') === 'draft', 404);

        return view('tax.deductions.form', [
            'kind' => $kind,
            'title' => $this->title($kind),
            'record' => $model,
        ]);
    }

    public function update(Request $request, string $kind, int $record): RedirectResponse
    {
        $this->guard($request, $kind, 'update');
        $model = $this->find($kind, $record);
        $this->attempt(fn (): Model => $this->writer->update($kind, $this->actor($request), $model, $request->all()));

        return redirect()->route('deductions.show', ['kind' => $kind, 'record' => $model->getKey()])
            ->with('status', 'Draft updated. No rate was applied.');
    }

    public function record(Request $request, string $kind, int $record): RedirectResponse
    {
        $this->guard($request, $kind, 'update');
        $model = $this->find($kind, $record);
        $this->attempt(fn (): Model => $this->writer->record($kind, $this->actor($request), $model));

        return redirect()->route('deductions.show', ['kind' => $kind, 'record' => $model->getKey()])
            ->with('status', 'Recorded. This is not an NBR certificate.');
    }

    public function void(Request $request, string $kind, int $record): RedirectResponse
    {
        $this->guard($request, $kind, 'void');
        $model = $this->find($kind, $record);
        $this->attempt(fn (): Model => $this->writer->void($kind, $this->actor($request), $model, $request->string('void_reason')->toString()));

        return redirect()->route('deductions.show', ['kind' => $kind, 'record' => $model->getKey()])
            ->with('status', 'Record voided. It was kept.');
    }

    public function pdf(Request $request, string $kind, int $record): Response
    {
        $this->guard($request, $kind, 'view');
        $model = $this->find($kind, $record);
        $this->audit->record($kind.'_pdf_downloaded', $model, newValues: ['id' => $model->getKey()], actor: $this->actor($request));

        return $this->pdfs->deduction($kind, $model);
    }

    private function query(string $kind): Builder
    {
        $class = $kind === 'ait' ? AitRecord::class : VdsRecord::class;

        return $class::query();
    }

    private function find(string $kind, int $id): Model
    {
        abort_unless(in_array($kind, ['vds', 'ait'], true), 404);
        $class = $kind === 'ait' ? AitRecord::class : VdsRecord::class;
        $record = $class::query()->find($id);
        abort_unless($record instanceof Model, 404);

        return $record;
    }

    private function guard(Request $request, string $kind, string $ability): void
    {
        abort_unless(in_array($kind, ['vds', 'ait'], true), 404);
        abort_unless($this->actor($request)->hasPermission($kind.'.'.$ability), 403);
    }

    private function title(string $kind): string
    {
        return $kind === 'ait' ? 'AIT' : 'VDS';
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
