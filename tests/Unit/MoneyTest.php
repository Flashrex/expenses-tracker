<?php

use App\Support\Money;

test('formats cents the German way', function (int $cents, bool $signed, string $expected) {
    expect(Money::format($cents, $signed))->toBe($expected);
})->with([
    [-115820, true, '−1.158,20 €'],
    [134819, true, '+1.348,19 €'],
    [227202, false, '2.272,02 €'],
    [-5, false, '−0,05 €'],
    [0, true, '0,00 €'],
]);
