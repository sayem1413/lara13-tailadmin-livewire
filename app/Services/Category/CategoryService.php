<?php

namespace App\Services\Category;

use App\Enums\DeletionStrategy;
use App\Models\Category;
use App\Repositories\Interfaces\Category\CategoryRepositoryInterface;
use App\Services\Lifecycle\LifecycleIntegrityService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    /**
     * Categories can be nested at most this many levels deep - keeps the
     * parent picker and breadcrumbs sane. Promote to a Setting if it
     * ever needs to vary per deployment.
     */
    protected const MAX_DEPTH = 3;

    public function __construct(
        protected CategoryRepositoryInterface $categoryRepository,
        protected LifecycleIntegrityService $lifecycle,
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
        return $this->categoryRepository->paginate($search, $perPage, $sort, $filters, $trashed);
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
        return $this->categoryRepository->filteredQuery($search, $sort, $filters, $trashed);
    }

    public function findOrFail(int $id, bool $withTrashed = false): Category
    {
        return $this->categoryRepository->findOrFail($id, $withTrashed);
    }

    /**
     * Ids of $category's own subtree (including itself) - for a "include
     * subcategories" product filter (see ProductsIndex).
     *
     * @return array<int, int|string>
     */
    public function descendantIds(Category $category): array
    {
        return $this->categoryRepository->descendantIds($category);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data): Category
    {
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : Str::slug($data['name']);

        $this->guardMaxDepth($data['parent_id'] ?? null);

        return DB::transaction(fn () => $this->categoryRepository->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        if (array_key_exists('slug', $data)) {
            $data['slug'] = filled($data['slug']) ? $data['slug'] : Str::slug($data['name'] ?? $category->name);
        }

        $newParentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id;

        if ($newParentId !== $category->parent_id) {
            $this->guardMaxDepth($newParentId);
        }

        unset($data['parent_id']);

        return DB::transaction(function () use ($category, $data, $newParentId): Category {
            $category = $this->categoryRepository->update($category, $data);

            if ($newParentId !== $category->parent_id) {
                $newParent = $newParentId !== null ? $this->categoryRepository->findOrFail($newParentId) : null;
                $this->lifecycle->reparent($category, $newParent);
                $category->refresh();
            }

            return $category;
        });
    }

    public function activateCategory(Category $category): void
    {
        $this->lifecycle->activate($category);
    }

    public function deactivateCategory(Category $category): void
    {
        $this->lifecycle->deactivate($category);
    }

    /**
     * Blocks deleting a category that still has products directly
     * assigned to it - a plain delete() would either leave those
     * products dangling or (worse) silently cascade in a way nobody
     * asked for, since Category declares no `shared` lifecycle rule for
     * products() (see Category::lifecycleRules()). Children are handled
     * by deleteNode()'s own strategy instead of a separate guard here.
     */
    public function deleteCategory(Category $category, ?DeletionStrategy $strategy = null): void
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            throw ValidationException::withMessages([
                'category' => "\"{$category->name}\" still has {$productCount} product(s) and can't be deleted.",
            ]);
        }

        $this->lifecycle->deleteNode($category, $strategy);
    }

    /**
     * The Lifecycle module's cascadeRestored() only restores a parent's
     * OWN children automatically (see docs/lifecycle-integrity.md) - a
     * category whose parent is still trashed must be restored from the
     * parent down, not the other way around, or it would come back
     * dangling under a row that doesn't exist yet.
     */
    public function restoreCategory(Category $category): Category
    {
        if ($category->parent_id !== null) {
            $parent = Category::withTrashed()->find($category->parent_id);

            if ($parent?->trashed()) {
                throw ValidationException::withMessages([
                    'category' => 'Restore the parent category first.',
                ]);
            }
        }

        $this->lifecycle->restore($category);

        return $category->refresh();
    }

    /**
     * Permanently removes the category row. Requires no remaining
     * children (even trashed ones - a force-delete cascade never
     * reaches further than the caller intends) and detaches every
     * product pivot row first, since the pivot's FKs are restrict.
     */
    public function forceDeleteCategory(Category $category): void
    {
        $childCount = Category::withTrashed()->where('parent_id', $category->id)->count();

        if ($childCount > 0) {
            throw ValidationException::withMessages([
                'category' => "\"{$category->name}\" still has {$childCount} subcategor".($childCount === 1 ? 'y' : 'ies')." (including trashed) and can't be permanently deleted.",
            ]);
        }

        $this->categoryRepository->detachAllProducts($category);

        $this->lifecycle->forceDelete($category);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    public function bulkActivate(array $ids): array
    {
        return $this->bulkApply($ids, fn (Category $category) => $this->activateCategory($category));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    public function bulkDeactivate(array $ids): array
    {
        return $this->bulkApply($ids, fn (Category $category) => $this->deactivateCategory($category));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    public function bulkDelete(array $ids): array
    {
        return $this->bulkApply($ids, fn (Category $category) => $this->deleteCategory($category));
    }

    /**
     * Bulk actions bypass the per-row `@can` checks in the Blade table
     * (see UserService::authorizedIds()) and can also legitimately fail
     * per row (a category with products, one deeper down the batch than
     * expected) - both are silently skipped rather than aborting the
     * whole batch, and reported back so the UI can say "3 of 5 done".
     *
     * @param  array<int, int>  $ids
     * @return array{affected: int, skipped: int}
     */
    protected function bulkApply(array $ids, Closure $action): array
    {
        $affected = 0;
        $skipped = 0;

        foreach (Category::query()->whereKey($ids)->get() as $category) {
            if (! Gate::allows('update', $category)) {
                $skipped++;

                continue;
            }

            try {
                $action($category);
                $affected++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * Indented "Beverages › Soft Drinks" labels for the parent picker,
     * excluding $excluding and its own descendants so the UI can't even
     * offer a selection that would form a cycle.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    public function selectOptions(?Category $excluding = null): Collection
    {
        $excludedIds = $excluding ? $this->categoryRepository->descendantIds($excluding) : [];

        $categories = $this->categoryRepository->all()->whereNotIn('id', $excludedIds);

        $byParent = $categories->groupBy('parent_id');

        $options = collect();

        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent, $options): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                $options->push([
                    'id' => $category->id,
                    'label' => str_repeat('— ', $depth).$category->name,
                ]);

                $walk($category->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $options;
    }

    /**
     * @throws ValidationException
     */
    protected function guardMaxDepth(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $depth = 1;
        $currentId = $parentId;

        while ($currentId !== null) {
            $depth++;

            if ($depth > self::MAX_DEPTH) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Categories can be nested at most '.self::MAX_DEPTH.' levels deep.',
                ]);
            }

            $currentId = Category::query()->whereKey($currentId)->value('parent_id');
        }
    }
}
