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

    expect(Permission::query()->where('name', 'login')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'home')->exists())->toBeFalse();
});

it('does not create a permission for an ignored admin route', function () {
    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'admin.dashboard.index')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.notifications.index')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.profile.edit')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.profile.password')->exists())->toBeFalse();
});

it('creates a single destroy permission per resource controller instead of one per store/show/update route', function () {
    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'admin.users.destroy')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.users.store')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.users.show')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.users.update')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.roles.destroy')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'admin.roles.store')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.roles.show')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'admin.roles.update')->exists())->toBeFalse();
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

it('does not delete a manually-seeded permission that has no route of its own', function () {
    Permission::create(['name' => 'admin.users.export', 'guard_name' => 'web']);

    $this->artisan('permissions:sync');

    expect(Permission::query()->where('name', 'admin.users.export')->exists())->toBeTrue();
});
