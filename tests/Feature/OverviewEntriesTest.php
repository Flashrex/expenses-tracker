<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** @return list<string> ids of the entry rows, in order */
function entryIds(TestResponse $response): array
{
    preg_match_all('/data-entry="([^"]+)"/', $response->getContent(), $matches);

    return $matches[1];
}

/** The decoded markup of one entry row. */
function entryRow(TestResponse $response, string $id): string
{
    preg_match('/data-entry="'.$id.'".*?<\/li>/s', $response->getContent(), $match);

    return html_entity_decode($match[0] ?? '');
}

/** The opening tag of the first element carrying $attribute. */
function entriesTag(TestResponse $response, string $attribute): string
{
    preg_match('/<(a|span|li)\b[^>]*'.preg_quote($attribute, '/').'[^>]*>/', $response->getContent(), $match);

    return $match[0] ?? '';
}

/** @return list<string> */
function monthHeadings(TestResponse $response): array
{
    preg_match_all('/data-month-heading[^>]*>([^<]+)</', $response->getContent(), $matches);

    return array_map('trim', $matches[1]);
}

function june(array $query = []): string
{
    return route('overview', ['month' => '2026-06'] + $query);
}

test('shows the entries card with the first page', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june())->assertOk()->assertSee('Entries')->assertSee('1–50 of 69');

    expect(entryIds($response))->toHaveCount(50)
        ->and(entriesTag($response, 'data-page="2"'))->toContain('page=2')
        ->and($response->getContent())->not->toContain('data-month-heading');
});

test('shows the second page', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['page' => 2]))->assertOk()->assertSee('51–69 of 69')->assertSee('Netflix + Router');

    expect(entryIds($response))->toHaveCount(19)
        ->and(entriesTag($response, 'data-page="1"'))->toStartWith('<a')
        ->and($response->getContent())->toMatch('/aria-current="page"[^>]*>2</');
});

test('redirects pages beyond the last', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $this->get(june(['page' => 9]))->assertRedirect(june(['page' => 2]));
    $this->get(june(['group' => 'health', 'page' => 3]))->assertRedirect(june(['group' => 'health']));
});

test('redirects invalid filters to the unfiltered card', function (array $query) {
    $this->actingAs($this->user);
    importFixtureStatement();

    $this->get(june($query))->assertRedirect(june());
})->with([
    'unknown group' => [['group' => 'nope']],
    'unknown status' => [['status' => 'open']],
    'page 0' => [['page' => '0']],
    'non-numeric page' => [['page' => 'abc']],
    'too long query' => [['q' => str_repeat('a', 101)]],
    'group array' => [['group' => ['x']]],
]);

test('renders shared amounts with the full bank amount', function () {
    $this->actingAs($this->user);
    importFixtureStatement();
    $rent = Transaction::where('merchant', 'Miete')->where('direction', 'out')->first();

    $row = entryRow($this->get(june(['page' => 2])), $rent->id);

    expect($row)->toContain('−386,07 €')
        ->toContain('÷3')
        ->toMatch('/data-full-amount[^>]*>−1.158,20 €</')
        ->toMatch('/data-purpose[^>]*>Miete</')
        ->toMatch('/data-grouped-by[^>]*>Rule · purpose contains "Miete"</')
        ->toContain('−386,07 € (−1.158,20 € / 3)')
        ->toContain('01.06.2026');
});

test('renders ignored entries dimmed', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['status' => 'ignored']))->assertOk()->assertSee('+515,40 €');
    preg_match_all('/<li data-entry[^>]*>/', $response->getContent(), $rows);

    expect($rows[0])->toHaveCount(3)->each->toContain('opacity-50');

    foreach (entryIds($response) as $id) {
        expect(entryRow($response, $id))->toMatch('/data-amount class="[^"]*line-through/')->toContain('ignored</span>');
    }
});

test('renders income in green without a chip', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['status' => 'income']));
    $ids = entryIds($response);
    $row = entryRow($response, $ids[0]);

    expect($ids)->toHaveCount(1)
        ->and($row)->toMatch('/data-amount class="[^"]*text-emerald-600[^"]*">\+1.348,19 €</')
        ->not->toContain('data-full-amount')
        ->not->toContain('data-unassigned')
        ->toMatch('/data-grouped-by[^>]*>Not grouped</');
});

test('shows picked manually entries', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['q' => 'echtzeit']));
    $ids = entryIds($response);

    expect($ids)->toHaveCount(1)
        ->and(entryRow($response, $ids[0]))->toContain('Picked manually')->toContain('−200,00 €');
});

