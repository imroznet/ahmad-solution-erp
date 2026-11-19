<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUnitRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('units.index', [
            'units' => Unit::query()->orderBy('sort_order')->orderBy('name')->get(),
            'canManage' => $this->actor(request()->user())->hasPermission('units.manage'),
        ]);
    }

    public function store(SaveUnitRequest $request, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $unit = Unit::query()->create([
            'name' => $request->validated('name'),
            'symbol' => $request->validated('symbol'),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $audit->record('unit_created', $unit, newValues: [
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'sort_order' => $unit->sort_order,
            'is_active' => $unit->is_active,
        ], actor: $actor);

        return redirect()->route('units.index')->with('status', 'Unit added.');
    }

    public function update(SaveUnitRequest $request, Unit $unit, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $before = [
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'sort_order' => $unit->sort_order,
            'is_active' => $unit->is_active,
        ];

        $unit->fill([
            'name' => $request->validated('name'),
            'symbol' => $request->validated('symbol'),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);
        $unit->save();

        $audit->record('unit_updated', $unit, oldValues: $before, newValues: [
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'sort_order' => $unit->sort_order,
            'is_active' => $unit->is_active,
        ], actor: $actor);

        return redirect()->route('units.index')->with('status', 'Unit updated.');
    }

    public function destroy(Unit $unit, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor(request()->user());
        abort_unless($actor->hasPermission('units.manage'), 403);
        $before = [
            'name' => $unit->name,
            'symbol' => $unit->symbol,
        ];
        $unit->delete();
        $audit->record('unit_deleted', $unit, oldValues: $before, actor: $actor);

        return redirect()->route('units.index')->with('status', 'Unit deleted. Documents that already use this text are unchanged.');
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
