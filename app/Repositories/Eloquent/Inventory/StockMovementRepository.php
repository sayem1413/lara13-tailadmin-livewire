<?php

namespace App\Repositories\Eloquent\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\Interfaces\Inventory\StockMovementRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(
        protected StockMovement $model
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StockMovement
    {
        return $this->model->create($data);
    }

    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginateForProduct(Product $product, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->query()
            ->where('product_id', $product->id)
            ->with('creator')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StockMovement>
     */
    public function filteredQuery(?string $search = null, string $sort = 'newest', array $filters = []): Builder
    {
        $query = $this->model->query()->with(['product', 'creator']);

        applySearch($query, $search, ['reason']);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        unset($filters['product_id']);

        applyFilters($query, $filters, ['type']);

        applySort($query, $sort, ['created_at']);

        $query->orderBy('id');

        return $query;
    }
}
