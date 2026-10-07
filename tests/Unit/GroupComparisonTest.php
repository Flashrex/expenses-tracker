<?php

use App\Services\Reports\GroupComparison;
use App\Services\Reports\Totals;

/** Four groups in the shape of config('expenses.groups'). */
function comparisonGroups(): array
{
    return [
        'rent' => ['name' => 'Rent', 'color' => '#6366f1', 'sort' => 1],
        'groceries' => ['name' => 'Groceries', 'color' => '#22c55e', 'sort' => 3],
        'hobbies' => ['name' => 'Hobbies', 'color' => '#f59e0b', 'sort' => 5],
        'takeaway' => ['name' => 'Takeaway & Fast Food', 'color' => '#f97316', 'sort' => 7],
        'health' => ['name' => 'Health', 'color' => '#ef4444', 'sort' => 9],
    ];
}

/** @param  array<string, int>  $groups */
function spending(array $groups): Totals
{
    return new Totals(array_sum($groups), 0, $groups);
}

/** @return array<string, GroupComparison> */
function comparisonByKey(array $rows): array
{
    return array_column($rows, null, 'key');
}

test('compares groups with the previous period', function () {
    $rows = GroupComparison::rows(
        spending(['groceries' => 12000, 'takeaway' => 8000, 'health' => 3000]),
        spending(['groceries' => 10000, 'takeaway' => 10000, 'hobbies' => 5000]),
        comparisonGroups(),
    );
    $byKey = comparisonByKey($rows);

    expect(array_keys($byKey))->toBe(['groceries', 'takeaway', 'health', 'hobbies'])
        ->and([$byKey['groceries']->trend, $byKey['groceries']->deltaPercent])->toBe(['up', 20])
        ->and([$byKey['takeaway']->trend, $byKey['takeaway']->deltaPercent])->toBe(['down', 20])
        ->and([$byKey['health']->trend, $byKey['health']->deltaPercent])->toBe(['new', null])
        ->and([$byKey['hobbies']->cents, $byKey['hobbies']->trend, $byKey['hobbies']->deltaPercent])->toBe([0, 'down', 100]);
});

test('marks unchanged groups', function () {
    [$row] = GroupComparison::rows(spending(['rent' => 38607]), spending(['rent' => 38607]), comparisonGroups());

    expect([$row->trend, $row->deltaPercent])->toBe(['same', 0]);
});

test('omits deltas without previous data', function () {
    $rows = GroupComparison::rows(spending(['groceries' => 12000, 'health' => 3000]), null, comparisonGroups());

    expect(array_map(fn (GroupComparison $row) => [$row->key, $row->previousCents, $row->trend, $row->deltaPercent], $rows))
        ->toBe([['groceries', null, null, null], ['health', null, null, null]]);
});

test('hides groups without spending in both periods', function () {
    $rows = GroupComparison::rows(spending(['groceries' => 12000]), spending(['takeaway' => 500]), comparisonGroups());

    expect(array_keys(comparisonByKey($rows)))->toBe(['groceries', 'takeaway']);
});

test('computes the share of spent', function () {
    $byKey = comparisonByKey(GroupComparison::rows(spending(['groceries' => 7500, 'takeaway' => 2500]), null, comparisonGroups()));

    expect($byKey['groceries']->share)->toBe(75.0)
        ->and($byKey['takeaway']->share)->toBe(25.0);
});

test('orders by amount then previous amount then config order', function () {
    $groups = [
        'a' => ['name' => 'A', 'color' => '#000000', 'sort' => 1],
        'b' => ['name' => 'B', 'color' => '#000000', 'sort' => 2],
        'c' => ['name' => 'C', 'color' => '#000000', 'sort' => 3],
    ];

    $byAmount = GroupComparison::rows(spending(['a' => 100, 'b' => 100]), spending(['a' => 50, 'b' => 80, 'c' => 90]), $groups);
    $byConfig = GroupComparison::rows(spending(['b' => 100, 'a' => 100]), spending(['a' => 50, 'b' => 50]), $groups);

    expect(array_column($byAmount, 'key'))->toBe(['b', 'a', 'c'])
        ->and(array_column($byConfig, 'key'))->toBe(['a', 'b']);
});

test('has no unassigned row', function () {
    expect(GroupComparison::rows(spending(['unassigned' => 500]), null, comparisonGroups()))->toBe([]);
});
