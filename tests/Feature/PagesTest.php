<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'me@example.com']);
});

test('renders each app page for the user', function (string $name, string $title) {
    $this->actingAs($this->user)
        ->get(route($name))
        ->assertOk()
        ->assertSee($title)
        ->assertSee('<title>'.ucfirst($name).' · Expenses Tracker</title>', false);
})->with([
    ['overview', 'No data yet'],
    ['trends', 'No trends yet'],
    ['upload', 'Drop your ING statements here'],
]);

test('shows the navigation with the current page marked', function () {
    $response = $this->actingAs($this->user)->get(route('trends'));

    $response->assertSee(route('overview'))
        ->assertSee(route('trends'))
        ->assertSee(route('upload'))
        ->assertSee(route('logout'))
        ->assertSee('aria-current="page"', false);

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBe(1);
});

test('links the empty states to upload', function () {
    $this->actingAs($this->user);

    foreach (['overview', 'trends'] as $name) {
        $this->get(route($name))
            ->assertSee('Upload statement')
            ->assertSee(route('upload'));
    }

    $this->get(route('upload'))->assertDontSee('Upload statement');
});

test('uses no external font or icon hosts', function () {
    $responses = [
        $this->get('/login'),
        $this->actingAs($this->user)->get('/overview'),
    ];

    foreach ($responses as $response) {
        $response->assertOk();

        foreach (['bunny.net', 'fonts.googleapis', 'cdn.', 'unpkg'] as $host) {
            $response->assertDontSee($host, false);
        }
    }
});
