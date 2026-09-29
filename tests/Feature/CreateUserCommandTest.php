<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates the user', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Email', 'me@example.com')
        ->expectsQuestion('Password', 'secret-pass')
        ->expectsOutputToContain('User me@example.com created.')
        ->assertSuccessful();

    expect(User::count())->toBe(1);

    $user = User::first();
    expect(Hash::check('secret-pass', $user->password))->toBeTrue()
        ->and($user->name)->toBeNull();
});

test('refuses when a user already exists', function () {
    User::factory()->create(['email' => 'me@example.com']);

    $this->artisan('user:create')
        ->expectsOutputToContain('A user already exists. Only one account is allowed.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});

test('creates the user only once', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Email', 'me@example.com')
        ->expectsQuestion('Password', 'secret-pass')
        ->assertSuccessful();

    $this->artisan('user:create')
        ->expectsOutputToContain('A user already exists. Only one account is allowed.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});

test('rejects an invalid email', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsOutputToContain('The email field must be a valid email address.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('rejects a password shorter than 8 characters', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Email', 'me@example.com')
        ->expectsQuestion('Password', 'short')
        ->expectsOutputToContain('The password field must be at least 8 characters.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
