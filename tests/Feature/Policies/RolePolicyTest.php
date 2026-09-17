<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('denies updating the Super Admin role even with roles.update permission', function () {
    Role::findOrCreate('Super Admin');
    Permission::findOrCreate('roles.update');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.update');

    expect($actor->can('update', Role::findByName('Super Admin')))->toBeFalse();
});

it('denies deleting the Super Admin role even with roles.delete permission', function () {
    Role::findOrCreate('Super Admin');
    Permission::findOrCreate('roles.delete');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.delete');

    expect($actor->can('delete', Role::findByName('Super Admin')))->toBeFalse();
});

it('allows updating a non-protected role with the roles.update permission', function () {
    Permission::findOrCreate('roles.update');
    $role = Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.update');

    expect($actor->can('update', $role))->toBeTrue();
});

it('denies updating a role without the roles.update permission', function () {
    $role = Role::findOrCreate('Editor');
    $actor = User::factory()->create();

    expect($actor->can('update', $role))->toBeFalse();
});
