<?php

namespace App\Repositories\Interfaces\Product;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  string|null  $trashed  'only' for soft-deleted rows only, 'with'
     *                                for both, anything else excludes them -
     *                                see applyTrashedFilter() in helpers.php.
     * @return LengthAwarePaginator<int, Product>
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
     * @return Builder<Product>
     */
    public function filteredQuery(
        ?string $search = null,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): Builder;

    public function findOrFail(int $id, bool $withTrashed = false): Product;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product;

    public function delete(Product $product): bool;

    public function restore(Product $product): Product;

    public function forceDelete(Product $product): bool;

    /**
     * @return Builder<Product>
     */
    public function lowStockQuery(int $defaultThreshold): Builder;

    /**
     * The only place stock_quantity is written - it isn't fillable (see
     * Product), so a plain update()/create() can never touch it.
     */
    public function setStockQuantity(Product $product, int $quantity): Product;
}
