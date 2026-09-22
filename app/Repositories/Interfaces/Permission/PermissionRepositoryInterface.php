<?php

namespace App\Repositories\Interfaces\Permission;

use App\Models\Permission\Permission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    /**
     * Permissions grouped by module (e.g. "users") for the Role form's
     * checkbox matrix.
     *
     * @return Collection<int|string, EloquentCollection<int, Permission>>
     */
    public function allGroupedByModule(): Collection;

    /**
     * @return array<int, string>
     */
    public function allNames(): array;

    /**
     * The given permission names, plus any module-level view permissions
     * they imply (see Permission::IMPLYING_SECTIONS).
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function expandWithImpliedNames(array $names): array;
}
