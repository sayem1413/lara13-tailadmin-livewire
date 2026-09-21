<?php

namespace App\Repositories\Interfaces\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
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
    ): LengthAwarePaginator;

    /**
     * The same search/filter/sort query paginate() paginates, unpaginated -
     * for exporting every matching row rather than just the current page.
     *
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
    ): Builder;

    public function findOrFail(int $id, bool $withTrashed = false): User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    public function restore(User $user): User;

    public function forceDelete(User $user): bool;

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkActivate(array $ids): int;

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkDeactivate(array $ids): int;
}
