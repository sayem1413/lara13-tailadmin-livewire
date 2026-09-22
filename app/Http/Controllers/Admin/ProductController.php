<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('admin.products.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $this->productService->createProduct($request->validated());

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        $product->load('categories');

        return view('admin.products.show', [
            'product' => $product,
            'stockMovements' => $product->stockMovements()->with('creator')->limit(20)->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('admin.products.edit', ['product' => $product]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $this->productService->updateProduct($product, $request->validated());

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $this->productService->deleteProduct($product);

        return redirect()->route('admin.products.index')->with('success', 'Product deleted successfully.');
    }

    public function restore(Product $product): RedirectResponse
    {
        Gate::authorize('restore', $product);

        $this->productService->restoreProduct($product);

        return redirect()->route('admin.products.index')->with('success', 'Product restored successfully.');
    }

    public function forceDelete(Product $product): RedirectResponse
    {
        Gate::authorize('forceDelete', $product);

        $this->productService->forceDeleteProduct($product);

        return redirect()->route('admin.products.index')->with('success', 'Product permanently deleted.');
    }
}
