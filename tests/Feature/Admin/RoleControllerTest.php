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
    $actor->givePermissionTo('admin.users.index');

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
    $actor->givePermissionTo('admin.users.create');

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

it('creates a role with a description and an explicit active status', function () {
    Permission::findOrCreate('admin.roles.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    $this->actingAs($actor)
        ->post(route('admin.roles.store'), [
            'name' => 'Editor',
            'description' => 'Edits published content',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Editor');

    expect($role->description)->toBe('Edits published content')
        ->and($role->is_active)->toBeFalse();
});

it("updates an existing role's description and active status", function () {
    Permission::findOrCreate('admin.roles.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    $role = Role::findOrCreate('Editor');

    $this->actingAs($actor)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Editor',
            'description' => 'Updated description',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role->refresh();

    expect($role->description)->toBe('Updated description')
        ->and($role->is_active)->toBeFalse();
});

it('blocks creating a role with a permission the actor does not currently hold, even via a direct request that bypasses the UI checkboxes', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.settings.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    $this->actingAs($actor)
        ->post(route('admin.roles.store'), [
            'name' => 'Escalated Role',
            'permissions' => ['admin.settings.edit'],
        ])
        ->assertSessionHasErrors('permissions');

    expect(Role::where('name', 'Escalated Role')->exists())->toBeFalse();
});

it('blocks updating a role to include a permission the actor does not currently hold, even via a direct request that bypasses the UI checkboxes', function () {
    Permission::findOrCreate('admin.roles.edit');
    Permission::findOrCreate('admin.settings.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    $role = Role::findOrCreate('Editor');

    $this->actingAs($actor)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Editor',
            'permissions' => ['admin.settings.edit'],
        ])
        ->assertSessionHasErrors('permissions');

    expect($role->fresh()->hasPermissionTo('admin.settings.edit'))->toBeFalse();
});

it('exempts a Super Admin actor from the permission-holding guard on create', function () {
    Permission::findOrCreate('admin.settings.edit');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    $this->actingAs($actor)
        ->post(route('admin.roles.store'), [
            'name' => 'Any Permission Role',
            'permissions' => ['admin.settings.edit'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::findByName('Any Permission Role')->hasPermissionTo('admin.settings.edit'))->toBeTrue();
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

it("shows a role's description and inactive status badge", function () {
    Permission::findOrCreate('admin.roles.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.index');

    $role = Role::findOrCreate('Editor');
    $role->update(['description' => 'Edits published content', 'is_active' => false]);

    $this->actingAs($actor)
        ->get(route('admin.roles.show', $role))
        ->assertOk()
        ->assertSee('Edits published content')
        ->assertSee('Inactive');
});
