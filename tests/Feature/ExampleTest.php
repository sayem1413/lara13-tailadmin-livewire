<?php

use App\Models\User;

test('shows the login page to a guest visiting the root url', function () {
    $response = $this->get('/');

    $response->assertOk()->assertSee('Sign In');
});

test('redirects an authenticated user away from the root url to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('admin.dashboard.index'));
});
