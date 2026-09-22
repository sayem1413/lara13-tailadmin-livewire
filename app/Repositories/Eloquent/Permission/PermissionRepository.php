<?php

namespace App\Repositories\Eloquent\Permission;

use App\Models\Permission\Permission;
use App\Repositories\Interfaces\Permission\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(
        protected Permission $model
    ) {}

    /**
     * @return Collection<int|string, EloquentCollection<int, Permission>>
     */
    public function allGroupedByModule(): Collection
    {
        return $this->model->query()
            ->orderBy('module')
            ->orderBy('section')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->module ?? $permission->name);
    }

    /**
     * @return array<int, string>
     */
    public function allNames(): array
    {
        return $this->model->query()->pluck('name')->all();
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function expandWithImpliedNames(array $names): array
    {
        if ($names === []) {
            return $names;
        }

        $ids = $this->model->query()->whereIn('name', $names)->pluck('id')->all();

        return $this->model->query()
            ->whereIn('id', Permission::expandWithImplied($ids))
            ->pluck('name')
            ->all();
    }
}
