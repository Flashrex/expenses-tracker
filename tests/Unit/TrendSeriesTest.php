<?php

use App\Enums\ReportMode;
use App\Services\Reports\Totals;
use App\Services\Reports\TrendSeries;

/** Five groups in the shape of config('expenses.groups'), deliberately not in sort order. */
function trendGroups(): array
{
    return [
        'takeaway' => ['name' => 'Takeaway & Fast Food', 'color' => '#f97316', 'sort' => 3],
        'rent' => ['name' => 'Rent', 'color' => '#6366f1', 'sort' => 1],
        'health' => ['name' => 'Health', 'color' => '#14b8a6', 'sort' => 4],
        'groceries' => ['name' => 'Groceries & Personal Care', 'color' => '#10b981', 'sort' => 2],
        'other' => ['name' => 'Other', 'color' => '#94a3b8', 'sort' => 10],
    ];
}

/** @param  array<string, int>  $groups */
function spent(array $groups): Totals
{
    return new Totals(array_sum($groups), 0, $groups);
}

/** @return array<string, array{key: string, name: string, color: string, values: list<int>}> */
function trendGroupsByKey(TrendSeries $series): array
{
    return array_column($series->groups, null, 'key');
}

test('builds a continuous month axis with zeros for missing months', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2026-03', '2026-06'], [
        '2026-03' => spent(['groceries' => 1000]),
        '2026-06' => spent(['groceries' => 2000]),
    ], trendGroups());

    expect($series->periods)->toBe(['2026-03', '2026-04', '2026-05', '2026-06'])
        ->and($series->labels)->toBe(['Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026'])
        ->and(trendGroupsByKey($series)['groceries']['values'])->toBe([1000, 0, 0, 2000]);
});

test('steps months across a year boundary', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2025-11', '2026-02'], [], trendGroups());

    expect($series->periods)->toBe(['2025-11', '2025-12', '2026-01', '2026-02']);
});

test('folds months into years', function () {
    $series = TrendSeries::build(ReportMode::Year, ['2025-12', '2026-07'], [
        '2025-12' => spent(['groceries' => 9999]),
        '2026-06' => spent(['groceries' => 29349, 'rent' => 38607]),
        '2026-07' => spent(['groceries' => 1000, 'rent' => 10000]),
    ], trendGroups());

    $groups = trendGroupsByKey($series);

    expect($series->periods)->toBe(['2025', '2026'])
        ->and($series->labels)->toBe(['2025', '2026'])
        ->and($groups['groceries']['values'])->toBe([9999, 30349])
        ->and($groups['rent']['values'])->toBe([0, 48607]);
});

test('fills missing years with zeros', function () {
    $series = TrendSeries::build(ReportMode::Year, ['2024-05', '2026-01'], [
        '2024-05' => spent(['other' => 500]),
        '2026-01' => spent(['other' => 700]),
    ], trendGroups());

    expect($series->periods)->toBe(['2024', '2025', '2026'])
        ->and(trendGroupsByKey($series)['other']['values'])->toBe([500, 0, 700]);
});

test('lists only groups with spending in config order', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2026-06', '2026-06'], [
        '2026-06' => spent(['takeaway' => 9000, 'rent' => 100]),
    ], trendGroups());

    expect(array_column($series->groups, 'key'))->toBe(['rent', 'takeaway'])
        ->and($series->groups[0])->toMatchArray(['name' => 'Rent', 'color' => '#6366f1'])
        ->and($series->groups[1])->toMatchArray(['name' => 'Takeaway & Fast Food', 'color' => '#f97316']);
});

test('adds unassigned after the config groups', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2026-06', '2026-06'], [
        '2026-06' => spent(['unassigned' => 500, 'rent' => 100]),
    ], trendGroups());

    expect(array_column($series->groups, 'key'))->toBe(['rent', 'unassigned'])
        ->and($series->groups[1])->toBe(['key' => 'unassigned', 'name' => 'Unassigned', 'color' => '#cbd5e1', 'values' => [500]]);
});

test('sums the groups per period', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2026-04', '2026-06'], [
        '2026-04' => spent(['rent' => 100, 'groceries' => 250]),
        '2026-06' => spent(['groceries' => 40]),
    ], trendGroups());

    expect($series->totals())->toBe([350, 0, 40]);
});

test('labels the range', function () {
    $range = fn (ReportMode $mode, string $first, string $last) => TrendSeries::build($mode, [$first, $last], [], trendGroups())->rangeLabel();

    expect($range(ReportMode::Month, '2025-06', '2026-06'))->toBe('Jun 2025 – Jun 2026')
        ->and($range(ReportMode::Month, '2026-06', '2026-06'))->toBe('Jun 2026')
        ->and($range(ReportMode::Year, '2025-12', '2026-07'))->toBe('2025 – 2026')
        ->and($range(ReportMode::Year, '2026-03', '2026-06'))->toBe('2026');
});

test('has no spending without group amounts', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2026-04', '2026-06'], [
        '2026-05' => Totals::empty(),
    ], trendGroups());

    expect($series->groups)->toBe([])
        ->and($series->hasSpending())->toBeFalse()
        ->and($series->periods)->toBe(['2026-04', '2026-05', '2026-06']);
});

test('flags imported months including months without spending', function () {
    $series = TrendSeries::build(ReportMode::Month, ['2026-03', '2026-04', '2026-06'], [
        '2026-03' => spent(['groceries' => 1000]),
        '2026-06' => spent(['groceries' => 2000]),
    ], trendGroups());

    expect($series->periods)->toBe(['2026-03', '2026-04', '2026-05', '2026-06'])
        ->and($series->imported)->toBe([true, true, false, true])
        ->and(trendGroupsByKey($series)['groceries']['values'])->toBe([1000, 0, 0, 2000]);
});

test('flags years with any imported month', function () {
    $series = TrendSeries::build(ReportMode::Year, ['2024-05', '2026-01', '2026-02'], [
        '2024-05' => spent(['other' => 500]),
        '2026-01' => spent(['other' => 700]),
    ], trendGroups());

    expect($series->periods)->toBe(['2024', '2025', '2026'])
        ->and($series->imported)->toBe([true, false, true]);
});

test('flags an imported year without spending', function () {
    $series = TrendSeries::build(ReportMode::Year, ['2025-03', '2026-06'], [
        '2026-06' => spent(['groceries' => 2000]),
    ], trendGroups());

    expect($series->imported)->toBe([true, true])
        ->and(trendGroupsByKey($series)['groceries']['values'])->toBe([0, 2000]);
});
