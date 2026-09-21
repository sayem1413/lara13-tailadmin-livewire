<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;
use App\Services\Role\RoleService;
use Illuminate\Validation\ValidationException;

it('blocks a non-Super-Admin from creating a role with a permission they do not currently hold', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.settings.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');
    $this->actingAs($actor);

    try {
        app(RoleService::class)->createRole([
            'name' => 'Escalated Role',
            'permissions' => ['admin.settings.edit'],
        ]);
        test()->fail('Expected RoleService::createRole() to throw for an unheld permission.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('permissions');
    }

    expect(Role::where('name', 'Escalated Role')->exists())->toBeFalse();
});

it('blocks a non-Super-Admin from updating a role to include a permission they do not currently hold', function () {
    Permission::findOrCreate('admin.roles.edit');
    Permission::findOrCreate('admin.settings.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');
    $this->actingAs($actor);

    $role = Role::findOrCreate('Editor');

    try {
        app(RoleService::class)->updateRole($role, [
            'name' => 'Editor',
            'permissions' => ['admin.settings.edit'],
        ]);
        test()->fail('Expected RoleService::updateRole() to throw for an unheld permission.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('permissions');
    }

    expect($role->fresh()->hasPermissionTo('admin.settings.edit'))->toBeFalse();
});

it('allows a non-Super-Admin to create a role with only permissions they currently hold', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');
    $actor->givePermissionTo('admin.users.index');
    $this->actingAs($actor);

    $role = app(RoleService::class)->createRole([
        'name' => 'Safe Role',
        'permissions' => ['admin.users.index'],
    ]);

    expect($role->hasPermissionTo('admin.users.index'))->toBeTrue();
});

it('allows a non-Super-Admin to update a role with only permissions they currently hold', function () {
    Permission::findOrCreate('admin.roles.edit');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');
    $actor->givePermissionTo('admin.users.index');
    $this->actingAs($actor);

    $role = Role::findOrCreate('Editor');

    app(RoleService::class)->updateRole($role, [
        'name' => 'Editor',
        'permissions' => ['admin.users.index'],
    ]);

    expect($role->fresh()->hasPermissionTo('admin.users.index'))->toBeTrue();
});

it('exempts a Super Admin actor from the permission-holding guard', function () {
    Permission::findOrCreate('admin.settings.edit');

    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('Super Admin'));
    $this->actingAs($actor);

    $role = app(RoleService::class)->createRole([
        'name' => 'Any Permission Role',
        'permissions' => ['admin.settings.edit'],
    ]);

    expect($role->hasPermissionTo('admin.settings.edit'))->toBeTrue();
});
