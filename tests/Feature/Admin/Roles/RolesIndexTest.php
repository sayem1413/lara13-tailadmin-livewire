<?php

use App\Livewire\Admin\Roles\RolesIndex;
use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.roles.index permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
});

it('lists roles with their permission and user counts', function () {
    Permission::findOrCreate('admin.roles.index');
    Permission::findOrCreate('some.permission');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.index');

    $role = Role::findOrCreate('Editor');
    $role->givePermissionTo('some.permission');
    User::factory()->create()->assignRole($role);

    $this->actingAs($actor)->get(route('admin.roles.index'))
        ->assertOk()
        ->assertSee('Editor')
        ->assertSee('1 user');
});

it('deletes a role with no users assigned', function () {
    Permission::findOrCreate('admin.roles.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.destroy');

    $role = Role::findOrCreate('Editor');

    Livewire::actingAs($actor)
        ->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect(Role::find($role->id))->toBeNull();
});

it('refuses to delete a role that still has users assigned', function () {
    Permission::findOrCreate('admin.roles.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.destroy');

    $role = Role::findOrCreate('Editor');
    User::factory()->create()->assignRole($role);

    Livewire::actingAs($actor)
        ->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'error' && str_contains($params['message'], "can't be deleted");
        });

    expect(Role::find($role->id))->not->toBeNull();
});

it('refuses to delete the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(RolesIndex::class)
        ->call('delete', Role::findByName('Super Admin')->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'error' && str_contains($params['message'], 'Super Admin role cannot be deleted');
        });

    expect(Role::findByName('Super Admin'))->not->toBeNull();
});
