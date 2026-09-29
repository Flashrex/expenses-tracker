<?php

use App\Support\Percent;

test('formats percentages the German way', function (float $value, int $decimals, string $expected) {
    expect(Percent::format($value, $decimals))->toBe($expected);
})->with([
    [22.94, 1, '22,9 %'],
    [17, 0, '17 %'],
    [100, 0, '100 %'],
    [0, 1, '0,0 %'],
]);