test('filters by group chip and marks it active', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['group' => 'groceries']))->assertSee('1–27 of 27');
    $chip = entriesTag($response, 'data-group-filter="groceries"');

    expect($chip)->toContain('aria-current="true"')
        ->toContain('href="'.june().'#entries"')
        ->and(entriesTag($response, 'data-group-filter="rent"'))->toStartWith('<a')->not->toContain('aria-current');
});

test('searches entries', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $this->get(june(['q' => 'tegut']))->assertSee('1–14 of 14')->assertSee('value="tegut"', false);
    $this->get(june(['q' => 'takeaway']))->assertSee('1–2 of 2');
    $this->get('/overview?month=2026-06&q=lotto%20hessen')->assertSee('1–1 of 1');
});

test('shows the empty filter state', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['status' => 'income', 'group' => 'groceries']))
        ->assertSee('No entries match these filters')
        ->assertDontSee('data-entries-count', false);

    expect(entriesTag($response, 'data-clear-filters'))->toContain('href="'.june().'#entries"');
});

test('groups the year under month headings', function () {
    $this->actingAs($this->user);
    importFixtureStatement();
    importJuly();

    $first = $this->get(route('overview', ['year' => '2026']))->assertSee('1–50 of 72');
    $second = $this->get(route('overview', ['year' => '2026', 'page' => 2]))->assertSee('51–72 of 72');

    expect(monthHeadings($first))->toBe(['July 2026', 'June 2026'])
        ->and(monthHeadings($second))->toBe(['June 2026']);
});

test('keeps the KPIs unfiltered', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june(['group' => 'groceries', 'q' => 'tegut']))->assertOk();
    preg_match('/data-kpi="spent".*?<\/p>/s', $response->getContent(), $spent);

    expect($spent[0])->toContain('1.686,28 €')
        ->and(substr_count($response->getContent(), 'data-group-row='))->toBe(10);
});

test('resets filters on period change', function () {
    $this->actingAs($this->user);
    importedMonth('2026-05');
    importFixtureStatement();

    $response = $this->get(june(['group' => 'groceries', 'q' => 'tegut', 'page' => 1]))->assertOk();

    expect(entriesTag($response, 'data-nav="prev"'))->toContain('href="'.route('overview', ['month' => '2026-05']).'"')
        ->and(entriesTag($response, 'data-mode="year"'))->toContain('href="'.route('overview', ['year' => '2026']).'"');
});

test('links group rows and donut slices to the filter', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $response = $this->get(june());
    preg_match('/data-group-row="rent".*?<\/li>/s', $response->getContent(), $row);
    preg_match('/data-chart="([^"]*)"/', $response->getContent(), $chart);

    expect($row[0])->toContain('data-entries-group="rent"')
        ->toContain('href="'.e(june(['group' => 'rent'])).'#entries"')
        ->and(json_decode(html_entity_decode($chart[1]), true)['slices'][0]['key'])->toBe('rent');
});

test('hides the card on the empty overview', function () {
    $this->actingAs($this->user)->get(route('overview'))->assertOk()->assertDontSee('id="entries"', false);
});

test('returns only the card body as a fragment', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $this->get(route('overview.entries', ['month' => '2026-06', 'page' => 2]))
        ->assertOk()
        ->assertSee('data-entries-fragment', false)
        ->assertSee('51–69 of 69')
        ->assertDontSee('<html', false)
        ->assertDontSee('data-kpi', false)
        ->assertDontSee('donutChart', false)
        ->assertDontSee('Entries</h2>', false);
});

test('redirects invalid fragment filters', function () {
    $this->actingAs($this->user);
    importFixtureStatement();

    $this->get(route('overview.entries', ['month' => '2026-06', 'status' => 'x']))
        ->assertRedirect(route('overview.entries', ['month' => '2026-06']));
    $this->get(route('overview.entries', ['month' => '2026-06', 'page' => 9]))
        ->assertRedirect(route('overview.entries', ['month' => '2026-06', 'page' => 2]));
});

test('rejects fragment requests without data', function () {
    $this->actingAs($this->user);

    $this->get(route('overview.entries', ['month' => '2026-06']))->assertNotFound();

    importFixtureStatement();

    $this->get(route('overview.entries', ['month' => '2019-01']))->assertNotFound();
});

test('redirects guests from the fragment', function () {
    $this->get('/overview/entries?month=2026-06')->assertRedirect(route('login'));
});
