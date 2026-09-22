<?php

use App\Livewire\Admin\Products\ProductForm;
use App\Models\Category;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('creates a product and auto-generates the slug from the name', function () {
    Permission::findOrCreate('admin.products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.create');

    Livewire::actingAs($actor)
        ->test(ProductForm::class)
        ->set('name', 'Basmati Rice 5kg')
        ->set('price', 750)
        ->call('save')
        ->assertRedirect(route('admin.products.index'));

    expect(Product::where('name', 'Basmati Rice 5kg')->first()?->slug)->toBe('basmati-rice-5kg');
});

it('creates a product with categories and initial stock', function () {
    Permission::findOrCreate('admin.products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.create');

    $category = Category::factory()->create();

    Livewire::actingAs($actor)
        ->test(ProductForm::class)
        ->set('name', 'Cooking Oil')
        ->set('price', 200)
        ->set('selectedCategories', [$category->id])
        ->set('initial_stock', 30)
        ->call('save')
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('name', 'Cooking Oil')->firstOrFail();

    expect($product->stock_quantity)->toBe(30)
        ->and($product->categories()->count())->toBe(1);
});

it('validates required fields', function () {
    Permission::findOrCreate('admin.products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.create');

    Livewire::actingAs($actor)
        ->test(ProductForm::class)
        ->call('save')
        ->assertHasErrors(['name', 'price']);
});

it('loads an existing product for editing', function () {
    Permission::findOrCreate('admin.products.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.edit');

    $product = Product::factory()->create(['name' => 'Cooking Oil']);

    Livewire::actingAs($actor)
        ->test(ProductForm::class, ['product' => $product])
        ->assertSet('name', 'Cooking Oil');
});

it('cannot set stock_quantity directly through the update form', function () {
    Permission::findOrCreate('admin.products.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.edit');

    $product = Product::factory()->withStock(10)->create();

    Livewire::actingAs($actor)
        ->test(ProductForm::class, ['product' => $product->fresh()])
        ->set('price', 999)
        ->call('save')
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->stock_quantity)->toBe(10);
});

it('forbids creating a product without the admin.products.create permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(ProductForm::class)
        ->assertForbidden();
});
