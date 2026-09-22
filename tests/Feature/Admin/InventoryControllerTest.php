<?php

use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.inventory.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.inventory.index permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.inventory.index'))
        ->assertForbidden();
});

it('forbids adjusting stock without the admin.inventory.adjust permission', function () {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.inventory.adjust'), [
            'product_id' => $product->id, 'type' => 'purchase', 'quantity_change' => 10,
        ])
        ->assertForbidden();

    expect($product->fresh()->stock_quantity)->toBe(0);
});

it('adjusts stock with the admin.inventory.adjust permission', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->create();

    $this->actingAs($actor)
        ->post(route('admin.inventory.adjust'), [
            'product_id' => $product->id, 'type' => 'purchase', 'quantity_change' => 25, 'reason' => 'New delivery',
        ])
        ->assertRedirect(route('admin.inventory.index'));

    expect($product->fresh()->stock_quantity)->toBe(25);
});

it('validates that the quantity change is not zero', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->create();

    $this->actingAs($actor)
        ->post(route('admin.inventory.adjust'), [
            'product_id' => $product->id, 'type' => 'adjustment', 'quantity_change' => 0,
        ])
        ->assertSessionHasErrors('quantity_change');
});

it('blocks an adjustment that would take stock negative without the allow_negative_stock setting', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->withStock(5)->create();

    $this->actingAs($actor)
        ->post(route('admin.inventory.adjust'), [
            'product_id' => $product->id, 'type' => 'sale', 'quantity_change' => -10,
        ])
        ->assertSessionHasErrors('quantity_change');

    expect($product->fresh()->stock_quantity)->toBe(5);
});
