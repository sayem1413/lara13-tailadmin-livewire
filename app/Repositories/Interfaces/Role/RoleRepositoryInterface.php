<?php

namespace App\Repositories\Interfaces\Role;

use App\Models\Permission\Role;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoleRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(?string $search = null, int $perPage = 10, string $sort = 'newest'): LengthAwarePaginator;

    public function findOrFail(int $id): Role;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role;

    public function delete(Role $role): bool;
}
