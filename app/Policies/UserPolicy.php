<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Only a Super Admin may edit another Super Admin's account, regardless
     * of who else holds the users.update permission.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->can('users.update')) {
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
        if (! $user->can('users.delete') || $model->is($user)) {
            return false;
        }

        return ! $model->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }
}
