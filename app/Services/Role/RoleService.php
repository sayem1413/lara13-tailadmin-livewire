<?php

namespace App\Services\Role;

use App\Models\Permission\Role;
use App\Repositories\Interfaces\Permission\PermissionRepositoryInterface;
use App\Repositories\Interfaces\Role\RoleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(
        protected RoleRepositoryInterface $roleRepository,
        protected PermissionRepositoryInterface $permissionRepository
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
        $permissions = $this->expandPermissions($data['permissions'] ?? []);

        $this->guardAgainstUnassignablePermissions($permissions);

        return DB::transaction(function () use ($data, $permissions) {
            $role = $this->roleRepository->create(Arr::only($data, ['name', 'description', 'is_active']));

            $role->syncPermissions($permissions);

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

        if ($permissions !== null) {
            $permissions = $this->expandPermissions($permissions);

            // Only permissions being newly added need the escalation check -
            // the form always resubmits the role's full permission set even
            // when the actor only touched its name/description/is_active, so
            // guarding the whole set would block an unrelated edit to a role
            // that already (legitimately) carries a permission the acting
            // user doesn't personally hold.
            $addedPermissions = array_diff($permissions, $role->permissions->pluck('name')->all());

            $this->guardAgainstUnassignablePermissions($addedPermissions);
        }

        return DB::transaction(function () use ($role, $data, $permissions) {
            $role = $this->roleRepository->update($role, Arr::only($data, ['name', 'description', 'is_active']));

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            return $role;
        });
    }

    public function findOrFail(int $id): Role
    {
        return $this->roleRepository->findOrFail($id);
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
        return $this->permissionRepository->expandWithImpliedNames($names);
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

    /**
     * A role-manager (anyone holding admin.roles.create/admin.roles.edit)
     * could otherwise build a role carrying ANY permission in the system -
     * including ones they don't personally hold - then self-assign that
     * role via Users to escalate their own access. Every permission being
     * attached to a role must therefore already be one the acting user
     * holds themselves, the same way StoreUserRequest/UpdateUserRequest
     * only check a submitted role *exists*, not who's allowed to grant it,
     * which is why UserService::guardAgainstUnassignableSuperAdminRole()
     * exists - this is that same guard, one layer up, for permissions
     * instead of the single Super Admin role.
     *
     * Super Admin bypasses this (mirrors
     * UserService::guardAgainstUnassignableSuperAdminRole()'s own check):
     * it already holds every permission via Gate::before, so re-deriving
     * that from hasPermissionTo() checks here would be redundant.
     *
     * @param  array<int, string>  $names
     */
    protected function guardAgainstUnassignablePermissions(array $names): void
    {
        if (auth()->user()?->hasRole('Super Admin')) {
            return;
        }

        $unassignable = collect($names)->reject(fn (string $name) => (bool) auth()->user()?->can($name));

        if ($unassignable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'You can only assign permissions you currently hold.',
            ]);
        }
    }
}
