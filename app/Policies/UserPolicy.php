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

    /**
     * Same Super Admin protection as update()/delete() - restoring a
     * Super Admin's account back into service is as sensitive as editing
     * one, so it's likewise reserved for another Super Admin.
     */
    public function restore(User $user, User $model): bool
    {
        if (! $user->can('admin.users.restore')) {
            return false;
        }

        return ! $model->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }

    /**
     * Same Super Admin protection as delete(), plus the same no-self-service
     * rule - permanently destroying your own account through the admin UI
     * would be irreversible and unrecoverable.
     */
    public function forceDelete(User $user, User $model): bool
    {
        if (! $user->can('admin.users.force-delete') || $model->is($user)) {
            return false;
        }

        return ! $model->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }
}
