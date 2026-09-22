<?php

use App\Models\Permission\Role;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Validation\ValidationException;

it('blocks assigning an inactive role when creating a user', function () {
    Role::findOrCreate('Retired Role')->update(['is_active' => false]);

    try {
        app(UserService::class)->createUser([
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'a-secure-password',
            'roles' => ['Retired Role'],
        ]);
        test()->fail('Expected UserService::createUser() to throw for an inactive role.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('roles')
            ->and($e->errors()['roles'][0])->toContain('Retired Role');
    }

    expect(User::where('email', 'new-person@example.com')->exists())->toBeFalse();
});

it('blocks assigning an inactive role when updating a user', function () {
    Role::findOrCreate('Retired Role')->update(['is_active' => false]);

    $target = User::factory()->create();

    try {
        app(UserService::class)->updateUser($target, [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['Retired Role'],
        ]);
        test()->fail('Expected UserService::updateUser() to throw for an inactive role.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('roles');
    }

    expect($target->fresh()->hasRole('Retired Role'))->toBeFalse();
});

it('allows assigning an active role', function () {
    Role::findOrCreate('Editor');

    $user = app(UserService::class)->createUser([
        'name' => 'New Person',
        'email' => 'new-person@example.com',
        'password' => 'a-secure-password',
        'roles' => ['Editor'],
    ]);

    expect($user->hasRole('Editor'))->toBeTrue();
});

it('does not block an update that leaves roles untouched even if a previously-held role has since gone inactive', function () {
    $role = Role::findOrCreate('Editor');
    $target = User::factory()->create();
    $target->assignRole($role);

    // Deactivated after assignment - an unrelated update (no "roles" key at
    // all, so UserService treats roles as untouched) must not be blocked by
    // it. This is the "simple boolean guard, nothing more" scope: is_active
    // only gates a *new* sync, it never revokes what's already held.
    $role->update(['is_active' => false]);

    $updated = app(UserService::class)->updateUser($target, [
        'name' => 'Renamed',
        'email' => $target->email,
    ]);

    expect($updated->name)->toBe('Renamed')
        ->and($updated->hasRole('Editor'))->toBeTrue();
});

it('blocks a user from deleting their own account', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    try {
        app(UserService::class)->deleteUser($actor);
        test()->fail('Expected UserService::deleteUser() to throw for a self-delete.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('user');
    }

    expect($actor->fresh()->trashed())->toBeFalse();
});

it('blocks a user from permanently deleting their own account', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    try {
        app(UserService::class)->forceDeleteUser($actor);
        test()->fail('Expected UserService::forceDeleteUser() to throw for a self-force-delete.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('user');
    }

    expect(User::withTrashed()->whereKey($actor->id)->exists())->toBeTrue();
});

it('blocks removing the Super Admin role from the last remaining Super Admin', function () {
    $superAdminRole = Role::findOrCreate('Super Admin');
    $actor = User::factory()->create();
    $actor->assignRole($superAdminRole);
    $this->actingAs($actor);

    try {
        app(UserService::class)->updateUser($actor, [
            'name' => $actor->name,
            'email' => $actor->email,
            'roles' => [],
        ]);
        test()->fail('Expected UserService::updateUser() to throw when removing the last Super Admin.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('roles');
    }

    expect($actor->fresh()->hasRole('Super Admin'))->toBeTrue();
});

it('allows removing the Super Admin role when another Super Admin remains', function () {
    $superAdminRole = Role::findOrCreate('Super Admin');

    $actor = User::factory()->create();
    $actor->assignRole($superAdminRole);
    $this->actingAs($actor);

    $anotherSuperAdmin = User::factory()->create();
    $anotherSuperAdmin->assignRole($superAdminRole);

    $updated = app(UserService::class)->updateUser($anotherSuperAdmin, [
        'name' => $anotherSuperAdmin->name,
        'email' => $anotherSuperAdmin->email,
        'roles' => [],
    ]);

    expect($updated->hasRole('Super Admin'))->toBeFalse();
});
