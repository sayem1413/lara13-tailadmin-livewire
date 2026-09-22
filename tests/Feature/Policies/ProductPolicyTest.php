<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\Product;
use App\Models\User;

it('allows viewAny with admin.products.index', function () {
    Permission::findOrCreate('admin.products.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.products.index');

    expect($actor->can('viewAny', Product::class))->toBeTrue();
});

it('denies create without admin.products.create', function () {
    $actor = User::factory()->create();

    expect($actor->can('create', Product::class))->toBeFalse();
});

it('allows update/delete with their matching permissions', function () {
    Permission::findOrCreate('admin.products.edit');
    Permission::findOrCreate('admin.products.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.products.edit', 'admin.products.destroy']);

    $product = Product::factory()->create();

    expect($actor->can('update', $product))->toBeTrue()
        ->and($actor->can('delete', $product))->toBeTrue();
});

it('allows adjustStock with admin.inventory.adjust on a non-trashed product', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    expect($actor->can('adjustStock', Product::factory()->create()))->toBeTrue();
});

it('denies adjustStock on a trashed product even with the permission', function () {
    Permission::findOrCreate('admin.inventory.adjust');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.inventory.adjust');

    $product = Product::factory()->create();
    $product->delete();

    expect($actor->can('adjustStock', $product))->toBeFalse();
});

it('denies adjustStock without the permission', function () {
    $actor = User::factory()->create();

    expect($actor->can('adjustStock', Product::factory()->create()))->toBeFalse();
});

it('a Super Admin bypasses every product permission via Gate::before', function () {
    Role::findOrCreate('Super Admin');
    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    $product = Product::factory()->create();

    expect($actor->can('viewAny', Product::class))->toBeTrue()
        ->and($actor->can('update', $product))->toBeTrue()
        ->and($actor->can('adjustStock', $product))->toBeTrue();
});
