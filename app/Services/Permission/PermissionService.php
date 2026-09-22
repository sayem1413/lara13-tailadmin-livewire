<?php

namespace App\Services\Permission;

use App\Models\Permission\Permission;
use App\Repositories\Interfaces\Permission\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PermissionService
{
    public function __construct(
        protected PermissionRepositoryInterface $permissionRepository
    ) {}

    /**
     * @return Collection<int|string, EloquentCollection<int, Permission>>
     */
    public function groupedByModule(): Collection
    {
        return $this->permissionRepository->allGroupedByModule();
    }

    /**
     * @return array<int, string>
     */
    public function allNames(): array
    {
        return $this->permissionRepository->allNames();
    }
}
