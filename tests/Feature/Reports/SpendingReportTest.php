<?php

use App\Models\User;
use App\Services\Reports\SpendingReport;
use App\Services\Reports\Totals;

test('aggregates the June fixture', function () {
    $this->actingAs(User::factory()->create());
    importFixtureStatement();

    $totals = app(SpendingReport::class)->totals('2026-06', '2026-06');

    expect($totals->spentCents)->toBe(168628)
        ->and($totals->incomeCents)->toBe(134819)
        ->and($totals->netCents())->toBe(-33809)
        ->and($totals->groupCents)->toEqualCanonicalizing([
            'rent' => 38607, 'utilities' => 12934, 'groceries' => 29349, 'subscriptions' => 7145, 'hobbies' => 7420,
            'online_orders' => 15835, 'takeaway' => 6118, 'restaurants' => 9210, 'health' => 15065, 'other' => 26945,
        ]);
});

test('divides shared costs by their share', function () {
    importedMonth('2026-06', [['amount_cents' => -115820, 'group_key' => 'rent', 'share_divisor' => 3]]);

    $totals = app(SpendingReport::class)->totals('2026-06', '2026-06');

    expect($totals->groupCents)->toBe(['rent' => 38607])
        ->and($totals->spentCents)->toBe(38607);
});

test('rounds each shared entry before summing', function () {
    importedMonth('2026-06', [
        ['amount_cents' => -100, 'group_key' => 'utilities', 'share_divisor' => 3],
        ['amount_cents' => -100, 'group_key' => 'utilities', 'share_divisor' => 3],
    ]);

    expect(app(SpendingReport::class)->totals('2026-06', '2026-06')->groupCents)->toBe(['utilities' => 66]);
});

test('excludes ignored entries', function () {
    importedMonth('2026-06', [
        ['amount_cents' => -5000, 'group_key' => 'other', 'ignored' => true],
        ['amount_cents' => 51540, 'direction' => 'in', 'ignored' => true],
    ]);

    expect(app(SpendingReport::class)->totals('2026-06', '2026-06'))->toEqual(Totals::empty());
});

test('counts income without grouping it', function () {
    importedMonth('2026-06', [
        ['amount_cents' => 134819, 'direction' => 'in'],
        ['amount_cents' => 1999, 'direction' => 'in'],
    ]);

    $totals = app(SpendingReport::class)->totals('2026-06', '2026-06');

    expect([$totals->incomeCents, $totals->spentCents, $totals->groupCents])->toBe([136818, 0, []]);
});

test('aggregates a year across months', function () {
    $this->actingAs(User::factory()->create());
    importFixtureStatement();
    importJuly();
    importedMonth('2025-12', [['amount_cents' => -9999, 'group_key' => 'groceries']]);

    $report = app(SpendingReport::class);
    $year = $report->totals('2026-01', '2026-12');

    expect($year->spentCents)->toBe(179628)
        ->and($year->incomeCents)->toBe(139819)
        ->and($year->groupCents['groceries'])->toBe(30349)
        ->and($year->groupCents['rent'])->toBe(48607)
        ->and($report->totals('2026-07', '2026-07')->spentCents)->toBe(11000);
});

test('returns totals per period', function () {
    $this->actingAs(User::factory()->create());
    importFixtureStatement();
    importJuly();

    $periods = app(SpendingReport::class)->totalsByPeriod('2026-01', '2026-12');

    expect(array_keys($periods))->toBe(['2026-06', '2026-07'])
        ->and($periods['2026-06']->spentCents)->toBe(168628)
        ->and($periods['2026-07']->spentCents)->toBe(11000);
});

test('counts outgoing entries without group as other', function () {
    importedMonth('2026-06', [['amount_cents' => -700, 'group_key' => null]]);

    $totals = app(SpendingReport::class)->totals('2026-06', '2026-06');

    expect([$totals->groupCents, $totals->spentCents])->toBe([['other' => 700], 700]);
});

test('returns empty totals when nothing matches', function () {
    $report = app(SpendingReport::class);

    expect($report->totals('2026-01', '2026-12'))->toEqual(Totals::empty())
        ->and($report->totalsByPeriod('2026-01', '2026-12'))->toBe([]);
});
