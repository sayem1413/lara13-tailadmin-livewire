<?php

namespace App\Repositories\Eloquent\Product;

use App\Models\Product;
use App\Repositories\Interfaces\Product\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        protected Product $model
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
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
     * @return Builder<Product>
     */
    public function filteredQuery(
        ?string $search = null,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): Builder {
        $query = $this->model->query()->with('categories');

        applyTrashedFilter($query, $trashed);

        applySearch($query, $search, ['name', 'sku', 'barcode']);

        // Category is a relationship, not a plain column - handled
        // separately, same carve-out UserRepository makes for "role".
        // 'include_descendants' lets the caller pass every id in the
        // chosen category's own subtree (see CategoryService::selectOptions()
        // callers / ProductsIndex), so filtering by a parent category also
        // matches products filed directly under its children.
        if (! empty($filters['category_ids'])) {
            $categoryIds = (array) $filters['category_ids'];
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
        }
        unset($filters['category_ids']);

        if (($filters['stock'] ?? null) === 'low') {
            $query->where('track_inventory', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        } elseif (($filters['stock'] ?? null) === 'out') {
            $query->where('track_inventory', true)->where('stock_quantity', '<=', 0);
        }
        unset($filters['stock']);

        applyFilters($query, $filters, ['lifecycle_status', 'track_inventory', 'unit']);

        applySort($query, $sort, ['name', 'sku', 'price', 'stock_quantity', 'created_at']);

        $query->orderBy('id');

        return $query;
    }

    public function findOrFail(int $id, bool $withTrashed = false): Product
    {
        $query = $withTrashed ? $this->model->newQuery()->withTrashed() : $this->model->query();

        return $query->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh();
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }

    public function restore(Product $product): Product
    {
        $product->restore();

        return $product->refresh();
    }

    public function forceDelete(Product $product): bool
    {
        return (bool) $product->forceDelete();
    }

    /**
     * @return Builder<Product>
     */
    public function lowStockQuery(int $defaultThreshold): Builder
    {
        return $this->model->query()
            ->where('track_inventory', true)
            ->whereRaw('stock_quantity <= COALESCE(low_stock_threshold, ?)', [$defaultThreshold]);
    }

    public function setStockQuantity(Product $product, int $quantity): Product
    {
        $product->forceFill(['stock_quantity' => $quantity])->save();

        return $product;
    }
}
