<?php

use App\Enums\RuleField;
use App\Enums\RuleOperator;
use App\Services\Rules\Condition;
use App\Services\Statements\ParsedEntry;

function conditionEntry(int $amountCents = -1000, string $merchant = 'TEGUT', ?string $counterparty = null): ParsedEntry
{
    return new ParsedEntry(
        bookedOn: '2026-06-01',
        valueOn: '2026-06-01',
        type: 'Lastschrift',
        counterparty: $counterparty,
        purpose: '',
        merchant: $merchant,
        amountCents: $amountCents,
    );
}

function condition(string $field, string $operator, string|int $value): Condition
{
    return new Condition(RuleField::from($field), RuleOperator::from($operator), $value);
}

test('matches text operators ignoring case, spaces and umlauts', function () {
    expect(condition('merchant', 'equals', 'takeaway.com')->matches(conditionEntry(merchant: 'Takeaway .com')))->toBeTrue()
        ->and(condition('merchant', 'equals', 'takeaway')->matches(conditionEntry(merchant: 'Takeaway .com')))->toBeFalse()
        ->and(condition('merchant', 'contains', 'müller')->matches(conditionEntry(merchant: 'MUELLER')))->toBeTrue()
        ->and(condition('merchant', 'not_contains', 'rewe')->matches(conditionEntry(merchant: 'REWE KAI')))->toBeFalse()
        ->and(condition('merchant', 'not_contains', 'rewe')->matches(conditionEntry(merchant: 'TEGUT')))->toBeTrue()
        ->and(condition('counterparty', 'not_contains', 'x')->matches(conditionEntry(counterparty: null)))->toBeTrue();
});

test('compares amounts without their sign', function (string $operator, int $value, bool $expected) {
    expect(condition('amount', $operator, $value)->matches(conditionEntry(-6390)))->toBe($expected)
        ->and(condition('amount', $operator, $value)->matches(conditionEntry(6390)))->toBe($expected);
})->with([
    'greater than' => ['greater_than', 5000, true],
    'at least' => ['at_least', 6390, true],
    'less than' => ['less_than', 6390, false],
    'at most' => ['at_most', 6390, true],
    'equal' => ['equals', 6390, true],
    'not equal' => ['not_equals', 6390, false],
]);

test('parses euro amounts', function (string $input, ?int $expected) {
    expect(Condition::parseAmount($input))->toBe($expected);
})->with([
    'comma, one decimal' => ['12,5', 1250],
    'dot, two decimals' => ['12.50', 1250],
    'whole euros' => ['20', 2000],
    'zero' => ['0', null],
    'zero with decimals' => ['0,00', null],
    'three decimals' => ['1,234', null],
    'thousands separator' => ['1.158,20', null],
    'negative' => ['-5', null],
    'text' => ['abc', null],
    'empty' => ['', null],
]);

test('describes conditions', function () {
    expect(condition('merchant', 'contains', 'Spotify')->describe())->toBe('merchant contains "Spotify"')
        ->and(condition('amount', 'greater_than', 5000)->describe())->toBe('amount is greater than 50,00 €')
        ->and(condition('purpose', 'not_contains', 'x')->describe())->toBe('purpose doesn\'t contain "x"')
        ->and(condition('amount', 'equals', 2000)->inputValue())->toBe('20,00');
});
