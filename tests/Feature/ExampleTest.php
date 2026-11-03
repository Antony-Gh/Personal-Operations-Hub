<?php

use App\Models\User;

it('redirects unauthenticated guests to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

it('redirects authenticated users to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('dashboard'));
});
