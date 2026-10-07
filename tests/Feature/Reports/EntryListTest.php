<?php

use App\Enums\EntryStatus;
use App\Models\Rule;
use App\Models\User;
use App\Services\Reports\EntryFilters;
use App\Services\Reports\EntryList;
use App\Services\Reports\EntryPage;
use App\Services\Reports\EntryRow;
use App\Services\Reports\ReportPeriod;

function juneEntries(EntryFilters $filters = new EntryFilters): EntryPage
{
    return app(EntryList::class)->page(ReportPeriod::month('2026-06'), $filters);
}

/** @return list<EntryRow> every June entry, across both pages */
function allJuneRows(): array
{
    return [...juneEntries()->rows, ...juneEntries(new EntryFilters(page: 2))->rows];
}

/** @return list<string> */
function merchantsOf(EntryPage $page): array
{
    return array_map(fn (EntryRow $row) => $row->merchant, $page->rows);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('lists all entries of the month newest first', function () {
    importFixtureStatement();

    $page = juneEntries();

    expect($page->total)->toBe(69)
        ->and($page->rows)->toHaveCount(50)
        ->and($page->rows[0]->merchant)->toBe('Abschluss')
        ->and($page->rows[49]->merchant)->toBe('Bargeldauszahlung VISA Card SPARKASSE FULDA');
});

test('returns the second page', function () {
    importFixtureStatement();

    $page = juneEntries(new EntryFilters(page: 2));

    expect($page->rows)->toHaveCount(19)
        ->and($page->rows[0]->merchant)->toBe('STEAM GAMES')
        ->and($page->rows[17]->merchant)->toBe('Miete')
        ->and($page->rows[18]->merchant)->toBe('Netflix + Router');
});

test('returns no rows beyond the last page', function () {
    importFixtureStatement();

    $page = juneEntries(new EntryFilters(page: 3));

    expect($page->rows)->toBe([])
        ->and($page->total)->toBe(69)
        ->and($page->lastPage())->toBe(2);
});

test('lists every month of a year', function () {
    importFixtureStatement();
    importJuly();
    importedMonth('2025-12', [['amount_cents' => -999, 'group_key' => 'groceries']]);

    $page = app(EntryList::class)->page(ReportPeriod::year('2026'), new EntryFilters);
    $periods = array_map(fn (EntryRow $row) => $row->period, $page->rows);

    expect($page->total)->toBe(72)
        ->and(array_slice($periods, 0, 4))->toBe(['2026-07', '2026-07', '2026-07', '2026-06'])
        ->and($periods)->not->toContain('2025-12');
});

test('orders by period, booking date and insertion', function () {
    importedMonth('2026-07', [
        ['booked_on' => '2026-06-30', 'merchant' => 'A'],
        ['booked_on' => '2026-07-02', 'merchant' => 'B'],
        ['booked_on' => '2026-07-02', 'merchant' => 'C'],
    ]);
    importedMonth('2026-06', [['booked_on' => '2026-06-30', 'merchant' => 'D']]);

    $page = app(EntryList::class)->page(ReportPeriod::year('2026'), new EntryFilters);

    expect(merchantsOf($page))->toBe(['C', 'B', 'A', 'D']);
});

test('filters by group', function () {
    importFixtureStatement();

    $page = juneEntries(new EntryFilters(group: 'groceries'));

    expect($page->total)->toBe(27)
        ->and(array_unique(array_map(fn (EntryRow $row) => $row->groupKey, $page->rows)))->toBe(['groceries']);
});

test('filters by status', function (EntryStatus $status, int $total) {
    importFixtureStatement();

    expect(juneEntries(new EntryFilters(status: $status))->total)->toBe($total);
})->with([
    'spending' => [EntryStatus::Spending, 65],
    'income' => [EntryStatus::Income, 1],
    'ignored' => [EntryStatus::Ignored, 3],
    'all' => [EntryStatus::All, 69],
]);

test('finds the salary as the only income', function () {
    importFixtureStatement();

    expect(merchantsOf(juneEntries(new EntryFilters(status: EntryStatus::Income))))->toBe(['CGS Clinical Guideline Serv ices']);
});

test('searches normalized text', function (string $search, int $total) {
    importFixtureStatement();

    expect(juneEntries(new EntryFilters(search: $search))->total)->toBe($total);
})->with([
    'tegut' => ['tegut', 14],
    'spaced merchant text' => ['takeaway', 2],
    'space in stored text' => ['lotto hessen', 1],
    'umlaut' => ['Grasmück', 3],
    'umlaut in query' => ['überweisung', 1],
    'folded umlaut' => ['ueberweisung', 1],
    'uppercase' => ['TEGUT', 14],
]);

test('escapes regex characters in searches', function () {
    importFixtureStatement();

    // Both Amazon counterparties contain a literal ".*" ("VISA WWW.AMAZON.* NL8J71384"); unescaped, ".*" would match all 69.
    expect(juneEntries(new EntryFilters(search: 'www.amazon'))->total)->toBe(2)
        ->and(juneEntries(new EntryFilters(search: '.*'))->total)->toBe(2)
        ->and(juneEntries(new EntryFilters(search: '.+'))->total)->toBe(0);
});

test('combines filters', function () {
    importFixtureStatement();

    expect(juneEntries(new EntryFilters(group: 'groceries', search: 'tegut'))->total)->toBe(14)
        ->and(juneEntries(new EntryFilters(status: EntryStatus::Ignored, search: 'miete'))->total)->toBe(2)
        ->and(juneEntries(new EntryFilters(status: EntryStatus::Spending, search: 'miete'))->total)->toBe(1)
        ->and(juneEntries(new EntryFilters(group: 'groceries', status: EntryStatus::Income))->total)->toBe(0);
});

test('includes ignored entries flagged', function () {
    importFixtureStatement();

    $rows = juneEntries(new EntryFilters(status: EntryStatus::Ignored))->rows;

    expect($rows)->toHaveCount(3)
        ->each(fn ($row) => $row->ignored->toBeTrue()->and($row->value->countedCents)->toBe($row->value->amountCents));
});

test('computes the counted share', function () {
    importFixtureStatement();

    $rent = collect(allJuneRows())->first(fn (EntryRow $row) => $row->merchant === 'Miete' && $row->amountCents < 0);

    expect($rent->amountCents)->toBe(-115820)
        ->and($rent->shareDivisor)->toBe(3)
        ->and($rent->countedCents)->toBe(-38607)
        ->and($rent->isShared())->toBeTrue();
});

test('explains how entries were grouped', function () {
    importFixtureStatement();
    $manual = Rule::factory()->manual()->withCondition('merchant', 'contains', 'Kiosk Nord')->create(['group_key' => 'takeaway', 'position' => null]);
    $removed = Rule::factory()->create();
    $removedId = $removed->id;
    $removed->delete();
    importedMonth('2026-07', [
        ['merchant' => 'Kiosk Nord', 'group_key' => 'takeaway', 'rule_id' => $manual->id],
        ['merchant' => 'Gone', 'group_key' => 'other', 'rule_id' => $removedId],
    ]);

    $june = collect(allJuneRows());
    $july = collect(app(EntryList::class)->page(ReportPeriod::month('2026-07'), new EntryFilters)->rows)->keyBy('merchant');
    $groupedBy = fn (callable $match) => $june->first($match)->groupedBy;

    expect($groupedBy(fn (EntryRow $row) => $row->merchant === 'Miete' && $row->amountCents < 0))->toBe('Rule · purpose contains "Miete"')
        ->and($groupedBy(fn (EntryRow $row) => $row->merchant === 'Echtzeitüberweisung'))->toBe('Picked manually')
        ->and($groupedBy(fn (EntryRow $row) => $row->merchant === 'CGS Clinical Guideline Serv ices'))->toBe('Not grouped')
        ->and($groupedBy(fn (EntryRow $row) => $row->merchant === 'Miete' && $row->ignored))->toBe('Rule · purpose contains "Miete"')
        ->and($july['Kiosk Nord']->groupedBy)->toBe('Manual rule · merchant contains "Kiosk Nord"')
        ->and($july['Gone']->groupedBy)->toBe('Rule (since removed)');
});
