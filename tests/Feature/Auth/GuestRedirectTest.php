<?php

use App\Models\User;

test('redirects guests from protected pages to login', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));

    $this->assertGuest();
})->with(['/', '/overview', '/trends', '/upload']);

test('redirects the root url to overview for users', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)->get('/')->assertRedirect(route('overview'));
});

test('redirects logged in users away from the login page', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)->get('/login')->assertRedirect(route('overview'));
});
