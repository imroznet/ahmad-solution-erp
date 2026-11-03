<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('roles.edit', [
            'role' => $role,
            'permissionGroups' => Permission::query()->orderBy('permission_group')->orderBy('name')->get()->groupBy('permission_group'),
            'locked' => $role->slug === 'super-administrator',
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        if ($role->slug === 'super-administrator') {
            abort(403);
        }

        $old = $role->permissions()->orderBy('slug')->pluck('slug')->all();
        $role->permissions()->sync($request->validated('permissions'));
        $new = $role->permissions()->orderBy('slug')->pluck('slug')->all();

        $audit->record('role_permissions_updated', $role, oldValues: [
            'permissions' => $old,
        ], newValues: [
            'permissions' => $new,
        ]);

        return redirect()->route('roles.index')->with('status', 'Role permissions updated.');
    }
}
