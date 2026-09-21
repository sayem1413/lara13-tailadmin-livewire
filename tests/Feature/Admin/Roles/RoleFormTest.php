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
    $actor->givePermissionTo('admin.users.index');

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
    $actor->givePermissionTo('admin.users.create');

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
    // The role-manager must hold both the write permission being assigned
    // and its implied module-level view permission (see
    // RoleService::guardAgainstUnassignablePermissions()) - expandPermissions()
    // adds admin.users.index to the persisted set even though only
    // admin.users.create is selected below.
    $actor->givePermissionTo('admin.users.create');
    $actor->givePermissionTo('admin.users.index');

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

it('renders the per-module select-all checkbox as checked once every permission the actor HOLDS in that module is selected', function () {
    Artisan::call('permissions:sync');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    // The actor only holds SOME of the users module's permissions - the
    // module select-all checkbox reflects "every permission the actor
    // holds in this module is selected", not "every permission in the
    // module", since toggleGroup() is only ever called with the held
    // subset (see role-form.blade.php) - an actor can't use this control
    // to select a permission they don't hold themselves.
    //
    // Ordered the same way RoleForm::permissionGroups() orders them, since
    // this is compared against the exact wire:click argument list rendered
    // in the view - a different array order would be the same permission
    // set but a different (mis-matching) JSON string.
    $heldUsersPermissions = Permission::query()->where('module', 'users')->orderBy('section')->take(2)->pluck('name')->all();
    $actor->givePermissionTo($heldUsersPermissions);

    $component = Livewire::actingAs($actor)->test(RoleForm::class);

    $component->set('selectedPermissions', $heldUsersPermissions);
    $component->assertSeeHtml('wire:click="toggleGroup('.e(json_encode($heldUsersPermissions)).')" checked="checked"');
});

it('disables the per-module select-all checkbox when the actor holds none of that module\'s permissions', function () {
    Artisan::call('permissions:sync');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)->test(RoleForm::class)
        ->assertSeeHtml('wire:click="toggleGroup([])" disabled="disabled"');
});

it('no longer offers a single "select all permissions" control for the whole page - only per-module', function () {
    Permission::findOrCreate('admin.roles.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)->test(RoleForm::class)
        ->assertDontSeeHtml('wire:click="toggleAllPermissions"');
});

it('saves description and is_active when creating a role', function () {
    Permission::findOrCreate('admin.roles.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Editor')
        ->set('description', 'Edits published content')
        ->set('is_active', false)
        ->call('save')
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

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => $role])
        ->set('description', 'Updated description')
        ->set('is_active', false)
        ->call('save');

    $role->refresh();

    expect($role->description)->toBe('Updated description')
        ->and($role->is_active)->toBeFalse();
});

it('prefills the description and active status when editing an existing role', function () {
    Permission::findOrCreate('admin.roles.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');

    $role = Role::findOrCreate('Editor');
    $role->update(['description' => 'Pre-existing description', 'is_active' => false]);

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => $role])
        ->assertSet('description', 'Pre-existing description')
        ->assertSet('is_active', false);
});

it('blocks assigning a permission the actor does not currently hold, even via the Livewire form', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.settings.edit');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Escalated Role')
        ->set('selectedPermissions', ['admin.settings.edit'])
        ->call('save')
        ->assertHasErrors('permissions');

    expect(Role::where('name', 'Escalated Role')->exists())->toBeFalse();
});

it('allows assigning a permission the actor currently holds via the Livewire form', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');
    $actor->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->set('name', 'Safe Role')
        ->set('selectedPermissions', ['admin.users.index'])
        ->call('save')
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::findByName('Safe Role')->hasPermissionTo('admin.users.index'))->toBeTrue();
});

it('forbids opening the edit form for the Super Admin role even for a Super Admin actor', function () {
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => Role::findByName('Super Admin')])
        ->assertForbidden();
});

it('renders a checkbox for a permission the actor does not hold as disabled with an explanatory tooltip', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)->test(RoleForm::class)
        ->assertSeeHtml('value="admin.users.index" disabled="disabled"')
        ->assertSeeHtml('title="You don\'t have this permission yourself, so you can\'t grant it to a role."');
});

it('renders a checkbox for a permission the actor holds as enabled, without a tooltip', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');
    $actor->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)->test(RoleForm::class)
        ->assertDontSeeHtml('value="admin.users.index" disabled="disabled"');
});

it('does not disable any permission checkbox for a Super Admin actor', function () {
    Permission::findOrCreate('admin.roles.create');
    Permission::findOrCreate('admin.users.index');
    Role::findOrCreate('Super Admin')->givePermissionTo(['admin.roles.create', 'admin.users.index']);

    $actor = User::factory()->create();
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)->test(RoleForm::class)
        ->assertDontSeeHtml('disabled="disabled"');
});

it('snapshots the role\'s permissions at mount as originalPermissions, unaffected by later selection changes', function () {
    Permission::findOrCreate('admin.roles.edit');
    Permission::findOrCreate('admin.users.index');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.edit');
    $actor->givePermissionTo('admin.users.index');

    $role = Role::findOrCreate('Editor');
    $role->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)
        ->test(RoleForm::class, ['role' => $role])
        ->assertSet('originalPermissions', ['admin.users.index'])
        ->set('selectedPermissions', [])
        ->assertSet('originalPermissions', ['admin.users.index']);
});

it('snapshots an empty originalPermissions for a brand new role', function () {
    Permission::findOrCreate('admin.roles.create');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.roles.create');

    Livewire::actingAs($actor)
        ->test(RoleForm::class)
        ->assertSet('originalPermissions', []);
});
