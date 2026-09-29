<?php

use App\Services\Rules\RuleMatch;
use App\Services\Statements\ReviewQueue;

beforeEach(function () {
    $this->entries = [
        entry(-399, 'VISA TEGUT FILIALE 5020'),
        entry(-499, 'VISA TEGUT FILIALE 1234'),
        entry(-250, 'VISA TE GUT FILIALE 7'),
        entry(-1000, 'VISA REWE MARKT'),
        entry(-20000, null, '', 'Echtzeitüberweisung'),
        entry(134819, 'CGS GmbH', '', 'Gehalt/Rente'),
        entry(51540, null, 'Miete', 'Gutschrift/Dauerauftrag'),
        entry(-115820, null, 'Miete', 'Dauerauftrag/Terminueberw.'),
    ];

    $this->matches = [
        RuleMatch::none(),
        RuleMatch::none(),
        RuleMatch::none(),
        RuleMatch::none(),
        RuleMatch::none(),
        RuleMatch::none(),
        new RuleMatch(null, 1, true, 'r-ignore'),
        new RuleMatch('rent', 3, false, 'r-rent'),
    ];

    $this->queue = new ReviewQueue($this->entries, $this->matches);
});

/** Picks #1 by hand, then ticks "always" for TEGUT on #0. */
function tegutAlwaysGroceries(ReviewQueue $queue): void
{
    $queue->pick(1, 'health');
    $queue->pick(0, 'groceries');
    $queue->setAlways(0, true);
}

test('queues only unmatched outgoing entries', function () {
    expect($this->entries[2]->merchant)->toBe('TE GUT')
        ->and($this->queue->queue())->toBe([0, 1, 2, 3, 4])
        ->and($this->queue->contains(5))->toBeFalse()
        ->and($this->queue->contains(6))->toBeFalse()
        ->and($this->queue->contains(7))->toBeFalse();
});

test('counts open entries', function () {
    expect($this->queue->openCount())->toBe(5);

    $this->queue->pick(4, 'other');

    expect($this->queue->openCount())->toBe(4);
});

test('assigns one entry', function () {
    $this->queue->pick(0, 'groceries');

    expect($this->queue->groupFor(0))->toBe('groceries')
        ->and($this->queue->groupFor(1))->toBeNull()
        ->and($this->queue->groupFor(2))->toBeNull()
        ->and($this->queue->picks())->toBe([0 => 'groceries']);
});

test('re-picks an entry', function () {
    $this->queue->pick(0, 'groceries');
    $this->queue->pick(0, 'health');

    expect($this->queue->groupFor(0))->toBe('health');
});

test('refuses entries outside the queue', function () {
    expect(fn () => $this->queue->pick(7, 'other'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->queue->setAlways(5, true))->toThrow(InvalidArgumentException::class);
});

test('refuses always without a group', function () {
    expect(fn () => $this->queue->setAlways(0, true))->toThrow(LogicException::class);
});

test('applies always to the other unassigned entries of the merchant', function () {
    tegutAlwaysGroceries($this->queue);

    expect($this->queue->groupFor(2))->toBe('groceries')
        ->and($this->queue->groupFor(1))->toBe('health')
        ->and($this->queue->groupFor(3))->toBeNull()
        ->and($this->queue->isAlways(0))->toBeTrue()
        ->and($this->queue->isAlways(2))->toBeTrue()
        ->and($this->queue->isAlways(1))->toBeFalse()
        ->and($this->queue->always())->toBe(['TEGUT' => ['merchant' => 'TEGUT', 'group_key' => 'groceries']])
        ->and($this->queue->openCount())->toBe(2);
});

test('follows a new pick while always is on', function () {
    tegutAlwaysGroceries($this->queue);

    $this->queue->pick(0, 'other');

    expect($this->queue->groupFor(2))->toBe('other')
        ->and($this->queue->always()['TEGUT']['group_key'])->toBe('other')
        ->and($this->queue->groupFor(1))->toBe('health');
});

test('ignores unticking on an entry that does not follow always', function () {
    tegutAlwaysGroceries($this->queue);
    $always = $this->queue->always();

    $this->queue->setAlways(1, false);

    expect($this->queue->always())->toBe($always)
        ->and($this->queue->groupFor(2))->toBe('groceries');
});

test('moves always to another group when ticked on a differing entry', function () {
    tegutAlwaysGroceries($this->queue);

    $this->queue->setAlways(1, true);

    expect($this->queue->always()['TEGUT']['group_key'])->toBe('health')
        ->and($this->queue->groupFor(2))->toBe('health')
        ->and($this->queue->groupFor(0))->toBe('groceries')
        ->and($this->queue->isAlways(0))->toBeFalse();
});

test('returns followers to unassigned when always is unticked', function () {
    $this->queue->pick(0, 'groceries');
    $this->queue->setAlways(0, true);
    $this->queue->setAlways(0, false);

    expect($this->queue->groupFor(0))->toBe('groceries')
        ->and($this->queue->groupFor(2))->toBeNull()
        ->and($this->queue->always())->toBe([]);
});

test('keeps the group of a follower that unticks', function () {
    $this->queue->pick(0, 'groceries');
    $this->queue->setAlways(0, true);
    $this->queue->setAlways(2, false);

    expect($this->queue->groupFor(2))->toBe('groceries')
        ->and($this->queue->groupFor(0))->toBe('groceries')
        ->and($this->queue->always())->toBe([]);
});

test('restores its state from arrays', function () {
    tegutAlwaysGroceries($this->queue);
    $this->queue->pick(4, 'other');

    $restored = new ReviewQueue($this->entries, $this->matches, $this->queue->picks(), $this->queue->always());

    expect($restored->state())->toBe($this->queue->state());
});

test('exports the state for the page', function () {
    $this->queue->pick(0, 'groceries');
    $this->queue->setAlways(0, true);

    expect($this->queue->state())->toBe([
        'entries' => [
            0 => ['group' => 'groceries', 'always' => true],
            1 => ['group' => 'groceries', 'always' => true],
            2 => ['group' => 'groceries', 'always' => true],
            3 => ['group' => null, 'always' => false],
            4 => ['group' => null, 'always' => false],
        ],
        'open' => 2,
    ]);
});

test('builds the final matches', function () {
    tegutAlwaysGroceries($this->queue);
    $this->queue->pick(3, 'groceries');
    $this->queue->pick(4, 'other');

    $matches = $this->queue->finalMatches(['TEGUT' => 'r-manual']);

    expect($matches[0])->toEqual(new RuleMatch('groceries', 1, false, 'r-manual'))
        ->and($matches[2])->toEqual(new RuleMatch('groceries', 1, false, 'r-manual'))
        ->and($matches[1])->toEqual(new RuleMatch('health', 1, false, null))
        ->and($matches[3])->toEqual(new RuleMatch('groceries', 1, false, null))
        ->and($matches[4])->toEqual(new RuleMatch('other', 1, false, null))
        ->and($matches[5])->toBe($this->matches[5])
        ->and($matches[6])->toBe($this->matches[6])
        ->and($matches[7])->toBe($this->matches[7]);
});
