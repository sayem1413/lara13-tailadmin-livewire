<?php

use App\Livewire\Admin\Users\UsersIndex;
use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

it('forbids exporting without the admin.users.export permission', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('export')
        ->assertForbidden();
});

it('exports the current filtered list for a user with the admin.users.export permission', function () {
    Permission::findOrCreate('admin.users.index');
    Permission::findOrCreate('admin.users.export');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.users.index', 'admin.users.export']);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('export')
        ->assertFileDownloaded();
});

it('forbids exporting a PDF without the admin.users.export permission', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('exports a PDF for a user with the admin.users.export permission', function () {
    Permission::findOrCreate('admin.users.index');
    Permission::findOrCreate('admin.users.export');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.users.index', 'admin.users.export']);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('exportPdf')
        ->assertFileDownloaded();
});

it('forbids a user without the admin.users.index permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

it('renders the users list for a user with the admin.users.index permission', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

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
    Permission::findOrCreate('admin.users.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create(['is_active' => true]);

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('toggleActive', $target->id);

    expect($target->fresh()->is_active)->toBeFalse();
});

it("forbids toggling a Super Admin's status without the Super Admin role", function () {
    Permission::findOrCreate('admin.users.edit');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('toggleActive', $target->id)
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

it('does not offer to deactivate your own account from the list', function () {
    Permission::findOrCreate('admin.users.edit');
    $actor = User::factory()->create(['is_active' => true]);
    $actor->givePermissionTo('admin.users.edit');

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->assertDontSeeHtml('wire:click="toggleActive('.$actor->id.')"');
});

it('soft deletes a user', function () {
    Permission::findOrCreate('admin.users.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.destroy');

    $target = User::factory()->create();

    Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->call('delete', $target->id);

    expect(User::find($target->id))->toBeNull()
        ->and(User::withTrashed()->findOrFail($target->id)->trashed())->toBeTrue();
});

it('renders the select-all-on-page checkbox as checked once every row on the page is selected', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    $component = Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->set('selected', [$actor->id, $other->id]);

    $component->assertSeeHtml('wire:click="toggleSelectAllOnPage" checked="checked"');
});

it('does not render the select-all-on-page checkbox as checked while some rows are unselected', function () {
    $actor = User::factory()->create();
    User::factory()->create();

    $component = Livewire::actingAs($actor)
        ->test(UsersIndex::class)
        ->set('selected', [$actor->id]);

    $component->assertDontSeeHtml('wire:click="toggleSelectAllOnPage" checked="checked"');
});

it('only activates the users the actor is allowed to update in a bulk action', function () {
    Permission::findOrCreate('admin.users.edit');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

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
