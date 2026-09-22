<?php

use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.dashboard.index'))->assertRedirect(route('login'));
});

it('shows the dashboard to an authenticated user', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $this->actingAs($user)->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertSee('Welcome back, Jane Doe');
});
