<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionRevoker;
use App\Services\SuperAdministratorGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $this->searchTerm($request);

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $roleIds = $this->authorizedRoleIds($actor, $request->validated('roles'));

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($roleIds);

        $audit->record('user_created', $user, newValues: [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles()->orderBy('slug')->pluck('slug')->all(),
        ], actor: $actor);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $actor = $this->actor(request());

        if (! $actor->isSuperAdministrator() && $user->isSuperAdministrator()) {
            abort(403);
        }

        $user->load('roles');

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'editingSelf' => $actor->is($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $audit, SessionRevoker $sessions): RedirectResponse
    {
        $actor = $this->actor($request);

        if (! $actor->isSuperAdministrator() && $user->isSuperAdministrator()) {
            abort(403);
        }

        $editingSelf = $actor->is($user);
        $willBeActive = $user->is_active;

        if (! $editingSelf && $actor->hasPermission('users.deactivate')) {
            $willBeActive = $request->boolean('is_active');
        }

        $user->loadMissing('roles');
        $roleIds = $user->roles->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (! $editingSelf) {
            $roleIds = $this->authorizedRoleIds($actor, $request->validated('roles'));
            app(SuperAdministratorGuard::class)->assertChangeIsSafe($user, $roleIds, $willBeActive);
        }

        $original = [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles()->orderBy('slug')->pluck('slug')->all(),
        ];

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'is_active' => $willBeActive,
        ]);

        $passwordChanged = $request->filled('password');

        if ($passwordChanged) {
            $user->password = $request->validated('password');
            $user->remember_token = Str::random(60);
        }

        DB::transaction(function () use ($user, $editingSelf, $roleIds, $passwordChanged, $sessions, $audit, $actor, $original): void {
            $user->save();

            if (! $editingSelf) {
                $user->roles()->sync($roleIds);
            }

            if ($passwordChanged) {
                $sessions->revoke($user);
            }

            $audit->record('user_updated', $user, oldValues: $original, newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles()->orderBy('slug')->pluck('slug')->all(),
                'password' => $passwordChanged ? '[redacted]' : null,
            ], actor: $actor);
        });

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }

    /**
     * @param  list<int>  $roleIds
     * @return list<int>
     */
    private function authorizedRoleIds(User $actor, array $roleIds): array
    {
        $roles = Role::query()->whereIn('id', $roleIds)->get();

        if (! $actor->isSuperAdministrator() && $roles->contains(fn (Role $role): bool => $role->slug === 'super-administrator')) {
            throw ValidationException::withMessages([
                'roles' => 'Only a super administrator can assign the super administrator role.',
            ]);
        }

        return $roles->modelKeys();
    }

    private function searchTerm(Request $request): string
    {
        return trim(str_replace(['%', '_'], '', (string) $request->query('search', '')));
    }
}
