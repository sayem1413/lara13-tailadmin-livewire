<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('allows viewing users with the users.view permission', function () {
    Permission::findOrCreate('users.view');
    $user = User::factory()->create();
    $user->givePermissionTo('users.view');

    expect($user->can('viewAny', User::class))->toBeTrue();
});

it('denies viewing users without the users.view permission', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeFalse();
});

it('denies editing a Super Admin unless the actor is also a Super Admin', function () {
    Permission::findOrCreate('users.update');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

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

it('allows editing a regular user with the users.update permission', function () {
    Permission::findOrCreate('users.update');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $target = User::factory()->create();

    expect($actor->can('update', $target))->toBeTrue();
});

it('denies deleting your own account', function () {
    Permission::findOrCreate('users.delete');

    $user = User::factory()->create();
    $user->givePermissionTo('users.delete');

    expect($user->can('delete', $user))->toBeFalse();
});

it('denies deleting a Super Admin unless the actor is also a Super Admin', function () {
    Permission::findOrCreate('users.delete');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.delete');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($actor->can('delete', $superAdmin))->toBeFalse();
});
