<?php

use App\Enums\RuleDirection;
use App\Enums\RuleField;
use App\Models\Rule;
use App\Services\Rules\RuleMatch;
use App\Services\Rules\RuleMatcher;
use Database\Seeders\RuleSeeder;

beforeEach(function () {
    $this->seed(RuleSeeder::class);
});

function ruleId(string $pattern, string $direction = 'out'): string
{
    return Rule::query()->where('pattern', $pattern)->where('direction', $direction)->first()->id;
}

/** @return list<RuleMatch> */
function fixtureMatches(): array
{
    return RuleMatcher::fromDatabase()->matchAll(fixtureStatement()->entries);
}

test('groups the June fixture', function () {
    $entries = fixtureStatement()->entries;
    $matches = fixtureMatches();

    $states = array_map(fn (RuleMatch $match, $entry) => $match->state($entry->direction()), $matches, $entries);
    expect(array_count_values($states))->toEqualCanonicalizing(['group' => 64, 'ignored' => 3, 'income' => 1, 'unassigned' => 1]);

    $groups = array_count_values(array_filter(array_map(fn (RuleMatch $match) => $match->groupKey, $matches)));
    expect($groups)->toEqualCanonicalizing([
        'rent' => 1, 'utilities' => 2, 'groceries' => 27, 'subscriptions' => 6, 'hobbies' => 10,
        'online_orders' => 7, 'takeaway' => 3, 'restaurants' => 4, 'health' => 1, 'other' => 3,
    ]);

    $shared = array_keys(array_filter($matches, fn (RuleMatch $match) => $match->shareDivisor === 3));
    expect($shared)->toBe([1, 2, 3]);
});

test('leaves only the unknown transfer unassigned', function () {
    $entries = fixtureStatement()->entries;
    $matches = fixtureMatches();

    $unassigned = array_keys(array_filter($matches, fn (RuleMatch $match, int $i) => $match->state($entries[$i]->direction()) === 'unassigned', ARRAY_FILTER_USE_BOTH));

    expect($unassigned)->toBe([65])
        ->and($entries[65]->type)->toBe('Echtzeitüberweisung')
        ->and($entries[65]->amountCents)->toBe(-20000)
        ->and($matches[65])->toEqual(RuleMatch::none());
});

test('does not group income', function () {
    $entry = fixtureStatement()->entries[60];
    $match = fixtureMatches()[60];

    expect($entry->type)->toBe('Gehalt/Rente')
        ->and($entry->amountCents)->toBe(134819)
        ->and($match->groupKey)->toBeNull()
        ->and($match->ignored)->toBeFalse()
        ->and($match->ruleId)->toBeNull();
});

test('matches seeded rules on fixture entries', function (int $index, string $merchant, string $expected, int $divisor = 1) {
    expect(fixtureStatement()->entries[$index]->merchant)->toBe($merchant);

    $match = fixtureMatches()[$index];

    if ($expected === 'ignored') {
        expect($match->ignored)->toBeTrue()->and($match->groupKey)->toBeNull();
    } else {
        expect($match->groupKey)->toBe($expected)->and($match->ignored)->toBeFalse();
    }

    expect($match->shareDivisor)->toBe($divisor);
})->with([
    [0, 'Netflix + Router', 'subscriptions'],
    [1, 'Miete', 'rent', 3],
    [2, 'RhoenEnergie Fulda', 'utilities', 3],
    [4, 'TEGUT', 'groceries'],
    [5, 'AMAZON', 'online_orders'],
    [8, 'LOTTO He ssen', 'other'],
    [9, 'Spotify', 'subscriptions'],
    [10, 'rebuy recommerc e', 'online_orders'],
    [11, 'ROSSMANN', 'groceries'],
    [12, 'VIVA HAVANNA RESTAURAN', 'restaurants'],
    [13, 'RESTAURANT PIZZERIA', 'restaurants'],
    [14, 'STEAM GAMES', 'hobbies'],
    [15, 'TEO FULDA', 'groceries'],
    [17, 'EDEKA HELLWIG', 'groceries'],
    [19, 'Bargeldauszahlung VISA Card SPARKASSE FULDA', 'other'],
    [21, 'UNI DONER', 'takeaway'],
    [23, 'REWE KAI UWE GRASMUECK', 'groceries'],
    [25, 'BAECKEREI HAPP', 'groceries'],
    [29, 'Takeaway.com Payments', 'takeaway'],
    [30, 'WWW.AMAZON', 'online_orders'],
    [32, 'Rundfunkbeitrag (3 Monate)', 'ignored'],
    [33, 'E-Plus Service', 'subscriptions'],
    [35, 'DAK-Gesundheit', 'health'],
    [43, 'ALDI SUED', 'groceries'],
    [47, 'RISTORANTE LA ROMA', 'restaurants'],
    [50, 'Discovery Communication s Benelux', 'subscriptions'],
    [54, 'RhonEnergie Baderbetrieb', 'hobbies'],
    [57, 'www.s teampowered.com', 'hobbies'],
    [59, 'AMAZON PRIM', 'subscriptions'],
    [61, 'LS CHUMBOS FULDA', 'restaurants'],
    [63, 'Miete', 'ignored'],
    [64, 'Miete 313,34 und Nebenkosten 63,33, Strom: 65,67, Gas', 'ignored'],
    [68, 'Abschluss', 'other'],
]);

