<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** The markup of one KPI tile, up to its value paragraph. */
function kpiTile(TestResponse $response, string $kpi): string
{
    preg_match('/data-kpi="'.$kpi.'".*?<\/p>/s', $response->getContent(), $match);

    return $match[0] ?? '';
}

/** @return list<string> group keys of the comparison list, in order */
function groupRowKeys(TestResponse $response): array
{
    preg_match_all('/data-group-row="([a-z_]+)"/', $response->getContent(), $matches);

    return $matches[1];
}

function groupRow(TestResponse $response, string $key): string
{
    preg_match('/data-group-row="'.$key.'".*?<\/li>/s', $response->getContent(), $match);

    return $match[0] ?? '';
}

/** The opening tag of the element carrying $attribute (e.g. data-nav="prev"). */
function tagWith(TestResponse $response, string $attribute): string
{
    preg_match('/<(a|span)\b[^>]*'.preg_quote($attribute, '/').'[^>]*>/', $response->getContent(), $match);

    return $match[0] ?? '';
}

test('redirects guests to login', function () {
    $this->get('/overview?month=2026-06')->assertRedirect(route('login'));
});

test('shows the empty state without imports', function () {
    $this->actingAs($this->user)
        ->get(route('overview', ['month' => '2026-06']))
        ->assertOk()
        ->assertSee('No data yet')
        ->assertSee('Upload statement')
        ->assertDontSee('data-kpi', false);
});

test('defaults to the latest imported month', function () {
    importedMonth('2026-05');
    importedMonth('2026-06');

    $response = $this->actingAs($this->user)->get(route('overview'))->assertOk();

    expect($response->getContent())->toMatch('/<h1[^>]*>June 2026<\/h1>/')
        ->and(tagWith($response, 'data-mode="month"'))->toContain('aria-current="true"');
});

test('shows the June fixture totals', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(route('overview', ['month' => '2026-06']))->assertOk();

    expect(kpiTile($response, 'spent'))->toContain('1.686,28 €')
        ->and(kpiTile($response, 'income'))->toContain('1.348,19 €')
        ->and(kpiTile($response, 'net'))->toContain('−338,09 €')->toContain('text-rose-600')
        ->and(groupRowKeys($response))->toBe([
            'rent', 'groceries', 'other', 'online_orders', 'health',
            'utilities', 'restaurants', 'hobbies', 'subscriptions', 'takeaway',
        ])
        ->and(groupRow($response, 'rent'))->toContain('386,07 €')->toContain('22,9 %');

    $response->assertSee('data-no-comparison', false)
        ->assertSee('No data for May 2026 to compare')
        ->assertDontSee('data-trend', false);
});

test('passes the donut data to the chart', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(route('overview', ['month' => '2026-06']));
    preg_match('/data-chart="([^"]*)"/', $response->getContent(), $match);
    $chart = json_decode(html_entity_decode($match[1]), true);

    expect($chart['total'])->toBe('1.686,28 €')
        ->and($chart['label'])->toBe('Spent')
        ->and($chart['slices'])->toHaveCount(10)
        ->and($chart['slices'][0])->toBe(['key' => 'rent', 'name' => 'Rent', 'value' => 38607, 'color' => '#7f81d5', 'amount' => '386,07 €']);
    $response->assertSee('x-data="donutChart"', false);
});

test('aggregates the whole year', function () {
    $this->actingAs($this->user);
    importFixtureStatement();
    importJuly();

    $response = $this->get(route('overview', ['year' => '2026']))->assertOk();

    expect($response->getContent())->toMatch('/<h1[^>]*>2026<\/h1>/')
        ->and(kpiTile($response, 'spent'))->toContain('1.796,28 €')
        ->and(kpiTile($response, 'income'))->toContain('1.398,19 €')
        ->and(kpiTile($response, 'net'))->toContain('−398,09 €')
        ->and(tagWith($response, 'data-mode="year"'))->toContain('aria-current="true"');
    $response->assertSee('No data for 2025 to compare');
});

test('compares groups with the previous month', function () {
    importedMonth('2026-05', [
        ['amount_cents' => -10000, 'group_key' => 'groceries'],
        ['amount_cents' => -10000, 'group_key' => 'takeaway'],
        ['amount_cents' => -5000, 'group_key' => 'hobbies'],
    ]);
    importedMonth('2026-06', [
        ['amount_cents' => -12000, 'group_key' => 'groceries'],
        ['amount_cents' => -8000, 'group_key' => 'takeaway'],
        ['amount_cents' => -3000, 'group_key' => 'health'],
    ]);

    $response = $this->actingAs($this->user)->get(route('overview', ['month' => '2026-06']))->assertOk();

    expect(groupRowKeys($response))->toBe(['groceries', 'takeaway', 'health', 'hobbies'])
        ->and(groupRow($response, 'groceries'))->toContain('data-trend="up"')->toContain('↑ 20 %')
        ->and(groupRow($response, 'takeaway'))->toContain('data-trend="down"')->toContain('↓ 20 %')
        ->and(groupRow($response, 'health'))->toContain('data-trend="new"')->toMatch('/data-delta[^>]*>\s*new\s*</')
        ->and(groupRow($response, 'hobbies'))->toContain('data-trend="down"')->toContain('↓ 100 %')->toContain('0,00 €');
    $response->assertSee('vs May 2026')->assertDontSee('data-no-comparison', false);
});

