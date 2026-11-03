<?php

use App\Models\User;

test('registration screen can be rendered for initial setup', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register for initial setup', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration is blocked when user exists and allow_registration is false', function () {
    User::factory()->create();
    config(['auth.allow_registration' => false]);

    $response = $this->get('/register');
    $response->assertStatus(403);

    $postResponse = $this->post('/register', [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $postResponse->assertStatus(403);
});
