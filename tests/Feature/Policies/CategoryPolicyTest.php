<?php

use App\Models\Category;
use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;

it('allows viewAny with admin.categories.index', function () {
    Permission::findOrCreate('admin.categories.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.index');

    expect($actor->can('viewAny', Category::class))->toBeTrue();
});

it('denies viewAny without the permission', function () {
    $actor = User::factory()->create();

    expect($actor->can('viewAny', Category::class))->toBeFalse();
});

it('allows create with admin.categories.create', function () {
    Permission::findOrCreate('admin.categories.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.create');

    expect($actor->can('create', Category::class))->toBeTrue();
});

it('allows update with admin.categories.edit', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    expect($actor->can('update', Category::factory()->create()))->toBeTrue();
});

it('denies delete without admin.categories.destroy', function () {
    $actor = User::factory()->create();

    expect($actor->can('delete', Category::factory()->create()))->toBeFalse();
});

it('allows restore/forceDelete with their matching permissions', function () {
    Permission::findOrCreate('admin.categories.restore');
    Permission::findOrCreate('admin.categories.force-delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.categories.restore', 'admin.categories.force-delete']);

    $category = Category::factory()->create();

    expect($actor->can('restore', $category))->toBeTrue()
        ->and($actor->can('forceDelete', $category))->toBeTrue();
});

it('a Super Admin bypasses every category permission via Gate::before', function () {
    Role::findOrCreate('Super Admin');
    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    $category = Category::factory()->create();

    expect($actor->can('viewAny', Category::class))->toBeTrue()
        ->and($actor->can('create', Category::class))->toBeTrue()
        ->and($actor->can('update', $category))->toBeTrue()
        ->and($actor->can('delete', $category))->toBeTrue();
});
