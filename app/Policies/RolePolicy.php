<?php

namespace App\Policies;

use App\Models\Permission\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.roles.index');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('admin.roles.index');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.roles.create');
    }

    /**
     * The Super Admin role bypasses all authorization via Gate::before, so
     * it must never be editable - otherwise a lesser role granted
     * admin.roles.edit could strip its permissions.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('admin.roles.edit') && $role->name !== 'Super Admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('admin.roles.destroy') && $role->name !== 'Super Admin';
    }
}
