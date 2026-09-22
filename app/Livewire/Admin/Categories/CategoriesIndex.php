<?php

namespace App\Livewire\Admin\Categories;

use App\Exceptions\Lifecycle\LifecycleGuardException;
use App\Models\Category;
use App\Services\Category\CategoryService;
use App\Services\Export\ExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CategoriesIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    /**
     * '' shows non-trashed categories (the default), 'only' shows
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

    public function updatingTrashed(): void
    {
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function toggleActive(Category $category): void
    {
        Gate::authorize('update', $category);

        try {
            $category->isLifecycleActive()
                ? app(CategoryService::class)->deactivateCategory($category)
                : app(CategoryService::class)->activateCategory($category);
        } catch (ValidationException|LifecycleGuardException $exception) {
            $this->dispatch('toast', type: 'error', message: $this->firstErrorMessage($exception));
        }
    }

    public function delete(Category $category): void
    {
        Gate::authorize('delete', $category);

        try {
            app(CategoryService::class)->deleteCategory($category);
            $this->selected = array_values(array_diff($this->selected, [$category->id]));
        } catch (ValidationException|LifecycleGuardException $exception) {
            $this->dispatch('toast', type: 'error', message: $this->firstErrorMessage($exception));
        }
    }

    /**
     * Takes a plain id rather than a type-hinted Category $category -
     * Livewire's implicit action-parameter binding always resolves
     * through Model::resolveRouteBinding(), which excludes soft-deleted
     * rows (see UsersIndex::restoreUser() for the same reasoning).
     */
    public function restoreCategory(int $categoryId): void
    {
        $category = app(CategoryService::class)->findOrFail($categoryId, withTrashed: true);

        Gate::authorize('restore', $category);

        try {
            app(CategoryService::class)->restoreCategory($category);
            $this->dispatch('toast', type: 'success', message: 'Category restored.');
        } catch (ValidationException|LifecycleGuardException $exception) {
            $this->dispatch('toast', type: 'error', message: $this->firstErrorMessage($exception));
        }
    }

    public function forceDeleteCategory(int $categoryId): void
    {
        $category = app(CategoryService::class)->findOrFail($categoryId, withTrashed: true);

        Gate::authorize('forceDelete', $category);

        try {
            app(CategoryService::class)->forceDeleteCategory($category);
            $this->dispatch('toast', type: 'success', message: 'Category permanently deleted.');
        } catch (ValidationException|LifecycleGuardException $exception) {
            $this->dispatch('toast', type: 'error', message: $this->firstErrorMessage($exception));
        }
    }

    /**
     * A ValidationException carries a field=>messages error bag; a
     * LifecycleGuardException is a plain exception whose own message is
     * already the user-facing text (see docs/lifecycle-integrity.md's
     * "Guard failures").
     */
    protected function firstErrorMessage(ValidationException|LifecycleGuardException $exception): string
    {
        return $exception instanceof ValidationException
            ? (string) collect($exception->errors())->flatten()->first()
            : $exception->getMessage();
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
        return $this->categories()->getCollection()->pluck('id')->all();
    }

    public function bulkActivate(): void
    {
        $result = app(CategoryService::class)->bulkActivate($this->selected);
        $this->reportBulkResult('Activated', $result);
        $this->selected = [];
    }

    public function bulkDeactivate(): void
    {
        $result = app(CategoryService::class)->bulkDeactivate($this->selected);
        $this->reportBulkResult('Deactivated', $result);
        $this->selected = [];
    }

    public function bulkDelete(): void
    {
        $result = app(CategoryService::class)->bulkDelete($this->selected);
        $this->reportBulkResult('Deleted', $result);
        $this->selected = [];
    }

    /**
     * @param  array{affected: int, skipped: int}  $result
     */
    protected function reportBulkResult(string $verb, array $result): void
    {
        $noun = 'categor'.($result['affected'] === 1 ? 'y' : 'ies');

        $this->dispatch('toast', type: $result['skipped'] > 0 ? 'warning' : 'success', message: $result['skipped'] > 0
            ? "{$verb} {$result['affected']} {$noun}; {$result['skipped']} skipped."
            : "{$verb} {$result['affected']} {$noun}.");
    }

    /**
     * A badge color per lifecycle status - purely decorative, no
     * existing per-status color convention to reuse (see the Phase 1
     * plan). x-ui.badge only defines gray/green/red/brand.
     */
    public function statusColor(Category $category): string
    {
        return match ($category->lifecycle_status->value) {
            'active' => 'green',
            'pending_activation' => 'brand',
            'archived' => 'red',
            default => 'gray',
        };
    }

    /**
     * Re-renders this component (picking up the freshly imported rows)
     * once <x-import.button>'s modal finishes importing.
     */
    #[On('imported')]
    public function refreshAfterImport(): void {}

    public function export(ExportService $exportService): BinaryFileResponse
    {
        Gate::authorize('admin.categories.export');

        $query = app(CategoryService::class)->filteredQuery(
            search: $this->search,
            sort: 'sort_order_asc',
            filters: $this->filters(),
            trashed: $this->trashed,
        );

        return $exportService->export($query, Category::class, 'categories-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(): View
    {
        return view('livewire.admin.categories.categories-index', [
            'categories' => $this->categories(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    protected function categories(): LengthAwarePaginator
    {
        return app(CategoryService::class)->paginate(
            search: $this->search,
            perPage: $this->perPage,
            sort: 'sort_order_asc',
            filters: $this->filters(),
            trashed: $this->trashed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'lifecycle_status' => $this->status === '' ? null : $this->status,
        ];
    }
}
