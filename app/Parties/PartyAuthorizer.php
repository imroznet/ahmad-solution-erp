<?php

namespace App\Parties;

use App\Models\Party;
use App\Models\User;

/**
 * Route middleware checks that the user holds at least one party permission.
 * These methods decide which record and which action that permission allows.
 */
final class PartyAuthorizer
{
    public function canView(User $user, Party $party): bool
    {
        if ($user->hasPermission('parties.view')) {
            return true;
        }

        if ($party->hasRole('customer') && $user->hasPermission('customers.view')) {
            return true;
        }

        return $party->hasRole('supplier') && $user->hasPermission('suppliers.view');
    }

    /**
     * @param  list<string>  $roles
     */
    public function canCreate(User $user, array $roles): bool
    {
        $roles = array_values(array_unique($roles));

        if ($roles === [] || array_diff($roles, ['customer', 'supplier']) !== []) {
            return false;
        }

        if ($user->hasPermission('parties.create')) {
            return true;
        }

        if (in_array('customer', $roles, true) && ! $user->hasPermission('customers.manage')) {
            return false;
        }

        if (in_array('supplier', $roles, true) && ! $user->hasPermission('suppliers.manage')) {
            return false;
        }

        return true;
    }

    public function canUpdate(User $user, Party $party): bool
    {
        if ($party->isArchived()) {
            return false;
        }

        if ($user->hasPermission('parties.update')) {
            return true;
        }

        $customer = $party->hasRole('customer');
        $supplier = $party->hasRole('supplier');

        if ($customer && $supplier) {
            return $user->hasPermission('customers.manage') && $user->hasPermission('suppliers.manage');
        }

        if ($customer) {
            return $user->hasPermission('customers.manage');
        }

        if ($supplier) {
            return $user->hasPermission('suppliers.manage');
        }

        return false;
    }

    public function canChangeRole(User $user, string $role): bool
    {
        if ($user->hasPermission('parties.update')) {
            return true;
        }

        return match ($role) {
            'customer' => $user->hasPermission('customers.manage'),
            'supplier' => $user->hasPermission('suppliers.manage'),
            default => false,
        };
    }

    public function canManageContacts(User $user, Party $party): bool
    {
        return ! $party->isArchived()
            && $user->hasPermission('parties.contacts.manage')
            && $this->canUpdate($user, $party);
    }

    public function canManageAddresses(User $user, Party $party): bool
    {
        return ! $party->isArchived()
            && $user->hasPermission('parties.addresses.manage')
            && $this->canUpdate($user, $party);
    }

    public function canArchive(User $user, Party $party): bool
    {
        return $user->hasPermission('parties.archive') && $this->canView($user, $party);
    }

    /**
     * @return list<string>
     */
    public function visibleRoles(User $user, Party $party): array
    {
        $roles = [];

        if ($party->hasRole('customer') && ($user->hasPermission('parties.view') || $user->hasPermission('customers.view') || $user->hasPermission('customers.manage'))) {
            $roles[] = 'customer';
        }

        if ($party->hasRole('supplier') && ($user->hasPermission('parties.view') || $user->hasPermission('suppliers.view') || $user->hasPermission('suppliers.manage'))) {
            $roles[] = 'supplier';
        }

        return $roles;
    }
}
