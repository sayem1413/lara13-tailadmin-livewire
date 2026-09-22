<?php

use App\Enums\StockMovementType;
use App\Livewire\Admin\Products\ProductsIndex;
use App\Models\Category;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\StockService;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.products.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.products.index permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.products.index'))
        ->assertForbidden();
});

it('lists products matching a search term', function () {
    Permission::findOrCreate('admin.products.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.index');

    Product::factory()->create(['name' => 'Basmati Rice']);
    Product::factory()->create(['name' => 'Cooking Oil']);

    Livewire::actingAs($actor)
        ->test(ProductsIndex::class)
        ->set('search', 'Basmati')
        ->assertSee('Basmati Rice')
        ->assertDontSee('Cooking Oil');
});

it('filters products by category, including subcategories when requested', function () {
    Permission::findOrCreate('admin.products.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.index');

    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();

    $direct = Product::factory()->create(['name' => 'Direct Product']);
    $direct->categories()->attach($parent);

    $nested = Product::factory()->create(['name' => 'Nested Product']);
    $nested->categories()->attach($child);

    Livewire::actingAs($actor)
        ->test(ProductsIndex::class)
        ->set('category', (string) $parent->id)
        ->assertSee('Direct Product')
        ->assertDontSee('Nested Product')
        ->set('includeSubcategories', true)
        ->assertSee('Direct Product')
        ->assertSee('Nested Product');
});

it('filters products by low stock', function () {
    Permission::findOrCreate('admin.products.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.index');

    $healthy = Product::factory()->create(['name' => 'Healthy Stock', 'low_stock_threshold' => 5])->fresh();
    app(StockService::class)->adjust($healthy, 100, StockMovementType::Purchase);

    $low = Product::factory()->create(['name' => 'Low Stock Item', 'low_stock_threshold' => 5])->fresh();
    app(StockService::class)->adjust($low, 3, StockMovementType::Purchase);

    Livewire::actingAs($actor)
        ->test(ProductsIndex::class)
        ->set('stock', 'low')
        ->assertSee('Low Stock Item')
        ->assertDontSee('Healthy Stock');
});

it('toggles a product active/inactive', function () {
    Permission::findOrCreate('admin.products.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.edit');

    $product = Product::factory()->create();

    Livewire::actingAs($actor)
        ->test(ProductsIndex::class)
        ->call('toggleActive', $product->id);

    expect($product->fresh()->isLifecycleActive())->toBeFalse();
});

it('bulk deletes selected products and reports the result', function () {
    Permission::findOrCreate('admin.products.edit');
    Permission::findOrCreate('admin.products.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.products.edit', 'admin.products.destroy']);

    $products = Product::factory()->count(2)->create();

    Livewire::actingAs($actor)
        ->test(ProductsIndex::class)
        ->set('selected', $products->pluck('id')->all())
        ->call('bulkDelete')
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect($products->fresh()->every(fn (Product $product) => $product->trashed()))->toBeTrue();
});

it('forbids toggling a product without the admin.products.edit permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(ProductsIndex::class)
        ->call('toggleActive', Product::factory()->create()->id)
        ->assertForbidden();
});
