<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\User;
use App\Notifications\UserRoleUpdatedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

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

it('prevents a non-Super-Admin actor from assigning the Super Admin role on create', function () {
    Permission::findOrCreate('admin.users.create');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.create');

    $this->actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'roles' => ['Super Admin'],
        ])
        ->assertSessionHasErrors('roles');

    expect(User::where('email', 'new-person@example.com')->exists())->toBeFalse();
});

it('prevents a non-Super-Admin actor from assigning the Super Admin role on update', function () {
    Permission::findOrCreate('admin.users.edit');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['Super Admin'],
        ])
        ->assertSessionHasErrors('roles');

    expect($target->fresh()->hasRole('Super Admin'))->toBeFalse();
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

it('notifies the target user when an admin changes their roles', function () {
    Notification::fake();

    Permission::findOrCreate('admin.users.edit');
    Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['Editor'],
        ])
        ->assertRedirect(route('admin.users.index'));

    Notification::assertSentTo($target, UserRoleUpdatedNotification::class);
});

it('does not notify the target user when their roles are left unchanged', function () {
    Notification::fake();

    Permission::findOrCreate('admin.users.edit');
    Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.edit');

    $target = User::factory()->create();
    $target->syncRoles(['Editor']);

    $this->actingAs($actor)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['Editor'],
        ])
        ->assertRedirect(route('admin.users.index'));

    Notification::assertNotSentTo($target, UserRoleUpdatedNotification::class);
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

it('forbids restoring a user without the admin.users.restore permission', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($actor)
        ->put(route('admin.users.restore', $target))
        ->assertForbidden();

    expect($target->fresh()->trashed())->toBeTrue();
});

it('restores a soft-deleted user with the admin.users.restore permission', function () {
    Permission::findOrCreate('admin.users.restore');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.restore');

    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($actor)
        ->put(route('admin.users.restore', $target))
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->trashed())->toBeFalse();
});

it("forbids restoring a Super Admin's account without the Super Admin role", function () {
    Permission::findOrCreate('admin.users.restore');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.restore');

    $target = User::factory()->create();
    $target->assignRole('Super Admin');
    $target->delete();

    $this->actingAs($actor)
        ->put(route('admin.users.restore', $target))
        ->assertForbidden();

    expect(User::withTrashed()->findOrFail($target->id)->trashed())->toBeTrue();
});

it('forbids force-deleting a user without the admin.users.force-delete permission', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($actor)
        ->delete(route('admin.users.force-delete', $target))
        ->assertForbidden();

    expect(User::withTrashed()->find($target->id))->not->toBeNull();
});

it('permanently deletes a user with the admin.users.force-delete permission', function () {
    Permission::findOrCreate('admin.users.force-delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.force-delete');

    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($actor)
        ->delete(route('admin.users.force-delete', $target))
        ->assertRedirect(route('admin.users.index'));

    expect(User::withTrashed()->find($target->id))->toBeNull();
});

it('prevents an actor from force-deleting their own account', function () {
    Permission::findOrCreate('admin.users.force-delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.force-delete');
    $actor->delete();

    $this->actingAs($actor)
        ->delete(route('admin.users.force-delete', $actor))
        ->assertForbidden();

    expect(User::withTrashed()->find($actor->id))->not->toBeNull();
});

it("forbids force-deleting a Super Admin's account without the Super Admin role", function () {
    Permission::findOrCreate('admin.users.force-delete');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.force-delete');

    $target = User::factory()->create();
    $target->assignRole('Super Admin');
    $target->delete();

    $this->actingAs($actor)
        ->delete(route('admin.users.force-delete', $target))
        ->assertForbidden();

    expect(User::withTrashed()->find($target->id))->not->toBeNull();
});

it('allows creating a new user with the email address of a soft-deleted user', function () {
    Permission::findOrCreate('admin.users.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.create');

    $trashed = User::factory()->create(['email' => 'reused@example.com']);
    $trashed->delete();

    $this->actingAs($actor)
        ->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'reused@example.com',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'reused@example.com')->count())->toBe(1)
        ->and(User::withTrashed()->where('email', 'reused@example.com')->count())->toBe(2);
});
