<?php

namespace App\Repositories\Interfaces\Category;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  string|null  $trashed  'only' for soft-deleted rows only, 'with'
     *                                for both, anything else excludes them -
     *                                see applyTrashedFilter() in helpers.php.
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Category>
     */
    public function filteredQuery(
        ?string $search = null,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): Builder;

    /**
     * Every category, for building the parent picker - unpaginated since
     * a catalog's category count is expected to be small.
     *
     * @return Collection<int, Category>
     */
    public function all(): Collection;

    public function findOrFail(int $id, bool $withTrashed = false): Category;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category;

    public function delete(Category $category): bool;

    public function restore(Category $category): Category;

    public function forceDelete(Category $category): bool;

    /**
     * Ids of $category's own subtree (including itself) - see
     * ClosureTableManager::descendantIds().
     *
     * @return array<int, int|string>
     */
    public function descendantIds(Category $category): array;

    /**
     * Hard-removes every category_product pivot row for $category
     * (bypassing the soft-delete the normal sync flow uses) - required
     * before a force-delete, since the pivot's FKs are restrict.
     */
    public function detachAllProducts(Category $category): void;
}
