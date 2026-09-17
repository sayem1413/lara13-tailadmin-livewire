<?php

use App\Livewire\Admin\Users\UsersIndex;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

it('forbids a user without the users.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

it('renders the users list for a user with the users.view permission', function () {
    Permission::findOrCreate('users.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.view');

    User::factory()->create(['name' => 'Jane Searchable']);

    $this->actingAs($actor)->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Jane Searchable');
});

it('filters the list by search term', function () {
    $actor = User::factory()->create();
    User::factory()->create(['name' => 'Alice Match']);
    User::factory()->create(['name' => 'Bob Other']);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->set('search', 'Alice')
        ->assertSee('Alice Match')
        ->assertDontSee('Bob Other');
});

it('filters the list by role', function () {
    $actor = User::factory()->create();

    $role = Role::findOrCreate('Editor');
    $editor = User::factory()->create(['name' => 'Role Match']);
    $editor->assignRole($role);
    User::factory()->create(['name' => 'No Role User']);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->set('role', 'Editor')
        ->assertSee('Role Match')
        ->assertDontSee('No Role User');
});

it('filters the list by active status', function () {
    $actor = User::factory()->create();
    User::factory()->create(['name' => 'Active Person', 'is_active' => true]);
    User::factory()->create(['name' => 'Inactive Person', 'is_active' => false]);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->set('status', 'inactive')
        ->assertSee('Inactive Person')
        ->assertDontSee('Active Person');
});

it("toggles a user's active status", function () {
    Permission::findOrCreate('users.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $target = User::factory()->create(['is_active' => true]);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('toggleActive', $target->id);

    expect($target->fresh()->is_active)->toBeFalse();
});

it("forbids toggling a Super Admin's status without the Super Admin role", function () {
    Permission::findOrCreate('users.update');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('toggleActive', $target->id)
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

it('soft deletes a user', function () {
    Permission::findOrCreate('users.delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.delete');

    $target = User::factory()->create();

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('delete', $target->id);

    expect(User::find($target->id))->toBeNull()
        ->and(User::withTrashed()->findOrFail($target->id)->trashed())->toBeTrue();
});

it('only activates the users the actor is allowed to update in a bulk action', function () {
    Permission::findOrCreate('users.update');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $allowed = User::factory()->create(['is_active' => false]);
    $superAdmin = User::factory()->create(['is_active' => false]);
    $superAdmin->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->set('selected', [$allowed->id, $superAdmin->id])
        ->call('bulkActivate');

    expect($allowed->fresh()->is_active)->toBeTrue()
        ->and($superAdmin->fresh()->is_active)->toBeFalse();
});
