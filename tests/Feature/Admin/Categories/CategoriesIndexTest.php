<?php

use App\Livewire\Admin\Categories\CategoriesIndex;
use App\Models\Category;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;
use App\Services\Lifecycle\LifecycleIntegrityService;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.categories.index permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('lists categories matching a search term', function () {
    Permission::findOrCreate('admin.categories.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.index');

    Category::factory()->create(['name' => 'Beverages']);
    Category::factory()->create(['name' => 'Snacks']);

    Livewire::actingAs($actor)
        ->test(CategoriesIndex::class)
        ->set('search', 'Beverages')
        ->assertSee('Beverages')
        ->assertDontSee('Snacks');
});

it('toggles a category active/inactive', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    $category = Category::factory()->create();

    Livewire::actingAs($actor)
        ->test(CategoriesIndex::class)
        ->call('toggleActive', $category->id);

    expect($category->fresh()->isLifecycleActive())->toBeFalse();
});

it('surfaces a toast error instead of a 500 when deleting a category that still has products', function () {
    Permission::findOrCreate('admin.categories.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.destroy');

    $category = Category::factory()->create();
    $category->products()->attach(Product::factory()->create());

    Livewire::actingAs($actor)
        ->test(CategoriesIndex::class)
        ->call('delete', $category->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'error';
        });

    expect($category->fresh()->trashed())->toBeFalse();
});

it('surfaces a toast error instead of a 500 when deleting a category that still has children', function () {
    Permission::findOrCreate('admin.categories.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.destroy');

    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create();

    Livewire::actingAs($actor)
        ->test(CategoriesIndex::class)
        ->call('delete', $root->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'error' && str_contains($params['message'], 'still has children');
        });

    expect($root->fresh()->trashed())->toBeFalse();
});

it('surfaces a toast error instead of a 500 when activating a category directly under an inactive parent', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    $root = Category::factory()->create();
    app(LifecycleIntegrityService::class)->deactivate($root);
    $child = Category::factory()->childOf($root->refresh())->create();
    $child->deactivate();

    Livewire::actingAs($actor)
        ->test(CategoriesIndex::class)
        ->call('toggleActive', $child->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'error' && str_contains($params['message'], 'not active');
        });

    expect($child->fresh()->isLifecycleActive())->toBeFalse();
});

it('bulk deletes selected categories and reports the result', function () {
    Permission::findOrCreate('admin.categories.edit');
    Permission::findOrCreate('admin.categories.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.categories.edit', 'admin.categories.destroy']);

    $categories = Category::factory()->count(2)->create();

    Livewire::actingAs($actor)
        ->test(CategoriesIndex::class)
        ->set('selected', $categories->pluck('id')->all())
        ->call('bulkDelete')
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect($categories->fresh()->every(fn (Category $category) => $category->trashed()))->toBeTrue();
});

it('forbids toggling a category without the admin.categories.edit permission', function () {
    $category = Category::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CategoriesIndex::class)
        ->call('toggleActive', $category->id)
        ->assertForbidden();
});
