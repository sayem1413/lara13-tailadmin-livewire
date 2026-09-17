<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    /**
     * The Super Admin role bypasses all authorization via Gate::before, so
     * it must never be editable - otherwise a lesser role granted
     * roles.update could strip its permissions.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && $role->name !== 'Super Admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && $role->name !== 'Super Admin';
    }
}
