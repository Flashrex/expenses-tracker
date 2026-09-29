<?php

use App\Models\User;

test('logs the user out', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('rejects logout via get', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)->get('/logout')->assertStatus(405);
});
