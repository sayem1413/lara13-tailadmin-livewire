<?php

use App\Models\User;

it('renders the login page for a guest', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign In');
});

it('logs in with valid credentials and redirects to the dashboard', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects an incorrect password without authenticating', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('blocks an authenticated request from a deactivated user', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
