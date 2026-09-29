<?php

use App\Services\Statements\IngStatementParser;
use App\Services\Statements\ParsedEntry;
use App\Services\Statements\ParsedStatement;
use App\Services\Statements\StatementParseException;

function juneStatement(): ParsedStatement
{
    static $statement;

    return $statement ??= (new IngStatementParser)->parse(fixturePath('ing-2026-06.pdf'));
}

/**
 * @return list<ParsedEntry>
 */
function juneEntriesWhere(callable $filter): array
{
    return array_values(array_filter(juneStatement()->entries, $filter));
}

function juneEntry(int $amountCents, ?string $type = null): ParsedEntry
{
    $entries = juneEntriesWhere(fn (ParsedEntry $entry) => $entry->amountCents === $amountCents
        && ($type === null || $entry->type === $type));

    expect($entries)->toHaveCount(1);

    return $entries[0];
}

function tempFileWith(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'stmt');
    file_put_contents($path, $content);

    return $path;
}

test('reads the statement header', function () {
    $statement = juneStatement();

    expect($statement->number)->toBe(6)
        ->and($statement->period)->toBe('2026-06')
        ->and($statement->statementDate)->toBe('2026-06-30')
        ->and($statement->oldBalanceCents)->toBe(227202)
        ->and($statement->newBalanceCents)->toBe(195230);
});

test('parses all 69 entries', function () {
    expect(juneStatement()->entries)->toHaveCount(69);
});

test('sums the entries to the balance difference', function () {
    $statement = juneStatement();
    $sum = array_sum(array_map(fn (ParsedEntry $entry) => $entry->amountCents, $statement->entries));

    expect($sum)->toBe($statement->newBalanceCents - $statement->oldBalanceCents)
        ->and($sum)->toBe(-31972);
});

test('keeps both separate 515,40 credits', function () {
    $credits = juneEntriesWhere(fn (ParsedEntry $entry) => $entry->amountCents === 51540);

    expect($credits)->toHaveCount(2);

    foreach ($credits as $credit) {
        expect($credit->direction())->toBe('in')
            ->and($credit->type)->toBe('Gutschrift/Dauerauftrag')
            ->and($credit->bookedOn)->toBe('2026-06-29');
    }

    expect($credits[0]->purpose)->toBe('Miete')
        ->and($credits[1]->purpose)->toStartWith('Miete 313,34 und Nebenkosten');
});

test('parses the first entry', function () {
    $entry = juneStatement()->entries[0];

    expect($entry->bookedOn)->toBe('2026-06-01')
        ->and($entry->valueOn)->toBe('2026-06-01')
        ->and($entry->type)->toBe('Dauerauftrag/Terminueberw.')
        ->and($entry->counterparty)->toBeNull()
        ->and($entry->purpose)->toBe('Netflix + Router')
        ->and($entry->amountCents)->toBe(-750)
        ->and($entry->merchant)->toBe('Netflix + Router');
});

test('separates type and counterparty', function () {
    $entry = juneEntry(-19700);

    expect($entry->type)->toBe('Lastschrift')
        ->and($entry->counterparty)->toBe('RhoenEnergie Fulda GmbH');
});

test('reads multi-line purposes', function () {
    $lines = explode("\n", juneEntry(-1299)->purpose);

    expect($lines)->toHaveCount(4)
        ->and($lines[3])->toBe('Referenz: 1050619809280')
        ->and(juneEntry(-1299)->purpose)->toContain('Ihr Einkauf bei');
});

test('reads a value date that differs from the booking date', function () {
    $entry = juneEntry(-5000);

    expect($entry->bookedOn)->toBe('2026-06-02')
        ->and($entry->valueOn)->toBe('2026-06-01')
        ->and($entry->counterparty)->toBe('Bargeldauszahlung VISA Card SPARKASSE FULDA');
});

test('keeps the Abschluss booking but skips the summary block', function () {
    $closings = juneEntriesWhere(fn (ParsedEntry $entry) => $entry->type === 'Abschluss');

    expect($closings)->toHaveCount(1)
        ->and($closings[0]->amountCents)->toBe(-5)
        ->and($closings[0]->purpose)->toBe('')
        ->and($closings[0]->merchant)->toBe('Abschluss');

    foreach (juneStatement()->entries as $entry) {
        expect($entry->purpose)->not->toContain('Neuer Saldo')
            ->not->toContain('Dispokredit')
            ->not->toContain('Freistellungsauftrag');
    }
});

test('parses the transfer without recipient', function () {
    $entry = juneEntry(-20000);

    expect($entry->type)->toBe('Echtzeitüberweisung')
        ->and($entry->counterparty)->toBeNull()
        ->and($entry->purpose)->toBe('')
        ->and($entry->merchant)->toBe('Echtzeitüberweisung');
});

test('parses income', function () {
    $entry = juneEntry(134819);

    expect($entry->type)->toBe('Gehalt/Rente')
        ->and($entry->direction())->toBe('in')
        ->and($entry->merchant)->toBe('CGS Clinical Guideline Serv ices');
});

test('derives sample merchants', function (int $amountCents, string $merchant, ?string $type = null) {
    expect(juneEntry($amountCents, $type)->merchant)->toBe($merchant);
})->with([
    [-399, 'TEGUT'],
    [-4898, 'AMAZON'],
    [-1154, 'WWW.AMAZON'],
    [-899, 'AMAZON PRIM'],
    [-1940, 'LOTTO He ssen'],
    [-1299, 'Spotify'],
    [-1167, 'rebuy recommerc e'],
    [-2044, 'Takeaway.com Payments'],
    [-1199, 'Discovery Communication s Benelux'],
    [-470, 'RhonEnergie Baderbetrieb'],
    [-2500, 'www.s teampowered.com'],
    [-84, 'ROSSMANN'],
    [-750, 'UNI DONER', 'Lastschrift'],
    [-570, 'BAECKEREI HAPP'],
    [-510, 'RISTORANTE LA ROMA'],
    [-4300, 'LS CHUMBOS FULDA'],
    [-999, 'E-Plus Service'],
    [-15065, 'DAK-Gesundheit'],
    [-115820, 'Miete'],
    [1836, 'Rundfunkbeitrag (3 Monate)'],
]);

test('sets direction by sign', function () {
    foreach (juneStatement()->entries as $entry) {
        expect($entry->direction())->toBe($entry->amountCents < 0 ? 'out' : 'in');
    }

    expect(juneEntriesWhere(fn (ParsedEntry $entry) => $entry->direction() === 'in'))->toHaveCount(4);
});

test('rejects a pdf that is not an ING statement', function () {
    (new IngStatementParser)->parse(tempFileWith(blankPdf()));
})->throws(StatementParseException::class);

test('rejects a file that is not a pdf', function () {
    (new IngStatementParser)->parse(tempFileWith('not a pdf'));
})->throws(StatementParseException::class);
