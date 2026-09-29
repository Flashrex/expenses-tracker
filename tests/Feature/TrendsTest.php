<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** @return array{labels: list<string>, series: list<array{key: string, name: string, color: string, values: list<int>}>} */
function trendChart(TestResponse $response): array
{
    preg_match('/data-chart="([^"]*)"/', $response->getContent(), $match);

    return json_decode(html_entity_decode($match[1]), true);
}

/** @return array<string, array{key: string, name: string, color: string, values: list<int>}> */
function trendSeriesByKey(TestResponse $response): array
{
    return array_column(trendChart($response)['series'], null, 'key');
}

function trendHeading(TestResponse $response): string
{
    preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $response->getContent(), $match);

    return trim($match[1] ?? '');
}

/** The opening tag of the Month|Year segment for $mode. */
function modeSegment(TestResponse $response, string $mode): string
{
    preg_match('/<(a|span)[^>]*data-mode="'.$mode.'"[^>]*>/', $response->getContent(), $match);

    return $match[0] ?? '';
}

test('redirects guests to login', function () {
    $this->get('/trends?by=year')->assertRedirect(route('login'));
});

test('shows the empty state without imports', function () {
    $this->actingAs($this->user)
        ->get(route('trends', ['by' => 'year']))
        ->assertOk()
        ->assertSee('No trends yet')
        ->assertSee('Upload statement')
        ->assertDontSee('data-chart', false);
});

test('defaults to month mode over the imported range', function () {
    importedMonth('2026-05', [['amount_cents' => -1000, 'group_key' => 'groceries']]);
    importedMonth('2026-06', [['amount_cents' => -2000, 'group_key' => 'groceries']]);

    $response = $this->actingAs($this->user)->get(route('trends'))->assertOk();

    expect(trendHeading($response))->toBe('May 2026 – Jun 2026')
        ->and(modeSegment($response, 'month'))->toContain('aria-current="true"');
    $response->assertSee('x-data="trendChart"', false);
});

test('passes the monthly series to the chart', function () {
    $this->actingAs($this->user);
    importFixtureStatement();
    importJuly();

    $response = $this->get(route('trends'))->assertOk();
    $chart = trendChart($response);
    $series = trendSeriesByKey($response);

    expect($chart['labels'])->toBe(['Jun 2026', 'Jul 2026'])
        ->and(array_column($chart['series'], 'key'))->toBe([
            'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies',
            'online_orders', 'takeaway', 'restaurants', 'health', 'other',
        ])
        ->and($series['rent'])->toBe(['key' => 'rent', 'name' => 'Rent', 'color' => '#6366f1', 'values' => [38607, 10000]])
        ->and($series['groceries']['values'])->toBe([29349, 1000])
        ->and($series['health']['values'])->toBe([15065, 0]);
});

test('shows months without data as zero', function () {
    importedMonth('2026-03', [['amount_cents' => -1000, 'group_key' => 'groceries']]);
    importedMonth('2026-06', [['amount_cents' => -2000, 'group_key' => 'groceries']]);

    $response = $this->actingAs($this->user)->get(route('trends'))->assertOk();

    expect(trendChart($response)['labels'])->toBe(['Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026'])
        ->and(trendSeriesByKey($response)['groceries']['values'])->toBe([1000, 0, 0, 2000])
        ->and(trendHeading($response))->toBe('Mar 2026 – Jun 2026');
});

test('aggregates per year', function () {
    $this->actingAs($this->user);
    importedMonth('2025-12', [['amount_cents' => -9999, 'group_key' => 'groceries']]);
    importFixtureStatement();
    importJuly();

    $response = $this->get(route('trends', ['by' => 'year']))->assertOk();
    $series = trendSeriesByKey($response);

    expect(trendChart($response)['labels'])->toBe(['2025', '2026'])
        ->and($series['groceries']['values'])->toBe([9999, 30349])
        ->and($series['rent']['values'])->toBe([0, 48607])
        ->and(trendHeading($response))->toBe('2025 – 2026')
        ->and(modeSegment($response, 'year'))->toContain('aria-current="true"');
    $response->assertSee('per year', false);
});

