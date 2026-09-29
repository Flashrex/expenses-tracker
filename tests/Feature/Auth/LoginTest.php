<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'me@example.com']);
});

test('shows the login form', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertSee('Remember me');
});

test('logs in with valid credentials', function () {
    $this->post(route('login.store'), [
        'email' => 'me@example.com',
        'password' => 'password',
    ])->assertRedirect(route('overview'));

    $this->assertAuthenticatedAs($this->user);
});

test('redirects to the intended page after login', function () {
    $this->get('/trends');

    $this->post(route('login.store'), [
        'email' => 'me@example.com',
        'password' => 'password',
    ])->assertRedirect(route('trends'));
});

test('sets the remember cookie when remember me is checked', function () {
    $this->post(route('login.store'), [
        'email' => 'me@example.com',
        'password' => 'password',
        'remember' => '1',
    ])->assertCookie(Auth::guard('web')->getRecallerName());

    expect($this->user->fresh()->remember_token)->not->toBeNull();
});

test('does not set the remember cookie without remember me', function () {
    $this->post(route('login.store'), [
        'email' => 'me@example.com',
        'password' => 'password',
    ])->assertCookieMissing(Auth::guard('web')->getRecallerName());
});

test('rejects a wrong password', function () {
    $this->from('/login')
        ->post(route('login.store'), [
            'email' => 'me@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.'])
        ->assertSessionHasInput('email', 'me@example.com')
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
});

test('requires email and password', function () {
    $this->post(route('login.store'), [])
        ->assertSessionHasErrors(['email', 'password']);
});

test('locks out after five failed attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => 'me@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => 'me@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many login attempts.');

    $this->assertGuest();
});
