<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.categories.index');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('admin.categories.index');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('admin.categories.edit');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('admin.categories.destroy');
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can('admin.categories.restore');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->can('admin.categories.force-delete');
    }
}
