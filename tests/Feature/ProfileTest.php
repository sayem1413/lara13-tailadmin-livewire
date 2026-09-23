<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders the profile page with the current user\'s details', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs($user)->get(route('admin.profile.edit'))
        ->assertOk()
        ->assertSee('Original Name');
});

it('updates the name and email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-profile-information.update'), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertRedirect();
    expect($user->fresh())
        ->name->toBe('Updated Name')
        ->email->toBe('updated@example.com');
});

it('rejects an email already used by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-profile-information.update'), [
        'name' => $user->name,
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email', null, 'updateProfileInformation');
    expect($user->fresh()->email)->not->toBe('taken@example.com');
});

it('allows reusing an email that belonged to a soft-deleted user, consistent with UserForm', function () {
    $trashed = User::factory()->create(['email' => 'freed@example.com']);
    $trashed->delete();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-profile-information.update'), [
        'name' => $user->name,
        'email' => 'freed@example.com',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->email)->toBe('freed@example.com');
});

it('stores an uploaded avatar in the media library', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-profile-information.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $freshUrl = $user->fresh()->avatarUrl();

    expect($freshUrl)->not->toBeNull();
    Storage::disk('public')->assertExists(str($freshUrl)->after('/storage/')->toString());
});
