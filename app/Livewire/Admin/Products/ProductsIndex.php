<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use App\Services\Category\CategoryService;
use App\Services\Export\ExportService;
use App\Services\Product\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductsIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public bool $includeSubcategories = false;

    #[Url]
    public string $stock = '';

    /**
     * '' shows non-trashed products (the default), 'only' shows
     * soft-deleted ones - see applyTrashedFilter() in helpers.php.
     */
    #[Url]
    public string $trashed = '';

    /** @var array<int, int> */
    public array $selected = [];

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->perPage = 10;
    }

    public function updatingStatus(): void
    {
        $this->perPage = 10;
    }

    public function updatingCategory(): void
    {
        $this->perPage = 10;
    }

    public function updatingStock(): void
    {
        $this->perPage = 10;
    }

    public function updatingTrashed(): void
    {
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function toggleActive(Product $product): void
    {
        Gate::authorize('update', $product);

        try {
            $product->isLifecycleActive()
                ? app(ProductService::class)->deactivateProduct($product)
                : app(ProductService::class)->activateProduct($product);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());
        }
    }

    public function archive(Product $product): void
    {
        Gate::authorize('update', $product);

        app(ProductService::class)->archiveProduct($product);
    }

    public function delete(Product $product): void
    {
        Gate::authorize('delete', $product);

        app(ProductService::class)->deleteProduct($product);

        $this->selected = array_values(array_diff($this->selected, [$product->id]));
    }

    /**
     * Takes a plain id - see UsersIndex::restoreUser()'s docblock.
     */
    public function restoreProduct(int $productId): void
    {
        $product = app(ProductService::class)->findOrFail($productId, withTrashed: true);

        Gate::authorize('restore', $product);

        app(ProductService::class)->restoreProduct($product);
        $this->dispatch('toast', type: 'success', message: 'Product restored.');
    }

    public function forceDeleteProduct(int $productId): void
    {
        $product = app(ProductService::class)->findOrFail($productId, withTrashed: true);

        Gate::authorize('forceDelete', $product);

        try {
            app(ProductService::class)->forceDeleteProduct($product);
            $this->dispatch('toast', type: 'success', message: 'Product permanently deleted.');
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());
        }
    }

    public function toggleSelectAllOnPage(): void
    {
        $pageIds = $this->currentPageIds();

        $this->selected = $this->allOnPageSelected($pageIds)
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique(array_merge($this->selected, $pageIds)));
    }

    /**
     * @param  array<int, int>|null  $pageIds
     */
    public function allOnPageSelected(?array $pageIds = null): bool
    {
        $pageIds ??= $this->currentPageIds();

        return $pageIds !== [] && array_diff($pageIds, $this->selected) === [];
    }

    /**
     * @return array<int, int>
     */
    protected function currentPageIds(): array
    {
        return $this->products()->getCollection()->pluck('id')->all();
    }

    public function bulkActivate(): void
    {
        $result = app(ProductService::class)->bulkActivate($this->selected);
        $this->reportBulkResult('Activated', $result);
        $this->selected = [];
    }

    public function bulkDeactivate(): void
    {
        $result = app(ProductService::class)->bulkDeactivate($this->selected);
        $this->reportBulkResult('Deactivated', $result);
        $this->selected = [];
    }

    public function bulkDelete(): void
    {
        $result = app(ProductService::class)->bulkDelete($this->selected);
        $this->reportBulkResult('Deleted', $result);
        $this->selected = [];
    }

    /**
     * @param  array{affected: int, skipped: int}  $result
     */
    protected function reportBulkResult(string $verb, array $result): void
    {
        $noun = 'product'.($result['affected'] === 1 ? '' : 's');

        $this->dispatch('toast', type: $result['skipped'] > 0 ? 'warning' : 'success', message: $result['skipped'] > 0
            ? "{$verb} {$result['affected']} {$noun}; {$result['skipped']} skipped."
            : "{$verb} {$result['affected']} {$noun}.");
    }

    public function statusColor(Product $product): string
    {
        return match ($product->lifecycle_status->value) {
            'active' => 'green',
            'pending_activation' => 'brand',
            'archived' => 'red',
            default => 'gray',
        };
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    #[Computed]
    public function categoryOptions(): Collection
    {
        return app(CategoryService::class)->selectOptions();
    }

    /**
     * Re-renders this component (picking up the freshly imported rows)
     * once <x-import.button>'s modal finishes importing.
     */
    #[On('imported')]
    public function refreshAfterImport(): void {}

    public function export(ExportService $exportService): BinaryFileResponse
    {
        Gate::authorize('admin.products.export');

        $query = app(ProductService::class)->filteredQuery(
            search: $this->search,
            sort: 'name_asc',
            filters: $this->filters(),
            trashed: $this->trashed,
        );

        return $exportService->export($query, Product::class, 'products-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(): View
    {
        return view('livewire.admin.products.products-index', [
            'products' => $this->products(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    protected function products(): LengthAwarePaginator
    {
        return app(ProductService::class)->paginate(
            search: $this->search,
            perPage: $this->perPage,
            sort: 'name_asc',
            filters: $this->filters(),
            trashed: $this->trashed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        $categoryIds = [];

        if ($this->category !== '') {
            $selected = app(CategoryService::class)->findOrFail((int) $this->category);

            $categoryIds = $this->includeSubcategories
                ? app(CategoryService::class)->descendantIds($selected)
                : [$selected->id];
        }

        return [
            'lifecycle_status' => $this->status === '' ? null : $this->status,
            'category_ids' => $categoryIds,
            'stock' => $this->stock === '' ? null : $this->stock,
        ];
    }
}
