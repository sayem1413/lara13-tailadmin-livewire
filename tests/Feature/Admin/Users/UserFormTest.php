<?php

use App\Livewire\Admin\Users\UserForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('forbids rendering the create form without the users.create permission', function () {
    $actor = User::factory()->create();

    Livewire::actingAs($actor)->test(UserForm::class)->assertForbidden();
});

it('creates a user with the submitted roles', function () {
    Permission::findOrCreate('users.create');
    Role::findOrCreate('Editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    Livewire::actingAs($actor)
        ->test(UserForm::class)
        ->set('name', 'New Person')
        ->set('email', 'new-person@example.com')
        ->set('password', 'a-secure-password')
        ->set('password_confirmation', 'a-secure-password')
        ->set('selectedRoles', ['Editor'])
        ->call('save')
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new-person@example.com')->firstOrFail();

    expect($user->name)->toBe('New Person')
        ->and(Hash::check('a-secure-password', $user->password))->toBeTrue()
        ->and($user->hasRole('Editor'))->toBeTrue();
});

it('requires a password when creating a user', function () {
    Permission::findOrCreate('users.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    Livewire::actingAs($actor)
        ->test(UserForm::class)
        ->set('name', 'New Person')
        ->set('email', 'new-person@example.com')
        ->call('save')
        ->assertHasErrors('password');
});

it('rejects a duplicate email', function () {
    Permission::findOrCreate('users.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($actor)
        ->test(UserForm::class)
        ->set('name', 'New Person')
        ->set('email', 'taken@example.com')
        ->set('password', 'a-secure-password')
        ->set('password_confirmation', 'a-secure-password')
        ->call('save')
        ->assertHasErrors('email');
});

it('updates an existing user without changing the password when left blank', function () {
    Permission::findOrCreate('users.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $target = User::factory()->create(['name' => 'Old Name', 'password' => 'original-password']);

    Livewire::actingAs($actor)
        ->test(UserForm::class, ['user' => $target])
        ->set('name', 'Updated Name')
        ->call('save')
        ->assertRedirect(route('admin.users.index'));

    $target->refresh();

    expect($target->name)->toBe('Updated Name')
        ->and(Hash::check('original-password', $target->password))->toBeTrue();
});

it('updates the password when one is provided', function () {
    Permission::findOrCreate('users.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $target = User::factory()->create(['password' => 'original-password']);

    Livewire::actingAs($actor)
        ->test(UserForm::class, ['user' => $target])
        ->set('password', 'brand-new-password')
        ->set('password_confirmation', 'brand-new-password')
        ->call('save');

    expect(Hash::check('brand-new-password', $target->fresh()->password))->toBeTrue();
});

it('forbids opening the edit form for a Super Admin unless the actor is also a Super Admin', function () {
    Permission::findOrCreate('users.update');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $target = User::factory()->create();
    $target->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(UserForm::class, ['user' => $target])
        ->assertForbidden();
});

it('cannot assign the Super Admin role to another user without the Super Admin role', function () {
    Permission::findOrCreate('users.create');
    Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->givePermissionTo('users.create');

    Livewire::actingAs($actor)
        ->test(UserForm::class)
        ->set('name', 'New Person')
        ->set('email', 'new-person@example.com')
        ->set('password', 'a-secure-password')
        ->set('password_confirmation', 'a-secure-password')
        ->set('selectedRoles', ['Super Admin'])
        ->call('save');

    $user = User::where('email', 'new-person@example.com')->firstOrFail();

    expect($user->hasRole('Super Admin'))->toBeFalse();
});
