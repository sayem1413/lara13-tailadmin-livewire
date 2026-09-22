<?php

use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.products.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.products.index permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.products.index'))
        ->assertForbidden();
});

it('creates a product with the admin.products.create permission', function () {
    Permission::findOrCreate('admin.products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.create');

    $this->actingAs($actor)
        ->post(route('admin.products.store'), [
            'name' => 'Basmati Rice 5kg',
            'unit' => 'kg',
            'price' => 750,
        ])
        ->assertRedirect(route('admin.products.index'));

    expect(Product::where('name', 'Basmati Rice 5kg')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Permission::findOrCreate('admin.products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.create');

    $this->actingAs($actor)
        ->post(route('admin.products.store'), [])
        ->assertSessionHasErrors(['name', 'unit', 'price']);
});

it('rejects a duplicate sku on create', function () {
    Permission::findOrCreate('admin.products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.create');

    Product::factory()->create(['sku' => 'RICE-001']);

    $this->actingAs($actor)
        ->post(route('admin.products.store'), [
            'name' => 'Another Rice', 'unit' => 'kg', 'price' => 500, 'sku' => 'RICE-001',
        ])
        ->assertSessionHasErrors('sku');
});

it('updates a product with the admin.products.edit permission', function () {
    Permission::findOrCreate('admin.products.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.edit');

    $product = Product::factory()->create(['price' => 100]);

    $this->actingAs($actor)
        ->put(route('admin.products.update', $product), [
            'name' => $product->name, 'unit' => $product->unit->value, 'price' => 200,
        ])
        ->assertRedirect(route('admin.products.index'));

    expect((float) $product->fresh()->price)->toBe(200.0);
});

it('shows a product to an actor with the admin.products.index permission', function () {
    Permission::findOrCreate('admin.products.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.index');

    $this->actingAs($actor)
        ->get(route('admin.products.show', Product::factory()->create()))
        ->assertOk();
});

it('deletes a product with the admin.products.destroy permission', function () {
    Permission::findOrCreate('admin.products.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.destroy');

    $product = Product::factory()->create();

    $this->actingAs($actor)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'));

    $this->assertSoftDeleted($product);
});

it('restores a soft-deleted product with the admin.products.restore permission', function () {
    Permission::findOrCreate('admin.products.restore');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.restore');

    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($actor)
        ->put(route('admin.products.restore', $product))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->trashed())->toBeFalse();
});

it('refuses to permanently delete a product with stock history, surfacing a validation error', function () {
    Permission::findOrCreate('admin.products.force-delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.force-delete');

    $product = Product::factory()->withStock(10)->create();
    $product->delete();

    $this->actingAs($actor)
        ->delete(route('admin.products.force-delete', $product))
        ->assertSessionHasErrors('product');

    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('permanently deletes a product with no stock history and the admin.products.force-delete permission', function () {
    Permission::findOrCreate('admin.products.force-delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.force-delete');

    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($actor)
        ->delete(route('admin.products.force-delete', $product))
        ->assertRedirect(route('admin.products.index'));

    expect(Product::withTrashed()->find($product->id))->toBeNull();
});
