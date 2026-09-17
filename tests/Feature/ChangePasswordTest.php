<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('renders the change password page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.password'))
        ->assertOk()
        ->assertSee('Change Password');
});

it('updates the password when the current password is correct', function () {
    $user = User::factory()->create(['password' => 'current-password']);

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'current-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $response->assertRedirect();
    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an incorrect current password', function () {
    $user = User::factory()->create(['password' => 'current-password']);

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $response->assertSessionHasErrors('current_password', null, 'updatePassword');
    expect(Hash::check('current-password', $user->fresh()->password))->toBeTrue();
});
