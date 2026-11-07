<?php

test('registration screen can be rendered when allow_registration is enabled', function () {
    config(['auth.allow_registration' => true]);

    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register when allow_registration is enabled', function () {
    config(['auth.allow_registration' => true]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration is strictly blocked server-side when allow_registration is false', function () {
    config(['auth.allow_registration' => false]);

    $response = $this->get('/register');
    $response->assertStatus(403);

    $postResponse = $this->post('/register', [
        'name' => 'Blocked User',
        'email' => 'blocked@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $postResponse->assertStatus(403);
});
