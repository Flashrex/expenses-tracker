<?php

use App\Enums\ReportMode;
use App\Services\Reports\ReportPeriod;

const IMPORTED = ['2025-11', '2026-03', '2026-06'];

test('resolves the latest month by default', function () {
    $period = ReportPeriod::resolve(null, null, ['2026-03', '2026-06']);

    expect($period->mode)->toBe(ReportMode::Month)
        ->and($period->value)->toBe('2026-06');
});

test('resolves a requested month or year', function () {
    $month = ReportPeriod::resolve('2026-03', null, IMPORTED);
    $year = ReportPeriod::resolve(null, '2026', IMPORTED);
    $both = ReportPeriod::resolve('2026-03', '2025', IMPORTED);

    expect([$month->mode, $month->value])->toBe([ReportMode::Month, '2026-03'])
        ->and([$year->mode, $year->value])->toBe([ReportMode::Year, '2026'])
        ->and([$both->mode, $both->value])->toBe([ReportMode::Month, '2026-03']);
});

test('rejects invalid or unknown periods', function (mixed $month, mixed $year) {
    expect(ReportPeriod::resolve($month, $year, IMPORTED))->toBeNull();
})->with([
    'month 13' => ['2026-13', null],
    'unpadded month' => ['2026-6', null],
    'date' => ['2026-06-01', null],
    'month not imported' => ['2025-06', null],
    'array month' => [['x'], null],
    'short year' => [null, '26'],
    'letters' => [null, 'abcd'],
    'year without data' => [null, '2024'],
]);

test('computes ranges and labels', function () {
    $month = ReportPeriod::month('2026-06');
    $year = ReportPeriod::year('2026');

    expect([$month->from(), $month->to(), $month->label()])->toBe(['2026-06', '2026-06', 'June 2026'])
        ->and([$year->from(), $year->to(), $year->label()])->toBe(['2026-01', '2026-12', '2026']);
});

test('finds the previous calendar period', function () {
    expect(ReportPeriod::month('2026-06')->previous()->value)->toBe('2026-05')
        ->and(ReportPeriod::month('2026-01')->previous()->value)->toBe('2025-12')
        ->and(ReportPeriod::year('2026')->previous())->toEqual(ReportPeriod::year('2025'));
});

test('steps through imported months only', function () {
    expect(ReportPeriod::month('2026-06')->earlier(IMPORTED)->value)->toBe('2026-03')
        ->and(ReportPeriod::month('2026-06')->later(IMPORTED))->toBeNull()
        ->and(ReportPeriod::month('2026-03')->earlier(IMPORTED)->value)->toBe('2025-11')
        ->and(ReportPeriod::month('2026-03')->later(IMPORTED)->value)->toBe('2026-06')
        ->and(ReportPeriod::month('2025-11')->earlier(IMPORTED))->toBeNull();
});

test('steps through imported years only', function () {
    expect(ReportPeriod::year('2026')->earlier(IMPORTED))->toEqual(ReportPeriod::year('2025'))
        ->and(ReportPeriod::year('2026')->later(IMPORTED))->toBeNull()
        ->and(ReportPeriod::year('2025')->earlier(IMPORTED))->toBeNull()
        ->and(ReportPeriod::year('2025')->later(IMPORTED))->toEqual(ReportPeriod::year('2026'));
});

test('toggles between month and year', function () {
    expect(ReportPeriod::month('2026-03')->toggled(IMPORTED))->toEqual(ReportPeriod::year('2026'))
        ->and(ReportPeriod::year('2026')->toggled(IMPORTED))->toEqual(ReportPeriod::month('2026-06'))
        ->and(ReportPeriod::year('2025')->toggled(IMPORTED))->toEqual(ReportPeriod::month('2025-11'));
});

test('builds the query', function () {
    expect(ReportPeriod::month('2026-06')->query())->toBe(['month' => '2026-06'])
        ->and(ReportPeriod::year('2026')->query())->toBe(['year' => '2026']);
});

test('knows whether a period has data', function () {
    expect(ReportPeriod::month('2026-05')->hasData(IMPORTED))->toBeFalse()
        ->and(ReportPeriod::month('2026-06')->hasData(IMPORTED))->toBeTrue()
        ->and(ReportPeriod::year('2024')->hasData(IMPORTED))->toBeFalse()
        ->and(ReportPeriod::year('2025')->hasData(IMPORTED))->toBeTrue();
});
