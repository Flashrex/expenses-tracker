<?php

use App\Services\Rules\RuleMatch;
use App\Services\Statements\ImportBatch;
use App\Services\Statements\ParsedStatement;

/** A parsed month with $income incoming entries (never queued) and $open unmatched outgoing ones. */
function parsedMonth(string $period, int $income = 69, int $open = 0): array
{
    $entries = [
        ...array_fill(0, $income, entry(1000, 'CGS GmbH', '', 'Gutschrift')),
        ...array_fill(0, $open, entry(-500, 'VISA REWE MARKT')),
    ];

    return [
        'statement' => new ParsedStatement((int) substr($period, 5), $period, "{$period}-28", 0, 0, $entries),
        'assignments' => array_map(fn () => RuleMatch::none(), $entries),
    ];
}

/**
 * @param  array<string, string>  $statuses  period => pending|confirmed|skipped
 */
function batchWith(array $statuses, array $failed = []): ImportBatch
{
    $batch = ImportBatch::start(array_map(parsedMonth(...), array_keys($statuses)), $failed);

    foreach ($statuses as $period => $status) {
        match ($status) {
            'confirmed' => $batch->markConfirmed($period),
            'skipped' => $batch->markSkipped($period),
            default => null,
        };
    }

    return $batch;
}

test('orders months oldest first', function () {
    $batch = ImportBatch::start([parsedMonth('2026-06'), parsedMonth('2026-04'), parsedMonth('2026-05')], []);

    expect($batch->periods())->toBe(['2026-04', '2026-05', '2026-06'])
        ->and($batch->isBatch())->toBeTrue()
        ->and(ImportBatch::fromArray($batch->toArray())->periods())->toBe(['2026-04', '2026-05', '2026-06']);
});

test('picks the next pending month after the current one and wraps', function () {
    $batch = batchWith(['2026-04' => 'confirmed', '2026-05' => 'pending', '2026-06' => 'pending']);

    expect($batch->nextPending('2026-04'))->toBe('2026-05')
        ->and($batch->nextPending())->toBe('2026-05');

    $batch = batchWith(['2026-04' => 'pending', '2026-05' => 'skipped', '2026-06' => 'confirmed']);

    expect($batch->nextPending('2026-06'))->toBe('2026-04');

    $batch = batchWith(['2026-04' => 'confirmed', '2026-05' => 'skipped']);

    expect($batch->nextPending('2026-05'))->toBeNull()
        ->and($batch->nextPending())->toBeNull();
});

test('reports steps with their state', function () {
    $batch = ImportBatch::start([
        parsedMonth('2026-04', open: 2),
        parsedMonth('2026-05'),
        parsedMonth('2026-06'),
        parsedMonth('2026-07', open: 1),
    ], []);
    $batch->markConfirmed('2026-06');
    $batch->markSkipped('2026-07');

    expect($batch->steps())->toBe([
        ['period' => '2026-04', 'label' => 'Apr 2026', 'state' => 'ready'],
        ['period' => '2026-05', 'label' => 'May 2026', 'state' => 'ready'],
        ['period' => '2026-06', 'label' => 'Jun 2026', 'state' => 'confirmed'],
        ['period' => '2026-07', 'label' => 'Jul 2026', 'state' => 'skipped'],
    ]);
});

