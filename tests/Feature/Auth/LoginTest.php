<?php

use App\Models\User;

it('renders the login page for a guest', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign In');
});

it('redirects an authenticated user away from the login page to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('admin.dashboard.index'));
});

it('logs in with valid credentials and redirects to the dashboard', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('admin.dashboard.index'));
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

    $response = $this->actingAs($user)->get(route('admin.dashboard.index'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
