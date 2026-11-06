<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttemptsTax;
use App\Http\Requests\SaveWithholdingRecordRequest;
use App\Models\AitRecord;
use App\Models\Party;
use App\Models\VdsRecord;
use App\Tax\WithholdingWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithholdingRecordController extends Controller
{
    use AttemptsTax;

    public function index(Request $request): View
    {
        $kind = $this->kind();
        $model = $this->model($kind);
        $records = $model->newQuery()
            ->when($request->string('direction')->toString() !== '', fn ($query) => $query->where('direction', $request->string('direction')->toString()))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('tax.withholding.index', [
            'kind' => $kind,
            'records' => $records,
            'filters' => ['direction' => $request->string('direction')->toString()],
            'canManage' => $this->taxActor()->hasPermission($kind.'.manage'),
        ]);
    }

    public function create(): View
    {
        abort_unless($this->taxActor()->hasPermission($this->kind().'.manage'), 403);

        return view('tax.withholding.form', [
            'kind' => $this->kind(),
            'customers' => $this->parties('customer'),
            'suppliers' => $this->parties('supplier'),
        ]);
    }

    public function store(SaveWithholdingRecordRequest $request, WithholdingWriter $writer): RedirectResponse
    {
        $kind = $this->kind();
        $record = $this->attemptTax(fn (): Model => $writer->create($kind, $this->taxActor(), $request->validated()));

        return redirect()
            ->route($kind.'.show', $record)
            ->with('status', $kind === 'vds'
                ? 'VDS record saved. It is not output VAT and it was not added to a Mushak total.'
                : 'AIT record saved. It is not VAT and it was not added to a Mushak total.');
    }

    public function show(int $record): View
    {
        $kind = $this->kind();
        $row = $this->find($kind, $record);

        return view('tax.withholding.show', [
            'kind' => $kind,
            'record' => $row,
            'canVoid' => $this->taxActor()->hasPermission($kind.'.void') && $row->getAttribute('status') === 'recorded',
        ]);
    }

    public function void(Request $request, int $record, WithholdingWriter $writer): RedirectResponse
    {
        $kind = $this->kind();
        abort_unless($this->taxActor()->hasPermission($kind.'.void'), 403);
        $row = $this->find($kind, $record);
        $this->attemptTax(fn () => $writer->void($kind, $row, $this->taxActor(), $request->string('void_reason')->toString()));

        return redirect()->route($kind.'.show', $row)->with('status', 'Record voided. No VAT total was changed.');
    }

    private function kind(): string
    {
        $name = request()->route()?->getName();

        return is_string($name) && str_starts_with($name, 'ait.') ? 'ait' : 'vds';
    }

    private function model(string $kind): VdsRecord|AitRecord
    {
        return $kind === 'ait' ? new AitRecord : new VdsRecord;
    }

    private function find(string $kind, int $id): Model
    {
        $record = $this->model($kind)->newQuery()->find($id);
        abort_unless($record instanceof Model, 404);

        return $record;
    }

    /**
     * @return Collection<int, Party>
     */
    private function parties(string $role): Collection
    {
        return Party::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('role', $role))
            ->orderBy('legal_name')
            ->get();
    }
}
