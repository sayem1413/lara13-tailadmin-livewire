<?php

namespace App\Livewire\Admin\Products;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Services\Category\CategoryService;
use App\Services\Product\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProductForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $productId = null;

    public ?string $sku = null;

    public ?string $barcode = null;

    public string $name = '';

    public string $slug = '';

    public string $unit = 'pc';

    public ?string $short_description = null;

    public ?string $description = null;

    public ?float $price = null;

    public ?float $compare_at_price = null;

    public ?float $cost_price = null;

    public ?float $tax_rate = null;

    public bool $track_inventory = true;

    public ?int $initial_stock = null;

    public ?int $low_stock_threshold = null;

    public ?float $weight = null;

    /** @var array<int, int> */
    public array $selectedCategories = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $newImages = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            Gate::authorize('update', $product);

            $this->productId = $product->id;
            $this->sku = $product->sku;
            $this->barcode = $product->barcode;
            $this->name = $product->name;
            $this->slug = $product->slug;
            $this->unit = $product->unit->value;
            $this->short_description = $product->short_description;
            $this->description = $product->description;
            $this->price = (float) $product->price;
            $this->compare_at_price = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
            $this->cost_price = $product->cost_price !== null ? (float) $product->cost_price : null;
            $this->tax_rate = $product->tax_rate !== null ? (float) $product->tax_rate : null;
            $this->track_inventory = $product->track_inventory;
            $this->low_stock_threshold = $product->low_stock_threshold;
            $this->weight = $product->weight !== null ? (float) $product->weight : null;
            $this->selectedCategories = $product->categories->pluck('id')->all();
        } else {
            Gate::authorize('create', Product::class);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->productId)->withoutTrashed()],
            'barcode' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($this->productId)->withoutTrashed()],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'gte:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'track_inventory' => ['boolean'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'selectedCategories' => ['array'],
            'selectedCategories.*' => ['integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'newImages.*' => ['file', 'max:5120', 'extensions:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }

    public function updatedName(): void
    {
        if ($this->productId === null && $this->slug === '') {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $product = $this->productId ? app(ProductService::class)->findOrFail($this->productId) : null;

        Gate::authorize($this->productId ? 'update' : 'create', $product ?? Product::class);

        $validated = $this->validate();
        $validated['categories'] = $validated['selectedCategories'];
        unset($validated['selectedCategories']);

        if ($product) {
            $product = app(ProductService::class)->updateProduct($product, $validated);
        } else {
            $product = app(ProductService::class)->createProduct($validated);
        }

        if ($this->newImages !== []) {
            app(ProductService::class)->addImages($product, $this->newImages);
        }

        session()->flash('success', $this->productId ? 'Product updated.' : 'Product created.');

        $this->redirect(route('admin.products.index'));
    }

    public function removeImage(int $mediaId): void
    {
        if (! $this->productId) {
            return;
        }

        Gate::authorize('update', Product::class);

        app(ProductService::class)->removeImage(app(ProductService::class)->findOrFail($this->productId), $mediaId);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    protected function categoryOptions(): Collection
    {
        return app(CategoryService::class)->selectOptions();
    }

    public function render(): View
    {
        return view('livewire.admin.products.product-form', [
            'categoryOptions' => $this->categoryOptions(),
            'existingImages' => $this->productId ? Product::find($this->productId)?->getMedia('images') : collect(),
        ]);
    }
}
