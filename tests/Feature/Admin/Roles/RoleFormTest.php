<?php

use App\Livewire\Admin\Roles\RoleForm;
use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

it('forbids rendering the create form without the admin.roles.create permission', function () {
    $actor = User::factory()->create();

    Livewire::actingAs($actor)->test(RoleForm::class)->assertForbidden();
});

it('creates a role with the selected permissions', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Editor')
        ->set('selectedPermissions', ['admin.users.index'])
        ->call('save')
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Editor');

    expect($role->hasPermissionTo('admin.users.index'))->toBeTrue();
});

it('rejects a duplicate role name', function () {
    Permission::findOrCreate('admin.roles.create');
    Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Editor')
        ->call('save')
        ->assertHasErrors('name');
});

it("updates an existing role's permissions", function () {
    Permission::findOrCreate('admin.roles.edit');
    Permission::findOrCreate('admin.users.index');
    Permission::findOrCreate('admin.users.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    $role = Role::findOrCreate('Editor');
    $role->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => $role])
        ->set('selectedPermissions', ['admin.users.create'])
        ->call('save');

    $role->refresh();

    // Neither permission was created with a module/section (see the sync
    // command), so Permission::expandWithImplied() has nothing to expand
    // here - this purely proves that syncPermissions() replaces rather
    // than merges. The implied-permission expansion itself is covered by
    // the "automatically grants..." test below, using real synced
    // permissions that do carry a module/section.
    expect($role->hasPermissionTo('admin.users.create'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.index'))->toBeFalse();
});

it("automatically grants a module's index permission when a write permission is selected", function () {
    Artisan::call('permissions:sync');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    $role = Role::findOrCreate('Editor');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => $role])
        ->set('selectedPermissions', ['admin.users.create'])
        ->call('save');

    $role->refresh();

    expect($role->hasPermissionTo('admin.users.create'))->toBeTrue()
        ->and($role->hasPermissionTo('admin.users.index'))->toBeTrue();
});

it("checks a module's whole permission group at once, then unchecks it again", function () {
    Artisan::call('permissions:sync');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    $usersPermissions = Permission::query()->where('module', 'users')->pluck('name')->all();

    $component = Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->call('toggleGroup', $usersPermissions);

    foreach ($usersPermissions as $name) {
        expect($component->get('selectedPermissions'))->toContain($name);
    }

    $component->call('toggleGroup', $usersPermissions);

    expect($component->get('selectedPermissions'))->toBe([]);
});

it('checks every permission at once via the global toggle, then unchecks them all', function () {
    Artisan::call('permissions:sync');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    $everyPermission = Permission::query()->pluck('name')->all();

    $component = Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->call('toggleAllPermissions');

    expect($component->get('selectedPermissions'))->toEqualCanonicalizing($everyPermission);

    $component->call('toggleAllPermissions');

    expect($component->get('selectedPermissions'))->toBe([]);
});

it('forbids opening the edit form for the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => Role::findByName('Super Admin')])
        ->assertForbidden();
});
