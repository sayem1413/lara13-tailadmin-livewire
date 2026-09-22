<?php

use App\Livewire\Admin\Inventory\InventoryIndex;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.inventory.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.inventory.index permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.inventory.index'))
        ->assertForbidden();
});

it('opens the adjust modal and adjusts stock', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->create();

    Livewire::actingAs($actor)
        ->test(InventoryIndex::class)
        ->call('openAdjustModal', $product->id)
        ->assertSet('showAdjustModal', true)
        ->set('quantity_change', 20)
        ->call('adjust')
        ->assertSet('showAdjustModal', false)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect($product->fresh()->stock_quantity)->toBe(20);
});

it('validates the quantity change is not zero', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->create();

    Livewire::actingAs($actor)
        ->test(InventoryIndex::class)
        ->call('openAdjustModal', $product->id)
        ->set('quantity_change', 0)
        ->call('adjust')
        ->assertHasErrors('quantity_change');
});

it('surfaces an inline error instead of a 500 when an adjustment would take stock negative', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->withStock(5)->create();

    Livewire::actingAs($actor)
        ->test(InventoryIndex::class)
        ->call('openAdjustModal', $product->fresh()->id)
        ->set('quantity_change', -10)
        ->call('adjust')
        ->assertHasErrors('quantity_change');

    expect($product->fresh()->stock_quantity)->toBe(5);
});

it('forbids adjusting stock without the admin.inventory.adjust permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(InventoryIndex::class)
        ->call('openAdjustModal', Product::factory()->create()->id)
        ->assertForbidden();
});