test('records the matching rule', function () {
    $matches = fixtureMatches();

    expect($matches[30]->ruleId)->toBe(ruleId('WWW.AMAZON'))
        ->and($matches[57]->ruleId)->toBe(ruleId('steampowered'))
        ->and($matches[33]->ruleId)->toBe(ruleId('ALDI TALK'))
        ->and($matches[1]->ruleId)->toBe(ruleId('Miete'))
        ->and($matches[63]->ruleId)->toBe(ruleId('Miete', 'in'));
});

test('matches seeded rules without a fixture entry', function (Closure $entry, string $group, int $divisor = 1) {
    $match = RuleMatcher::fromDatabase()->match($entry());

    expect($match->groupKey)->toBe($group)
        ->and($match->shareDivisor)->toBe($divisor)
        ->and($match->ignored)->toBeFalse();
})->with([
    'mueller' => [fn () => entry(-899, 'VISA MUELLER 1234'), 'groceries'],
    'cinestar' => [fn () => entry(-1350, 'VISA CINESTAR FULDA'), 'hobbies'],
    'google payment' => [fn () => entry(-499, 'Google Payment Ireland Limited'), 'hobbies'],
    'lieferando' => [fn () => entry(-2290, 'VISA LIEFERANDO.DE'), 'takeaway'],
    'mcdonalds' => [fn () => entry(-899, 'VISA MCDONALDS 1234'), 'takeaway'],
    'selecta' => [fn () => entry(-150, 'VISA SELECTA DEUTSCHLAND'), 'takeaway'],
    'kiosk' => [fn () => entry(-320, 'VISA KIOSK AM BAHNHOF'), 'takeaway'],
    'apotheke' => [fn () => entry(-1295, 'VISA ROSEN-APOTHEKE'), 'health'],
    'rundfunkbeitrag' => [fn () => entry(-5508, 'Rundfunk ARD, ZDF, DRadio', "Rundfunkbeitrag 07.2026 - 09.2026\nBeitragsnr. 123456789"), 'other', 3],
    'umlaut' => [fn () => entry(-999, 'VISA MÜLLER 1234'), 'groceries'],
]);

test('prefers higher priority', function () {
    expect(fixtureMatches()[59]->groupKey)->toBe('subscriptions');

    $match = RuleMatcher::fromDatabase()->match(entry(-470, 'RhoenEnergie Fulda Baderbetrieb GmbH'));

    expect($match->groupKey)->toBe('hobbies')
        ->and($match->shareDivisor)->toBe(1);
});

test('prefers the longer pattern at equal priority', function () {
    $matcher = new RuleMatcher([
        Rule::factory()->create(['pattern' => 'AMAZON', 'group_key' => 'online_orders']),
        Rule::factory()->create(['pattern' => 'AMAZON MARKETPLACE', 'group_key' => 'other']),
    ]);

    expect($matcher->match(entry(-1000, 'VISA AMAZON MARKETPLACE* X1'))->groupKey)->toBe('other');
});

test('prefers seeded rules over manual rules of higher priority', function () {
    Rule::factory()->manual()->create(['pattern' => 'Peter Hein', 'priority' => Rule::MANUAL_PRIORITY, 'group_key' => 'other']);
    $matcher = RuleMatcher::fromDatabase();

    $rent = $matcher->match(entry(-113000, 'Peter Hein', 'Miete Bahnhofstrasse 13', 'Dauerauftrag/Terminueberw.'));
    expect($rent->groupKey)->toBe('rent')->and($rent->shareDivisor)->toBe(3);

    expect($matcher->match(entry(-23873, 'Peter Hein', 'Nebenkosten Abrechnung 2025', 'Ueberweisung'))->groupKey)->toBe('other');
});

test('falls back to the older rule on a full tie', function () {
    $matcher = new RuleMatcher([
        Rule::factory()->create(['pattern' => 'TEGUT', 'group_key' => 'groceries']),
        Rule::factory()->create(['field' => RuleField::Counterparty, 'pattern' => 'TEGUT', 'group_key' => 'other']),
    ]);

    expect($matcher->match(entry(-399, 'VISA TEGUT FILIALE 5020'))->groupKey)->toBe('groceries');
});

test('respects the rule direction', function () {
    $matcher = RuleMatcher::fromDatabase();

    $rent = $matcher->match(entry(-115820, null, 'Miete', 'Dauerauftrag/Terminueberw.'));
    expect($rent->groupKey)->toBe('rent')->and($rent->shareDivisor)->toBe(3);

    expect($matcher->match(entry(51540, null, 'Miete', 'Gutschrift/Dauerauftrag'))->ignored)->toBeTrue();

    $refund = $matcher->match(entry(1999, 'VISA AMAZON* NQ0NU61X4'));
    expect($refund->state('in'))->toBe('income')
        ->and($refund->ruleId)->toBeNull();
});

test('matches any direction', function () {
    $matcher = new RuleMatcher([
        Rule::factory()->create(['pattern' => 'FOO', 'direction' => RuleDirection::Any, 'group_key' => 'other']),
    ]);

    expect($matcher->match(entry(-100, 'FOO GmbH'))->groupKey)->toBe('other')
        ->and($matcher->match(entry(100, 'FOO GmbH'))->groupKey)->toBe('other');
});
