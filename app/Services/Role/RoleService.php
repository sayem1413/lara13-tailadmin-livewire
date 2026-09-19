<?php

namespace App\Services\Role;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Repositories\Interfaces\Role\RoleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(
        protected RoleRepositoryInterface $roleRepository
    ) {}

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(?string $search = null, int $perPage = 10, string $sort = 'newest'): LengthAwarePaginator
    {
        return $this->roleRepository->paginate($search, $perPage, $sort);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRole(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];

        return DB::transaction(function () use ($data, $permissions) {
            $role = $this->roleRepository->create(['name' => $data['name']]);

            $role->syncPermissions($this->expandPermissions($permissions));

            return $role;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRole(Role $role, array $data): Role
    {
        $this->guardAgainstSuperAdminRole($role, 'modified');

        $permissions = $data['permissions'] ?? null;

        return DB::transaction(function () use ($role, $data, $permissions) {
            $role = $this->roleRepository->update($role, ['name' => $data['name']]);

            if ($permissions !== null) {
                $role->syncPermissions($this->expandPermissions($permissions));
            }

            return $role;
        });
    }

    public function deleteRole(Role $role): bool
    {
        $this->guardAgainstSuperAdminRole($role, 'deleted');

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => "\"{$role->name}\" is assigned to at least one user and can't be deleted.",
            ]);
        }

        return $this->roleRepository->delete($role);
    }

    /**
     * The given permission names, plus any module-level view permissions
     * they imply (see Permission::IMPLYING_SECTIONS) - public so the Role
     * form's live checkbox matrix can reflect this as the user checks
     * boxes, not only once the role is actually saved.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function expandPermissions(array $names): array
    {
        if ($names === []) {
            return $names;
        }

        $ids = Permission::query()->whereIn('name', $names)->pluck('id')->all();

        return Permission::query()
            ->whereIn('id', Permission::expandWithImplied($ids))
            ->pluck('name')
            ->all();
    }

    /**
     * The Super Admin role bypasses all authorization via Gate::before (see
     * AppServiceProvider), which lets a Super Admin actor sail straight
     * through RolePolicy::update()/delete()'s own "not Super Admin" check -
     * so it has to be re-asserted here too, or a Super Admin actor could
     * rename, strip permissions from, or delete the one role every
     * Gate::before check depends on, locking every Super Admin out at once.
     */
    protected function guardAgainstSuperAdminRole(Role $role, string $action): void
    {
        if ($role->name === 'Super Admin') {
            throw ValidationException::withMessages([
                'role' => "The Super Admin role cannot be {$action}.",
            ]);
        }
    }
}
