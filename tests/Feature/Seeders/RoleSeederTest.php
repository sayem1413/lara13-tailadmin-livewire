<?php

use App\Models\Permission\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    // RoleSeeder assigns permissions RolesAndPermissionsSeeder is
    // responsible for creating first - including the manually-seeded
    // ones (e.g. admin.users.destroy) that permissions:sync alone can't
    // discover, since no route backs them.
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('grants the User Manager role full access to users and read-only activity log access', function () {
    $this->seed(RoleSeeder::class);

    $role = Role::findByName('User Manager');

    expect($role->hasPermissionTo('admin.users.index'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.create'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.edit'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.activity-log.index'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.roles.index'))->toBeFalse()
        ->and($role->hasPermissionTo('admin.settings.edit'))->toBeFalse();
});

it('grants the Auditor role read-only access across users, roles, and the activity log', function () {
    $this->seed(RoleSeeder::class);

    $role = Role::findByName('Auditor');

    expect($role->hasPermissionTo('admin.users.index'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.roles.index'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.activity-log.index'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.create'))->toBeFalse()
        ->and($role->hasPermissionTo('admin.users.destroy'))->toBeFalse();
});

it('grants the Settings Manager role only the settings permissions', function () {
    $this->seed(RoleSeeder::class);

    $role = Role::findByName('Settings Manager');

    expect($role->hasPermissionTo('admin.settings.edit'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.settings.update'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.index'))->toBeFalse();
});

it('is safe to run twice without duplicating roles', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::query()->where('name', 'User Manager')->count())->toBe(1)
        ->and(Role::query()->whereIn('name', ['Super Admin', 'Admin'])->count())->toBe(2);
});
