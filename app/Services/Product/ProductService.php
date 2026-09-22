<?php

namespace App\Services\Product;

use App\Enums\LifecycleStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\CategoryProduct;
use App\Models\LibraryAsset;
use App\Models\Product;
use App\Repositories\Interfaces\Product\ProductRepositoryInterface;
use App\Services\Inventory\StockService;
use App\Services\Media\MediaService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected StockService $stockService,
        protected MediaService $mediaService,
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
        return $this->productRepository->paginate($search, $perPage, $sort, $filters, $trashed);
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
        return $this->productRepository->filteredQuery($search, $sort, $filters, $trashed);
    }

    public function findOrFail(int $id, bool $withTrashed = false): Product
    {
        return $this->productRepository->findOrFail($id, $withTrashed);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data): Product
    {
        $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : $this->generateSku($data['name']);
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : Str::slug($data['name']);

        $this->guardUniqueSku($data['sku']);

        $categoryIds = $data['categories'] ?? [];
        $initialStock = (int) ($data['initial_stock'] ?? 0);
        unset($data['categories'], $data['initial_stock']);

        return DB::transaction(function () use ($data, $categoryIds, $initialStock): Product {
            $product = $this->productRepository->create($data);

            $this->syncCategories($product, $categoryIds);

            if ($initialStock > 0) {
                $this->stockService->adjust($product, $initialStock, StockMovementType::Initial, 'Initial stock on creation');
            }

            return $product->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        if (array_key_exists('sku', $data) && $data['sku'] !== $product->sku) {
            $this->guardUniqueSku($data['sku'], $product);
        }

        if (array_key_exists('slug', $data)) {
            $data['slug'] = filled($data['slug']) ? $data['slug'] : Str::slug($data['name'] ?? $product->name);
        }

        $categoryIds = $data['categories'] ?? null;
        // Belt-and-braces: stock_quantity isn't fillable, so a plain
        // update() can never touch it, but this makes the intent
        // explicit at the one call site that accepts arbitrary $data.
        unset($data['categories'], $data['initial_stock'], $data['stock_quantity']);

        return DB::transaction(function () use ($product, $data, $categoryIds): Product {
            $product = $this->productRepository->update($product, $data);

            if ($categoryIds !== null) {
                $this->syncCategories($product, $categoryIds);
            }

            return $product;
        });
    }

    /**
     * Guards SKU uniqueness at the Service layer on top of the Form
     * Request's own Rule::unique()->withoutTrashed() check - there's no
     * DB unique index (see the products migration), and
     * Product::fromImportRow() bypasses the Form Request entirely, so
     * this is the only check an import actually goes through.
     */
    protected function guardUniqueSku(string $sku, ?Product $ignoring = null): void
    {
        $query = Product::query()->where('sku', $sku);

        if ($ignoring) {
            $query->whereKeyNot($ignoring->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sku' => "The SKU \"{$sku}\" is already in use.",
            ]);
        }
    }

    /**
     * Replaces $product's category assignments with exactly $categoryIds -
     * restores a previously soft-removed pivot row rather than
     * duplicating it, and soft-removes (never hard-deletes) a row that's
     * no longer selected, so history stays auditable. Never blind-inserts,
     * which is what makes the lack of a DB unique index on the pivot pair
     * safe (see the category_product migration).
     *
     * @param  array<int, int>  $categoryIds
     */
    public function syncCategories(Product $product, array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if ($categoryIds !== []) {
            $activeCount = Category::query()
                ->whereKey($categoryIds)
                ->where('lifecycle_status', LifecycleStatus::Active->value)
                ->count();

            if ($activeCount !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'categories' => 'One or more selected categories no longer exist or are inactive.',
                ]);
            }
        }

        $currentIds = $product->categories()->pluck('categories.id')->all();

        $toRemove = array_diff($currentIds, $categoryIds);
        $toAdd = array_diff($categoryIds, $currentIds);

        if ($toRemove !== []) {
            CategoryProduct::query()
                ->where('product_id', $product->id)
                ->whereIn('category_id', $toRemove)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        foreach ($toAdd as $categoryId) {
            $existing = CategoryProduct::withTrashed()
                ->where('product_id', $product->id)
                ->where('category_id', $categoryId)
                ->first();

            if ($existing) {
                $existing->restore();
            } else {
                CategoryProduct::query()->create([
                    'product_id' => $product->id,
                    'category_id' => $categoryId,
                    'created_by' => Auth::id(),
                ]);
            }
        }
    }

    public function activateProduct(Product $product): void
    {
        $product->activate();
    }

    public function deactivateProduct(Product $product): void
    {
        $product->deactivate();
    }

    /**
     * Marks a product discontinued - kept forever (never force-deletable
     * while it has stock history) but never sellable again.
     * HasActiveStatus's activate()/deactivate() only toggle between
     * Active/Inactive, so Archived is set directly here.
     */
    public function archiveProduct(Product $product): void
    {
        $product->forceFill(['lifecycle_status' => LifecycleStatus::Archived])->save();
    }

    public function deleteProduct(Product $product): bool
    {
        return $this->productRepository->delete($product);
    }

    public function restoreProduct(Product $product): Product
    {
        return $this->productRepository->restore($product);
    }

    /**
     * Blocks permanently destroying a product with any stock history -
     * the products.id FK on stock_movements is restrict, so this turns
     * that raw constraint violation into a clear, actionable message
     * instead of a 500. Media is cleaned up automatically:
     * InteractsWithMedia hooks the model's "deleting" event and clears
     * all collections once forceDeleting is true.
     */
    public function forceDeleteProduct(Product $product): bool
    {
        if ($product->stockMovements()->exists()) {
            throw ValidationException::withMessages([
                'product' => "\"{$product->name}\" has stock history and can't be permanently deleted.",
            ]);
        }

        return $this->productRepository->forceDelete($product);
    }

    /**
     * @param  array<int, UploadedFile>  $uploads
     * @return array<int, Media>
     */
    public function addImages(Product $product, array $uploads): array
    {
        return array_map(
            fn (UploadedFile $file) => $product->addMedia($file)->toMediaCollection('images'),
            $uploads
        );
    }

    public function removeImage(Product $product, int $mediaId): void
    {
        $product->media()->whereKey($mediaId)->firstOrFail()->delete();
    }

    public function attachFromLibrary(Product $product, LibraryAsset $asset): Media
    {
        return $this->mediaService->attachToModel($asset, $product, 'images');
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    public function bulkActivate(array $ids): array
    {
        return $this->bulkApply($ids, fn (Product $product) => $this->activateProduct($product));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    public function bulkDeactivate(array $ids): array
    {
        return $this->bulkApply($ids, fn (Product $product) => $this->deactivateProduct($product));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    public function bulkDelete(array $ids): array
    {
        return $this->bulkApply($ids, fn (Product $product) => $this->deleteProduct($product));
    }

    /**
     * Same partial-result pattern as CategoryService::bulkApply() - see
     * its docblock for why a skip is silent rather than aborting.
     *
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    protected function bulkApply(array $ids, Closure $action): array
    {
        $affected = 0;
        $skipped = 0;

        foreach (Product::query()->whereKey($ids)->get() as $product) {
            if (! Gate::allows('update', $product)) {
                $skipped++;

                continue;
            }

            try {
                $action($product);
                $affected++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * A short, unique-looking SKU from the product name plus a random
     * suffix, retried on the rare collision - used when the form's SKU
     * field is left blank.
     */
    public function generateSku(string $name): string
    {
        $prefix = (string) setting('sku_prefix', 'SKU');
        $nameFragment = Str::of($name)->slug('')->upper()->substr(0, 6)->toString();

        do {
            $candidate = trim($prefix.'-'.$nameFragment.'-'.random_int(1000, 9999), '-');
        } while (Product::query()->where('sku', $candidate)->exists());

        return $candidate;
    }
}
