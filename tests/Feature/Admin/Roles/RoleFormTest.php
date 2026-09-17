<?php

use App\Livewire\Admin\Roles\RoleForm;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('forbids rendering the create form without the roles.create permission', function () {
    $actor = User::factory()->create();

    Livewire::actingAs($actor)->test(RoleForm::class)->assertForbidden();
});

it('creates a role with the selected permissions', function () {
    Permission::findOrCreate('roles.create');
    Permission::findOrCreate('users.view');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Editor')
        ->set('selectedPermissions', ['users.view'])
        ->call('save')
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Editor');

    expect($role->hasPermissionTo('users.view'))->toBeTrue();
});

it('rejects a duplicate role name', function () {
    Permission::findOrCreate('roles.create');
    Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Editor')
        ->call('save')
        ->assertHasErrors('name');
});

it("updates an existing role's permissions", function () {
    Permission::findOrCreate('roles.update');
    Permission::findOrCreate('users.view');
    Permission::findOrCreate('users.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.update');

    $role = Role::findOrCreate('Editor');
    $role->givePermissionTo('users.view');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => $role])
        ->set('selectedPermissions', ['users.create'])
        ->call('save');

    $role->refresh();

    expect($role->hasPermissionTo('users.create'))->toBeTrue()
        ->and($role->hasPermissionTo('users.view'))->toBeFalse();
});

it('forbids opening the edit form for the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => Role::findByName('Super Admin')])
        ->assertForbidden();
});