test('shows years without data as zero', function () {
    importedMonth('2024-05', [['amount_cents' => -500, 'group_key' => 'other']]);
    importedMonth('2026-01', [['amount_cents' => -700, 'group_key' => 'other']]);

    $response = $this->actingAs($this->user)->get(route('trends', ['by' => 'year']))->assertOk();

    expect(trendChart($response)['labels'])->toBe(['2024', '2025', '2026'])
        ->and(trendSeriesByKey($response)['other']['values'])->toBe([500, 0, 700]);
});

test('links the mode toggle', function () {
    importedMonth('2026-06', [['amount_cents' => -1000, 'group_key' => 'groceries']]);
    $this->actingAs($this->user);

    expect(modeSegment($this->get(route('trends')), 'year'))
        ->toStartWith('<a')
        ->toContain('href="'.e(route('trends', ['by' => 'year'])).'"')
        ->and(modeSegment($this->get(route('trends', ['by' => 'year'])), 'month'))
        ->toStartWith('<a')
        ->toContain('href="'.route('trends').'"');
});

test('redirects unknown modes to month mode', function (array $query) {
    importedMonth('2026-06');

    $this->actingAs($this->user)->get(route('trends', $query))->assertRedirect(route('trends'));
})->with([
    'month' => [['by' => 'month']],
    'week' => [['by' => 'week']],
    'array' => [['by' => ['x']]],
]);

test('treats an empty mode as month mode', function () {
    importedMonth('2026-06', [['amount_cents' => -1000, 'group_key' => 'groceries']]);

    $response = $this->actingAs($this->user)->get('/trends?by=')->assertOk();

    expect(modeSegment($response, 'month'))->toContain('aria-current="true"');
});

test('hints at importing a second month', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(route('trends'))->assertOk()->assertSee('Import another month to compare trends.');

    expect(trendHeading($response))->toBe('Jun 2026')
        ->and(trendChart($response)['labels'])->toBe(['Jun 2026']);
});

test('shows no hint with several months in one year', function () {
    importedMonth('2026-05', [['amount_cents' => -1000, 'group_key' => 'groceries']]);
    importedMonth('2026-06', [['amount_cents' => -1000, 'group_key' => 'groceries']]);

    $response = $this->actingAs($this->user)->get(route('trends', ['by' => 'year']))
        ->assertOk()
        ->assertDontSee('data-single-period', false);

    expect(trendHeading($response))->toBe('2026')
        ->and(trendChart($response)['labels'])->toBe(['2026']);
});

test('charts spending only', function () {
    importedMonth('2026-06', [
        ['amount_cents' => -1000, 'group_key' => 'groceries'],
        ['amount_cents' => 5000, 'direction' => 'in', 'group_key' => null],
        ['amount_cents' => 51540, 'direction' => 'in', 'ignored' => true],
        ['amount_cents' => -4000, 'group_key' => 'other', 'ignored' => true],
    ]);

    $chart = trendChart($this->actingAs($this->user)->get(route('trends')));

    expect(array_column($chart['series'], 'key'))->toBe(['groceries'])
        ->and($chart['series'][0]['values'])->toBe([1000]);
});

test('shows no chart without spending', function () {
    importedMonth('2026-06', [['amount_cents' => 5000, 'direction' => 'in']]);

    $this->actingAs($this->user)->get(route('trends'))
        ->assertOk()
        ->assertSee('data-no-spending', false)
        ->assertSee('No spending in Jun 2026')
        ->assertDontSee('data-chart', false)
        ->assertSee('data-single-period', false);
});

test('includes unassigned spending', function () {
    importedMonth('2026-06', [['amount_cents' => -700, 'group_key' => null]]);

    $chart = trendChart($this->actingAs($this->user)->get(route('trends')));

    expect($chart['series'])->toBe([
        ['key' => 'unassigned', 'name' => 'Unassigned', 'color' => '#cbd5e1', 'values' => [700]],
    ]);
});
