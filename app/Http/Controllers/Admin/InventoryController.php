<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Services\Inventory\StockService;
use App\Services\Product\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function __construct(
        protected StockService $stockService,
        protected ProductService $productService,
    ) {}

    public function index(): View
    {
        Gate::authorize('admin.inventory.index');

        return view('admin.inventory.index');
    }

    /**
     * A real, named route (not a Livewire-only action) so
     * SyncPermissionsFromRoutes discovers admin.inventory.adjust
     * automatically - see routes/web.php.
     */
    public function adjust(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        Gate::authorize('admin.inventory.adjust');

        $product = $this->productService->findOrFail($request->validated('product_id'));

        Gate::authorize('adjustStock', $product);

        $this->stockService->adjust(
            $product,
            (int) $request->validated('quantity_change'),
            StockMovementType::from($request->validated('type')),
            $request->validated('reason'),
        );

        return redirect()->route('admin.inventory.index')->with('success', 'Stock adjusted successfully.');
    }
}
