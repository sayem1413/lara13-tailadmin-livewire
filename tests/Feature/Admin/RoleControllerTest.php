<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;

it('forbids creating a role without the admin.roles.create permission', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->post(route('admin.roles.store'), ['name' => 'Editor'])
        ->assertForbidden();
});

it('creates a role with the submitted permissions', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    $this->actingAs($actor)
        ->post(route('admin.roles.store'), [
            'name' => 'Editor',
            'permissions' => ['admin.users.index'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Editor');

    expect($role->hasPermissionTo('admin.users.index'))->toBeTrue();
});

it("updates an existing role's permissions", function () {
    Permission::findOrCreate('admin.roles.edit');
    Permission::findOrCreate('admin.users.index');
    Permission::findOrCreate('admin.users.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    $role = Role::findOrCreate('Editor');
    $role->givePermissionTo('admin.users.index');

    $this->actingAs($actor)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Editor',
            'permissions' => ['admin.users.create'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role->refresh();

    expect($role->hasPermissionTo('admin.users.create'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.index'))->toBeFalse();
});

it('refuses to update the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    $this->actingAs($actor)
        ->put(route('admin.roles.update', Role::findByName('Super Admin')), ['name' => 'Renamed'])
        ->assertSessionHasErrors('role');

    expect(Role::findByName('Super Admin')->name)->toBe('Super Admin');
});

it('forbids deleting a role without the admin.roles.destroy permission', function () {
    $actor = User::factory()->create();
    $role = Role::findOrCreate('Editor');

    $this->actingAs($actor)
        ->delete(route('admin.roles.destroy', $role))
        ->assertForbidden();

    expect(Role::find($role->id))->not->toBeNull();
});

it('deletes a role with the admin.roles.destroy permission', function () {
    Permission::findOrCreate('admin.roles.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.destroy');

    $role = Role::findOrCreate('Editor');

    $this->actingAs($actor)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::find($role->id))->toBeNull();
});

it('refuses to delete the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    $this->actingAs($actor)
        ->delete(route('admin.roles.destroy', Role::findByName('Super Admin')))
        ->assertSessionHasErrors('role');

    expect(Role::findByName('Super Admin'))->not->toBeNull();
});

it('refuses to delete a role that still has users assigned', function () {
    Permission::findOrCreate('admin.roles.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.destroy');

    $role = Role::findOrCreate('Editor');
    User::factory()->create()->assignRole($role);

    $this->actingAs($actor)
        ->delete(route('admin.roles.destroy', $role))
        ->assertSessionHasErrors('role');

    expect(Role::find($role->id))->not->toBeNull();
});

it('shows a role to an actor with the admin.roles.index permission', function () {
    Permission::findOrCreate('admin.roles.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.index');

    $role = Role::findOrCreate('Editor');

    $this->actingAs($actor)
        ->get(route('admin.roles.show', $role))
        ->assertOk();
});
