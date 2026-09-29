<?php

use App\Enums\EntryStatus;
use App\Services\Reports\EntryFilters;

const ENTRY_GROUP_KEYS = ['rent', 'groceries', 'health', 'other'];

test('parses valid filters', function () {
    $filters = EntryFilters::fromQuery(['group' => 'groceries', 'status' => 'spending', 'q' => '  tegut ', 'page' => '2'], ENTRY_GROUP_KEYS);

    expect($filters->group)->toBe('groceries')
        ->and($filters->status)->toBe(EntryStatus::Spending)
        ->and($filters->search)->toBe('tegut')
        ->and($filters->page)->toBe(2)
        ->and(EntryFilters::fromQuery(['group' => 'unassigned'], ENTRY_GROUP_KEYS)->group)->toBe('unassigned');
});

test('defaults missing values', function () {
    $filters = EntryFilters::fromQuery([], ENTRY_GROUP_KEYS);

    expect($filters->group)->toBeNull()
        ->and($filters->status)->toBe(EntryStatus::All)
        ->and($filters->search)->toBeNull()
        ->and($filters->page)->toBe(1)
        ->and($filters->isFiltered())->toBeFalse();
});

test('rejects invalid values', function (array $query) {
    expect(EntryFilters::fromQuery($query, ENTRY_GROUP_KEYS))->toBeNull();
})->with([
    'unknown group' => [['group' => 'nope']],
    'unknown status' => [['status' => 'open']],
    'page 0' => [['page' => '0']],
    'negative page' => [['page' => '-1']],
    'non-numeric page' => [['page' => 'abc']],
    'fractional page' => [['page' => '1.5']],
    'too long query' => [['q' => str_repeat('a', 101)]],
    'group array' => [['group' => ['x']]],
    'query array' => [['q' => ['x']]],
]);

test('accepts the longest query', function () {
    expect(EntryFilters::fromQuery(['q' => str_repeat('a', 100)], ENTRY_GROUP_KEYS)->search)->toBe(str_repeat('a', 100));
});

test('treats blank searches as no search', function () {
    $filters = EntryFilters::fromQuery(['q' => '   '], ENTRY_GROUP_KEYS);

    expect($filters->search)->toBeNull()
        ->and($filters->isFiltered())->toBeFalse();
});

test('builds the query string', function () {
    $full = new EntryFilters('groceries', EntryStatus::Spending, 'tegut', 2);

    expect($full->query())->toBe(['group' => 'groceries', 'status' => 'spending', 'q' => 'tegut', 'page' => 2])
        ->and((new EntryFilters)->query())->toBe([])
        ->and((new EntryFilters(status: EntryStatus::All, page: 1))->query())->toBe([])
        ->and((new EntryFilters(status: EntryStatus::Income))->query())->toBe(['status' => 'income']);
});

test('resets the page when group or status change', function () {
    $filters = new EntryFilters('groceries', EntryStatus::Spending, 'tegut', 3);

    expect($filters->withGroup('rent')->page)->toBe(1)
        ->and($filters->withStatus(EntryStatus::Income)->page)->toBe(1)
        ->and($filters->withPage(4)->query())->toBe(['group' => 'groceries', 'status' => 'spending', 'q' => 'tegut', 'page' => 4])
        ->and($filters->cleared()->query())->toBe([]);
});

test('normalizes the search like rule matching', function () {
    expect((new EntryFilters(search: 'Grasmück'))->normalizedSearch())->toBe('GRASMUECK')
        ->and((new EntryFilters(search: 'lotto hessen'))->normalizedSearch())->toBe('LOTTOHESSEN')
        ->and((new EntryFilters)->normalizedSearch())->toBeNull();
});
