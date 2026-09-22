<?php

namespace App\Livewire\Admin\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Export\ExportService;
use App\Services\Inventory\StockService;
use App\Services\Product\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryIndex extends Component
{
    #[Url]
    public string $search = '';

    /** 'low' shows only products at or under their threshold. */
    #[Url]
    public string $stock = '';

    public int $perPage = 10;

    public bool $showAdjustModal = false;

    public ?int $adjustingProductId = null;

    public string $adjustingProductName = '';

    public string $type = 'purchase';

    public ?int $quantity_change = null;

    public ?string $reason = null;

    public function updatingSearch(): void
    {
        $this->perPage = 10;
    }

    public function updatingStock(): void
    {
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function openAdjustModal(Product $product): void
    {
        Gate::authorize('adjustStock', $product);

        $this->adjustingProductId = $product->id;
        $this->adjustingProductName = $product->name;
        $this->type = StockMovementType::Purchase->value;
        $this->quantity_change = null;
        $this->reason = null;
        $this->resetErrorBag();
        $this->showAdjustModal = true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', array_column(StockMovementType::cases(), 'value'))],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function adjust(): void
    {
        $product = app(ProductService::class)->findOrFail((int) $this->adjustingProductId);

        Gate::authorize('adjustStock', $product);

        $validated = $this->validate();

        try {
            app(StockService::class)->adjust(
                $product,
                (int) $validated['quantity_change'],
                StockMovementType::from($validated['type']),
                $validated['reason'],
            );

            $this->showAdjustModal = false;
            $this->dispatch('toast', type: 'success', message: 'Stock adjusted successfully.');
        } catch (ValidationException $exception) {
            $this->addError('quantity_change', collect($exception->errors())->flatten()->first());
        }
    }

    public function export(ExportService $exportService): BinaryFileResponse
    {
        Gate::authorize('admin.inventory.export');

        $query = app(ProductService::class)->filteredQuery(
            search: $this->search,
            sort: 'name_asc',
            filters: ['stock' => $this->stock === '' ? null : $this->stock],
        );

        return $exportService->export($query, Product::class, 'inventory-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(): View
    {
        return view('livewire.admin.inventory.inventory-index', [
            'products' => $this->products(),
            'recentMovements' => StockMovement::query()->with(['product', 'creator'])->latest()->limit(10)->get(),
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
            filters: [
                'track_inventory' => true,
                'stock' => $this->stock === '' ? null : $this->stock,
            ],
        );
    }
}
