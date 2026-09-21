<?php

namespace App\Repositories\Eloquent\User;

use App\Models\User;
use App\Repositories\Interfaces\User\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        protected User $model
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  string|null  $trashed  'only' for soft-deleted rows only, 'with'
     *                                for both, anything else excludes them -
     *                                see applyTrashedFilter() in helpers.php.
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): LengthAwarePaginator {
        // Infinite-scroll (see UsersIndex::loadMore()) grows $perPage instead
        // of advancing the page, so page is always pinned to 1.
        return $this->filteredQuery($search, $sort, $filters, $trashed)->paginate($perPage, page: 1);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  string|null  $trashed  'only' for soft-deleted rows only, 'with'
     *                                for both, anything else excludes them -
     *                                see applyTrashedFilter() in helpers.php.
     * @return Builder<User>
     */
    public function filteredQuery(
        ?string $search = null,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): Builder {
        // applySearch()/applyFilters()/applySort() mutate the builder in
        // place and hand the same instance back - called here without
        // reassigning $query so it keeps its Builder<User> generic type
        // instead of widening to the helpers' unparameterized Builder.
        $query = $this->model->query()->with('roles');

        applyTrashedFilter($query, $trashed);

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

        // applySort()'s custom-column branch (used here for the fixed
        // "name_asc" sort - see UsersIndex::users()) has no unique
        // tie-breaker, which is fine for a single LIMIT/OFFSET page but
        // unsafe for FromQuery's chunked export walk: two same-named users
        // could each land in the wrong chunk boundary and one gets
        // skipped, the other duplicated. An "id" tie-breaker fixes that
        // for every consumer of this query, not just export.
        $query->orderBy('id');

        return $query;
    }

    public function findOrFail(int $id, bool $withTrashed = false): User
    {
        $query = $withTrashed ? $this->model->newQuery()->withTrashed() : $this->model->query();

        return $query->findOrFail($id);
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

    public function restore(User $user): User
    {
        $user->restore();

        return $user->refresh();
    }

    public function forceDelete(User $user): bool
    {
        return (bool) $user->forceDelete();
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
