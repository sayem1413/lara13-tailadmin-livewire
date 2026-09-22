<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.products.index');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('admin.products.index');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('admin.products.edit');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('admin.products.destroy');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('admin.products.restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can('admin.products.force-delete');
    }

    public function adjustStock(User $user, Product $product): bool
    {
        return $user->can('admin.inventory.adjust') && ! $product->trashed();
    }
}
