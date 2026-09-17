<?php

test('redirects a guest visiting the root url to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
