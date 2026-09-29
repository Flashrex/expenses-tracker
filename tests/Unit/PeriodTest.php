<?php

use App\Support\Period;

test('formats periods', function () {
    expect(Period::label('2026-06'))->toBe('June 2026')
        ->and(Period::short('2026-06'))->toBe('Jun 2026')
        ->and(Period::label('2026-02'))->toBe('February 2026');
});
