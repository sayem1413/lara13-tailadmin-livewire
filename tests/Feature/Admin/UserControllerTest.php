<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('forbids creating a user without the admin.users.create permission', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ])
        ->assertForbidden();
});

it('creates a user with the submitted roles', function () {
    Permission::findOrCreate('admin.users.create');
    Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.create');

    $this->actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'roles' => ['Editor'],
        ])
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new-person@example.com')->firstOrFail();

    expect($user->name)->toBe('New Person')
        ->and($user->hasRole('Editor'))->toBeTrue();
});

it('updates an existing user without changing the password when left blank', function () {
    Permission::findOrCreate('admin.users.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create(['name' => 'Old Name', 'password' => 'original-password']);

    $this->actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => 'Updated Name',
            'email' => $target->email,
        ])
        ->assertRedirect(route('admin.users.index'));

    $target->refresh();

    expect($target->name)->toBe('Updated Name')
        ->and(Hash::check('original-password', $target->password))->toBeTrue();
});

it('prevents a user from deactivating their own account', function () {
    Permission::findOrCreate('admin.users.edit');
    $actor = User::factory()->create(['is_active' => true]);
    $actor->givePermissionTo('admin.users.edit');

    $this->actingAs($actor)
        ->put(route('admin.users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'is_active' => false,
        ])
        ->assertSessionHasErrors('is_active');

    expect($actor->fresh()->is_active)->toBeTrue();
});

it('forbids deleting a user without the admin.users.destroy permission', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    expect($target->fresh())->not->toBeNull();
});

it('deletes a user with the admin.users.destroy permission', function () {
    Permission::findOrCreate('admin.users.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.destroy');

    $target = User::factory()->create();

    $this->actingAs($actor)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    $this->assertSoftDeleted($target);
});

it('shows a user to an actor with the admin.users.index permission', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    $target = User::factory()->create();

    $this->actingAs($actor)
        ->get(route('admin.users.show', $target))
        ->assertOk();
});
