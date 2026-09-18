<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;

it('denies updating the Super Admin role even with admin.roles.edit permission', function () {
    Role::findOrCreate('Super Admin');
    Permission::findOrCreate('admin.roles.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    expect($actor->can('update', Role::findByName('Super Admin')))->toBeFalse();
});

it('denies deleting the Super Admin role even with admin.roles.destroy permission', function () {
    Role::findOrCreate('Super Admin');
    Permission::findOrCreate('admin.roles.destroy');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.destroy');

    expect($actor->can('delete', Role::findByName('Super Admin')))->toBeFalse();
});

it('allows updating a non-protected role with the admin.roles.edit permission', function () {
    Permission::findOrCreate('admin.roles.edit');
    $role = Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    expect($actor->can('update', $role))->toBeTrue();
});

it('denies updating a role without the admin.roles.edit permission', function () {
    $role = Role::findOrCreate('Editor');
    $actor = User::factory()->create();

    expect($actor->can('update', $role))->toBeFalse();
});
