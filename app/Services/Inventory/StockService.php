<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\Interfaces\Inventory\StockMovementRepositoryInterface;
use App\Repositories\Interfaces\Product\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single hook point for every stock change - Phase 2's order
 * fulfillment will call adjust() the same way this phase's Inventory UI
 * does. Every write goes through here so
 * Product::stock_quantity === SUM(stock_movements.quantity_change) for
 * that product always holds; nothing else may write stock_quantity (see
 * Product's #[Fillable] and ProductRepository::setStockQuantity()).
 */
class StockService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected StockMovementRepositoryInterface $stockMovementRepository,
    ) {}

    /**
     * Records a stock change and updates the product's running balance
     * in the same transaction. $quantityChange is signed (+50 received,
     * -3 sold). $reference is an optional related record (Phase 2:
     * Order/OrderItem) recorded on the movement for traceability.
     */
    public function adjust(
        Product $product,
        int $quantityChange,
        StockMovementType $type,
        ?string $reason = null,
        ?Model $reference = null,
    ): StockMovement {
        if (! $product->track_inventory) {
            throw ValidationException::withMessages([
                'stock' => "Inventory isn't tracked for \"{$product->name}\".",
            ]);
        }

        if ($quantityChange === 0) {
            throw ValidationException::withMessages([
                'quantity_change' => 'The quantity change cannot be zero.',
            ]);
        }

        return DB::transaction(function () use ($product, $quantityChange, $type, $reason, $reference): StockMovement {
            // Locks the product's own row for the rest of this
            // transaction, so two staff adjusting the same product's
            // stock at once can't both read the same stale balance (the
            // second writer blocks until the first commits).
            $locked = $product->newQuery()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $newQuantity = $locked->stock_quantity + $quantityChange;

            if ($newQuantity < 0 && ! setting('allow_negative_stock', false)) {
                throw ValidationException::withMessages([
                    'quantity_change' => "Only {$locked->stock_quantity} in stock.",
                ]);
            }

            $movement = $this->stockMovementRepository->create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'quantity_after' => $newQuantity,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => Auth::id(),
            ]);

            $this->productRepository->setStockQuantity($product, $newQuantity);
            $product->setAttribute('stock_quantity', $newQuantity);

            return $movement;
        });
    }

    /**
     * Sets the stock to an exact quantity by computing and recording the
     * delta as an Adjustment movement - for a stock count/correction,
     * rather than the caller having to compute the delta itself.
     */
    public function setLevel(Product $product, int $newQuantity, ?string $reason = null): StockMovement
    {
        $delta = $newQuantity - $product->stock_quantity;

        if ($delta === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'That is already the current stock level.',
            ]);
        }

        return $this->adjust($product, $delta, StockMovementType::Adjustment, $reason);
    }

    /**
     * @return Builder<Product>
     */
    public function lowStockQuery(): Builder
    {
        return $this->productRepository->lowStockQuery((int) setting('low_stock_threshold', 10));
    }

    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function historyFor(Product $product, int $perPage = 20): LengthAwarePaginator
    {
        return $this->stockMovementRepository->paginateForProduct($product, $perPage);
    }
}
