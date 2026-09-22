<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Lifecycle\LifecycleGuardException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('admin.categories.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('admin.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $this->categoryService->createCategory($request->validated());

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function show(Category $category): View
    {
        Gate::authorize('view', $category);

        return view('admin.categories.show', ['category' => $category]);
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        try {
            $this->categoryService->updateCategory($category, $request->validated());
        } catch (LifecycleGuardException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        try {
            $this->categoryService->deleteCategory($category);
        } catch (LifecycleGuardException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }

    public function restore(Category $category): RedirectResponse
    {
        Gate::authorize('restore', $category);

        try {
            $this->categoryService->restoreCategory($category);
        } catch (LifecycleGuardException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category restored successfully.');
    }

    public function forceDelete(Category $category): RedirectResponse
    {
        Gate::authorize('forceDelete', $category);

        try {
            $this->categoryService->forceDeleteCategory($category);
        } catch (LifecycleGuardException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category permanently deleted.');
    }
}
