<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SuperAdministratorGuard
{
    /**
     * @param  list<int>  $roleIds
     */
    public function assertChangeIsSafe(User $subject, array $roleIds, bool $willBeActive): void
    {
        if (! $subject->isSuperAdministrator()) {
            return;
        }

        $keepsRole = Role::query()
            ->whereIn('id', $roleIds)
            ->where('slug', 'super-administrator')
            ->exists();

        if ($keepsRole && $willBeActive) {
            return;
        }

        $anotherActiveSuper = User::query()
            ->where('is_active', true)
            ->whereKeyNot($subject->getKey())
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super-administrator'))
            ->exists();

        if (! $anotherActiveSuper) {
            throw ValidationException::withMessages([
                'roles' => 'At least one active super administrator is required.',
            ]);
        }
    }
}
