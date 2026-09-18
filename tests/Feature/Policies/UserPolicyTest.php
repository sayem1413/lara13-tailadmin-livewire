<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;

it('allows viewing users with the admin.users.index permission', function () {
    Permission::findOrCreate('admin.users.index');
    $user = User::factory()->create();
    $user->givePermissionTo('admin.users.index');

    expect($user->can('viewAny', User::class))->toBeTrue();
});

it('denies viewing users without the admin.users.index permission', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeFalse();
});

it('denies editing a Super Admin unless the actor is also a Super Admin', function () {
    Permission::findOrCreate('admin.users.edit');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($actor->can('update', $superAdmin))->toBeFalse();
});

it('allows a Super Admin to edit another Super Admin', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($actor->can('update', $superAdmin))->toBeTrue();
});

it('allows editing a regular user with the admin.users.edit permission', function () {
    Permission::findOrCreate('admin.users.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create();

    expect($actor->can('update', $target))->toBeTrue();
});

it('denies deleting your own account', function () {
    Permission::findOrCreate('admin.users.destroy');

    $user = User::factory()->create();
    $user->givePermissionTo('admin.users.destroy');

    expect($user->can('delete', $user))->toBeFalse();
});

it('denies deleting a Super Admin unless the actor is also a Super Admin', function () {
    Permission::findOrCreate('admin.users.destroy');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.destroy');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($actor->can('delete', $superAdmin))->toBeFalse();
});
