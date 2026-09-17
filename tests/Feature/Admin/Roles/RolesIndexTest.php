<?php

use App\Livewire\Admin\Roles\RolesIndex;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
});

it('forbids a user without the roles.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
});

it('lists roles with their permission and user counts', function () {
    Permission::findOrCreate('roles.view');
    Permission::findOrCreate('some.permission');

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.view');

    $role = Role::findOrCreate('Editor');
    $role->givePermissionTo('some.permission');
    User::factory()->create()->assignRole($role);

    $this->actingAs($actor)->get(route('admin.roles.index'))
        ->assertOk()
        ->assertSee('Editor')
        ->assertSee('1 user');
});

it('deletes a role with no users assigned', function () {
    Permission::findOrCreate('roles.delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.delete');

    $role = Role::findOrCreate('Editor');

    Livewire::actingAs($actor)
        ->test(RolesIndex::class)
        ->call('delete', $role->id);

    expect(Role::find($role->id))->toBeNull();
});

it('refuses to delete a role that still has users assigned', function () {
    Permission::findOrCreate('roles.delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.delete');

    $role = Role::findOrCreate('Editor');
    User::factory()->create()->assignRole($role);

    Livewire::actingAs($actor)
        ->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertSee("can't be deleted");

    expect(Role::find($role->id))->not->toBeNull();
});

it('refuses to delete the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(RolesIndex::class)
        ->call('delete', Role::findByName('Super Admin')->id);

    expect(Role::findByName('Super Admin'))->not->toBeNull();
});
