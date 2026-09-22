<?php

namespace App\Repositories\Eloquent\Category;

use App\Models\Category;
use App\Models\CategoryProduct;
use App\Repositories\Interfaces\Category\CategoryRepositoryInterface;
use App\Services\Lifecycle\ClosureTableManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        protected Category $model,
        protected ClosureTableManager $closures,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): LengthAwarePaginator {
        return $this->filteredQuery($search, $sort, $filters, $trashed)->paginate($perPage, page: 1);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Category>
     */
    public function filteredQuery(
        ?string $search = null,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): Builder {
        $query = $this->model->query()->with('parent')->withCount(['children', 'products']);

        applyTrashedFilter($query, $trashed);

        applySearch($query, $search, ['name', 'slug']);

        applyFilters($query, $filters, ['lifecycle_status', 'parent_id']);

        applySort($query, $sort, ['name', 'sort_order', 'created_at']);

        // See UserRepository::filteredQuery() for why a chunked export
        // walk needs this tie-breaker in addition to applySort().
        $query->orderBy('id');

        return $query;
    }

    /**
     * @return Collection<int, Category>
     */
    public function all(): Collection
    {
        return $this->model->query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function findOrFail(int $id, bool $withTrashed = false): Category
    {
        $query = $withTrashed ? $this->model->newQuery()->withTrashed() : $this->model->query();

        return $query->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return $this->model->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }

    public function restore(Category $category): Category
    {
        $category->restore();

        return $category->refresh();
    }

    public function forceDelete(Category $category): bool
    {
        return (bool) $category->forceDelete();
    }

    /**
     * @return array<int, int|string>
     */
    public function descendantIds(Category $category): array
    {
        return $this->closures->descendantIds($category);
    }

    public function detachAllProducts(Category $category): void
    {
        // Hard-removes every pivot row, including ones already
        // soft-removed by syncCategories() - a force-delete must clear
        // all of them, since the pivot's FKs are restrict.
        CategoryProduct::withTrashed()->where('category_id', $category->id)->forceDelete();
    }
}
