<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveWithholdingRequest;
use App\Models\AitRecord;
use App\Models\VdsRecord;
use App\Tax\WithholdingDirection;
use App\Tax\WithholdingWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WithholdingController extends Controller
{
    use AttemptsTax;

    public function index(): View
    {
        $kind = $this->kind();
        abort_unless($this->taxActor()->hasPermission($kind.'.view'), 403);
        $query = $kind === 'ait' ? AitRecord::query() : VdsRecord::query();

        return view('withholding.index', [
            'kind' => $kind,
            'records' => $query->orderByDesc('id')->paginate(15),
            'canRecord' => $this->taxActor()->hasPermission($kind.'.record'),
        ]);
    }

    public function create(): View
    {
        $kind = $this->kind();
        abort_unless($this->taxActor()->hasPermission($kind.'.record'), 403);

        return view('withholding.form', [
            'kind' => $kind,
            'record' => null,
            'directions' => WithholdingDirection::all(),
        ]);
    }

    public function store(SaveWithholdingRequest $request, WithholdingWriter $writer): RedirectResponse
    {
        $kind = $this->kind();
        $record = $kind === 'ait'
            ? $this->attemptTax(fn (): AitRecord => $writer->createAit($this->taxActor(), $request->validated()))
            : $this->attemptTax(fn (): VdsRecord => $writer->createVds($this->taxActor(), $request->validated()));

        return redirect()->route($kind.'.show', $record)->with('status', strtoupper($kind).' recorded. It was not added to output VAT.');
    }

    public function show(int $record): View
    {
        $kind = $this->kind();
        abort_unless($this->taxActor()->hasPermission($kind.'.view'), 403);
        $model = $this->find($kind, $record);

        return view('withholding.show', [
            'kind' => $kind,
            'record' => $model,
            'canRecord' => $this->taxActor()->hasPermission($kind.'.record'),
        ]);
    }

    public function edit(int $record): View
    {
        $kind = $this->kind();
        abort_unless($this->taxActor()->hasPermission($kind.'.record'), 403);

        return view('withholding.form', [
            'kind' => $kind,
            'record' => $this->find($kind, $record),
            'directions' => WithholdingDirection::all(),
        ]);
    }

    public function update(SaveWithholdingRequest $request, WithholdingWriter $writer, int $record): RedirectResponse
    {
        $kind = $this->kind();
        $model = $this->find($kind, $record);

        if ($model instanceof AitRecord) {
            $saved = $this->attemptTax(fn (): AitRecord => $writer->updateAit($this->taxActor(), $model, $request->validated()));
        } elseif ($model instanceof VdsRecord) {
            $saved = $this->attemptTax(fn (): VdsRecord => $writer->updateVds($this->taxActor(), $model, $request->validated()));
        } else {
            abort(404);
        }

        return redirect()->route($kind.'.show', $saved)->with('status', strtoupper($kind).' record updated. Output VAT was not changed.');
    }

    private function kind(): string
    {
        return request()->routeIs('ait.*') ? 'ait' : 'vds';
    }

    private function find(string $kind, int $id): Model
    {
        $model = $kind === 'ait' ? AitRecord::query()->find($id) : VdsRecord::query()->find($id);
        abort_unless($model instanceof Model, 404);

        return $model;
    }
}
