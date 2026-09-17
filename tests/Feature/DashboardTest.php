<?php

use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows the dashboard to an authenticated user', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, Jane Doe');
});
