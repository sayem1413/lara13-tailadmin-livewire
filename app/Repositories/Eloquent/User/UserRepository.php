<?php

namespace App\Repositories\Eloquent\User;

use App\Models\User;
use App\Repositories\Interfaces\User\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        protected User $model
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        string $sort = 'newest',
        array $filters = []
    ): LengthAwarePaginator {
        // applySearch()/applyFilters()/applySort() mutate the builder in
        // place and hand the same instance back - called here without
        // reassigning $query so it keeps its Builder<User> generic type
        // instead of widening to the helpers' unparameterized Builder.
        $query = $this->model->query()->with('roles');

        applySearch($query, $search, ['name', 'email']);

        // Roles are a relationship, not a plain column, so applyFilters()
        // (equality/whereIn only) can't express this - handled separately,
        // same as the reference implementation this pattern is based on.
        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }
        unset($filters['role']);

        applyFilters($query, $filters, ['is_active']);

        applySort($query, $sort, ['name', 'email']);

        // Infinite-scroll (see UsersIndex::loadMore()) grows $perPage instead
        // of advancing the page, so page is always pinned to 1.
        return $query->paginate($perPage, page: 1);
    }

    public function findOrFail(int $id): User
    {
        return $this->model->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkActivate(array $ids): int
    {
        return $this->model->query()->whereKey($ids)->update(['is_active' => true]);
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkDeactivate(array $ids): int
    {
        return $this->model->query()->whereKey($ids)->update(['is_active' => false]);
    }
}
