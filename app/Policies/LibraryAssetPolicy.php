<?php

namespace App\Policies;

use App\Models\LibraryAsset;
use App\Models\User;

class LibraryAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.media.index');
    }

    public function view(User $user, LibraryAsset $libraryAsset): bool
    {
        return $user->can('admin.media.index');
    }

    public function delete(User $user, LibraryAsset $libraryAsset): bool
    {
        return $user->can('admin.media.destroy');
    }
}
