<?php

namespace App\Repositories\Eloquent\Role;

use App\Models\Permission\Role;
use App\Repositories\Interfaces\Role\RoleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        protected Role $model
    ) {}

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(?string $search = null, int $perPage = 10, string $sort = 'newest'): LengthAwarePaginator
    {
        // See UserRepository::paginate() - called without reassigning
        // $query so it keeps its Builder<Role> generic type.
        $query = $this->model->query()->withCount(['permissions', 'users']);

        applySearch($query, $search, ['name']);

        applySort($query, $sort, ['name']);

        return $query->paginate($perPage, page: 1);
    }

    public function findOrFail(int $id): Role
    {
        return $this->model->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role
    {
        // Not $this->model->create(): Spatie's own Role::create() override
        // is only PHPDoc-typed to return RoleContract|Role (not `static`),
        // so PHPStan can't see it as returning this app's own Role subclass.
        // Its extra behavior beyond a plain save() - a friendlier duplicate
        // check and multi-tenant "teams" support - doesn't apply here: the
        // caller already validates the name is unique, and this app doesn't
        // use Spatie's teams feature.
        $role = new Role([...$data, 'guard_name' => 'web']);
        $role->save();

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->refresh();
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }
}
