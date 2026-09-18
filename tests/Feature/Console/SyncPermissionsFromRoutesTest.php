<?php

use App\Models\Permission\Permission;

it('creates a permission for every named admin route', function () {
    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'admin.users.index')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.users.create')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.users.edit')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.roles.index')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.activity-log.index')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.settings.edit')->exists())->toBeTrue();
});

it('does not create a permission for a non-admin route', function () {
    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'dashboard')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'profile.edit')->exists())->toBeFalse();
});

it('records the module and section derived from the route name', function () {
    $this->artisan('permissions:sync');

    $permission = Permission::query()->where('name', 'admin.users.edit')->firstOrFail();

    expect($permission->module)->toBe('users')
        ->and($permission->section)->toBe('edit')
        ->and($permission->description)->toBe('Users - Edit');
});

it('removes a previously synced permission whose route no longer exists', function () {
    Permission::create(['name' => 'admin.ghost-module.index', 'guard_name' => 'web']);

    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'admin.ghost-module.index')->exists())->toBeFalse();
});

it('is safe to run twice without duplicating permissions', function () {
    $this->artisan('permissions:sync');
    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'admin.users.index')->count())->toBe(1);
});
