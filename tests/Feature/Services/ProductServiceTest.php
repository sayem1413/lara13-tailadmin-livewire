<?php

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\CategoryProduct;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;
use App\Services\Product\ProductService;
use Illuminate\Validation\ValidationException;

it('generates a sku and slug when both are left blank', function () {
    $product = app(ProductService::class)->createProduct(['name' => 'Basmati Rice 5kg', 'price' => 750]);

    expect($product->sku)->not->toBeEmpty()
        ->and($product->slug)->toBe('basmati-rice-5kg');
});

it('blocks creating a product with a sku already in use', function () {
    Product::factory()->create(['sku' => 'RICE-001']);

    expect(fn () => app(ProductService::class)->createProduct(['name' => 'Duplicate', 'sku' => 'RICE-001', 'price' => 10]))
        ->toThrow(ValidationException::class);
});

it('allows reusing the sku of a trashed product', function () {
    $trashed = Product::factory()->create(['sku' => 'RICE-002']);
    $trashed->delete();

    $product = app(ProductService::class)->createProduct(['name' => 'Fresh Rice', 'sku' => 'RICE-002', 'price' => 10]);

    expect($product->exists)->toBeTrue();
});

it('routes initial stock through StockService so a movement is recorded', function () {
    $product = app(ProductService::class)->createProduct([
        'name' => 'Cooking Oil 1L', 'price' => 200, 'initial_stock' => 30,
    ]);

    expect($product->stock_quantity)->toBe(30)
        ->and($product->stockMovements()->count())->toBe(1)
        ->and($product->stockMovements()->first()->type)->toBe(StockMovementType::Initial);
});

it('cannot change stock_quantity through updateProduct', function () {
    $product = Product::factory()->withStock(10)->create();

    app(ProductService::class)->updateProduct($product->fresh(), ['stock_quantity' => 9999, 'price' => 500]);

    expect($product->fresh()->stock_quantity)->toBe(10)
        ->and((float) $product->fresh()->price)->toBe(500.0);
});

it('syncCategories attaches, then soft-removes, then restores rather than duplicating the pivot row', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    app(ProductService::class)->syncCategories($product, [$category->id]);
    expect($product->categories()->count())->toBe(1);

    app(ProductService::class)->syncCategories($product, []);
    expect($product->fresh()->categories()->count())->toBe(0)
        ->and(CategoryProduct::withTrashed()->where('product_id', $product->id)->count())->toBe(1);

    app(ProductService::class)->syncCategories($product, [$category->id]);
    expect($product->fresh()->categories()->count())->toBe(1)
        ->and(CategoryProduct::withTrashed()->where('product_id', $product->id)->count())->toBe(1);
});

it('rejects assigning an inactive category', function () {
    $product = Product::factory()->create();
    $inactive = Category::factory()->inactive()->create();

    expect(fn () => app(ProductService::class)->syncCategories($product, [$inactive->id]))
        ->toThrow(ValidationException::class);
});

it('rejects assigning a trashed category', function () {
    $product = Product::factory()->create();
    $trashed = Category::factory()->trashed()->create();

    expect(fn () => app(ProductService::class)->syncCategories($product, [$trashed->id]))
        ->toThrow(ValidationException::class);
});

it('blocks permanently deleting a product that has stock history', function () {
    $product = Product::factory()->withStock(10)->create();
    $product->delete();

    expect(fn () => app(ProductService::class)->forceDeleteProduct($product->fresh()))
        ->toThrow(ValidationException::class);

    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('allows permanently deleting a product with no stock history', function () {
    $product = Product::factory()->create();
    $product->delete();

    app(ProductService::class)->forceDeleteProduct($product->fresh());

    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

it('bulk delete soft-deletes every authorized row and reports zero skipped', function () {
    Permission::findOrCreate('admin.products.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.edit');
    $this->actingAs($actor);

    $products = Product::factory()->count(2)->create();

    $result = app(ProductService::class)->bulkDelete($products->pluck('id')->all());

    expect($result)->toBe(['affected' => 2, 'skipped' => 0])
        ->and($products->fresh()->every(fn (Product $product) => $product->trashed()))->toBeTrue();
});

it('bulk actions skip every row for an actor with no admin.products.edit permission at all', function () {
    $this->actingAs(User::factory()->create());

    $products = Product::factory()->count(2)->create();

    $result = app(ProductService::class)->bulkDeactivate($products->pluck('id')->all());

    expect($result)->toBe(['affected' => 0, 'skipped' => 2]);
});
