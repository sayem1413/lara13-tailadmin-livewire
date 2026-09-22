<?php

namespace App\Repositories\Interfaces\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

interface StockMovementRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StockMovement;

    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginateForProduct(Product $product, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StockMovement>
     */
    public function filteredQuery(?string $search = null, string $sort = 'newest', array $filters = []): Builder;
}