test('builds the summary', function (array $statuses, bool $discarding, ?string $expected) {
    expect(batchWith($statuses)->summary($discarding))->toBe($expected);
})->with([
    'single month confirmed' => [['2026-06' => 'confirmed'], false, 'June 2026 imported · 69 entries'],
    'single month discarded' => [['2026-06' => 'pending'], true, null],
    'single month skipped' => [['2026-06' => 'skipped'], false, null],
    'all confirmed' => [['2026-04' => 'confirmed', '2026-05' => 'confirmed', '2026-06' => 'confirmed'], false, '3 months imported · 207 entries'],
    'two confirmed, one skipped' => [['2026-04' => 'confirmed', '2026-05' => 'skipped', '2026-06' => 'confirmed'], false, '2 months imported · 138 entries (1 skipped)'],
    'one confirmed, one skipped' => [['2026-04' => 'confirmed', '2026-05' => 'skipped'], false, '1 month imported · 69 entries (1 skipped)'],
    'nothing confirmed' => [['2026-04' => 'skipped', '2026-05' => 'skipped', '2026-06' => 'skipped'], false, 'Nothing imported (3 skipped)'],
    'discarding after one confirm' => [['2026-04' => 'confirmed', '2026-05' => 'pending', '2026-06' => 'pending'], true, '1 month imported · 69 entries (2 discarded)'],
    'discarding with a skip' => [['2026-04' => 'confirmed', '2026-05' => 'skipped', '2026-06' => 'pending'], true, '1 month imported · 69 entries (1 skipped, 1 discarded)'],
    'discarding with nothing confirmed' => [['2026-04' => 'skipped', '2026-05' => 'pending'], true, null],
]);

test('counts a single entry in the singular', function () {
    $batch = ImportBatch::start([parsedMonth('2026-04', income: 1), parsedMonth('2026-05')], []);
    $batch->markConfirmed('2026-04');
    $batch->markSkipped('2026-05');

    expect($batch->summary())->toBe('1 month imported · 1 entry (1 skipped)');
});

test('hides the notice once dismissed', function () {
    $batch = batchWith(['2026-06' => 'pending'], ['notes.txt – not a PDF']);

    expect($batch->showsNotice())->toBeTrue()
        ->and($batch->failed())->toBe(['notes.txt – not a PDF']);

    $batch->dismissNotice();

    expect($batch->showsNotice())->toBeFalse()
        ->and(ImportBatch::fromArray($batch->toArray())->showsNotice())->toBeFalse()
        ->and(batchWith(['2026-06' => 'pending'])->showsNotice())->toBeFalse();
});

/** June with a rent entry (÷3), an ignored incoming entry, an unmatched outgoing entry and unmatched income. */
function overrideMonth(): ImportBatch
{
    $entries = [
        entry(-115820, null, 'Miete', 'Dauerauftrag/Terminueberw.'),
        entry(51540, null, 'Miete', 'Gutschrift/Dauerauftrag'),
        entry(-20000, 'Someone'),
        entry(134819, 'CGS GmbH', '', 'Gutschrift'),
    ];

    return ImportBatch::start([[
        'statement' => new ParsedStatement(6, '2026-06', '2026-06-30', 0, 0, $entries),
        'assignments' => [new RuleMatch('rent', 3, false, 'rule-rent'), new RuleMatch(null, 1, true, 'rule-ignore'), RuleMatch::none(), RuleMatch::none()],
    ]], []);
}

test('overrides only outgoing entries a rule grouped', function () {
    $batch = overrideMonth();

    expect($batch->canOverride('2026-06', 0))->toBeTrue()
        ->and($batch->canOverride('2026-06', 1))->toBeFalse()
        ->and($batch->canOverride('2026-06', 2))->toBeFalse()
        ->and($batch->canOverride('2026-06', 3))->toBeFalse()
        ->and($batch->canOverride('2026-06', 9))->toBeFalse();
});

test('stores an override and drops it when the rule group is picked', function () {
    $batch = overrideMonth();

    $batch->override('2026-06', 0, 'other');

    expect($batch->overrides('2026-06'))->toBe([0 => 'other'])
        ->and(ImportBatch::fromArray($batch->toArray())->overrides('2026-06'))->toBe([0 => 'other']);

    $batch->override('2026-06', 0, 'rent');

    expect($batch->overrides('2026-06'))->toBe([]);

    $batch->override('2026-06', 2, 'other');
})->throws(InvalidArgumentException::class, 'Entry 2 cannot be regrouped.');

test('applies overrides to the final matches', function () {
    $batch = overrideMonth();
    $batch->override('2026-06', 0, 'other');

    $matches = $batch->finalMatches('2026-06', []);

    expect($matches[0])->toEqual(new RuleMatch('other', 1, false, null))
        ->and($matches[1])->toEqual(new RuleMatch(null, 1, true, 'rule-ignore'));
});
