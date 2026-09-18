<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.users.index');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('admin.users.index');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.users.create');
    }

    /**
     * Only a Super Admin may edit another Super Admin's account, regardless
     * of who else holds the admin.users.edit permission.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->can('admin.users.edit')) {
            return false;
        }

        return ! $model->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }

    /**
     * Same Super Admin protection as update(), plus no one may delete
     * their own account through the admin UI.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->can('admin.users.destroy') || $model->is($user)) {
            return false;
        }

        return ! $model->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }
}