test('compares the year with the previous year', function () {
    importedMonth('2025-06', [['amount_cents' => -10000, 'group_key' => 'groceries']]);
    importedMonth('2026-06', [['amount_cents' => -15000, 'group_key' => 'groceries']]);

    $response = $this->actingAs($this->user)->get(route('overview', ['year' => '2026']))->assertOk();

    expect(groupRow($response, 'groceries'))->toContain('data-trend="up"')->toContain('↑ 50 %');
    $response->assertSee('vs 2025');
});

test('links the arrows to imported months only', function () {
    importedMonth('2025-11');
    importedMonth('2026-03');
    importedMonth('2026-06');
    $this->actingAs($this->user);

    $march = $this->get(route('overview', ['month' => '2026-03']));
    $june = $this->get(route('overview', ['month' => '2026-06']));
    $november = $this->get(route('overview', ['month' => '2025-11']));

    expect(tagWith($march, 'data-nav="prev"'))->toStartWith('<a')->toContain('href="'.e(route('overview', ['month' => '2025-11'])).'"')
        ->and(tagWith($march, 'data-nav="next"'))->toStartWith('<a')->toContain('href="'.e(route('overview', ['month' => '2026-06'])).'"')
        ->and(tagWith($june, 'data-nav="next"'))->toStartWith('<span')->toContain('aria-disabled="true"')
        ->and(tagWith($november, 'data-nav="prev"'))->toStartWith('<span')->toContain('aria-disabled="true"');
});

test('links the arrows to imported years', function () {
    importedMonth('2025-11');
    importedMonth('2026-03');
    importedMonth('2026-06');
    $this->actingAs($this->user);

    $current = $this->get(route('overview', ['year' => '2026']));
    $previous = $this->get(route('overview', ['year' => '2025']));

    expect(tagWith($current, 'data-nav="prev"'))->toContain('href="'.e(route('overview', ['year' => '2025'])).'"')
        ->and(tagWith($current, 'data-nav="next"'))->toContain('aria-disabled="true"')
        ->and(tagWith($previous, 'data-nav="prev"'))->toContain('aria-disabled="true"')
        ->and(tagWith($previous, 'data-nav="next"'))->toContain('href="'.e(route('overview', ['year' => '2026'])).'"');
});

test('links the mode toggle', function () {
    importedMonth('2025-11');
    importedMonth('2026-03');
    importedMonth('2026-06');
    $this->actingAs($this->user);

    expect(tagWith($this->get(route('overview', ['month' => '2026-03'])), 'data-mode="year"'))
        ->toContain('href="'.e(route('overview', ['year' => '2026'])).'"')
        ->and(tagWith($this->get(route('overview', ['year' => '2026'])), 'data-mode="month"'))
        ->toContain('href="'.e(route('overview', ['month' => '2026-06'])).'"')
        ->and(tagWith($this->get(route('overview', ['year' => '2025'])), 'data-mode="month"'))
        ->toContain('href="'.e(route('overview', ['month' => '2025-11'])).'"');
});

test('redirects invalid periods to the default', function (array $query) {
    importedMonth('2026-06');

    $this->actingAs($this->user)->get(route('overview', $query))->assertRedirect(route('overview'));
})->with([
    'month 13' => [['month' => '2026-13']],
    'month not imported' => [['month' => '2025-01']],
    'letters as year' => [['year' => 'abcd']],
    'year without data' => [['year' => '2024']],
    'array month' => [['month' => ['x']]],
]);

test('shows no donut without spending', function () {
    importedMonth('2026-06', [['amount_cents' => 5000, 'direction' => 'in']]);

    $response = $this->actingAs($this->user)->get(route('overview'))->assertOk();

    $response->assertSee('data-no-spending', false)
        ->assertSee('No spending in June 2026')
        ->assertDontSee('data-chart', false);
    expect(kpiTile($response, 'income'))->toContain('50,00 €')
        ->and(kpiTile($response, 'net'))->toContain('+50,00 €')->toContain('text-emerald-600');
});

test('excludes ignored entries on the page', function () {
    importedMonth('2026-06', [
        ['amount_cents' => -1000, 'group_key' => 'groceries'],
        ['amount_cents' => 51540, 'direction' => 'in', 'ignored' => true],
    ]);

    $response = $this->actingAs($this->user)->get(route('overview'))->assertOk();

    expect(kpiTile($response, 'income'))->toContain('0,00 €')
        ->and(kpiTile($response, 'spent'))->toContain('10,00 €');
});
