<?php

use App\Services\Reports\EntryPage;

test('computes bounds', function () {
    $first = new EntryPage([], 69, 1);
    $second = new EntryPage([], 69, 2);
    $empty = new EntryPage([], 0, 1);

    expect([$first->lastPage(), $first->from(), $first->to()])->toBe([2, 1, 50])
        ->and([$second->from(), $second->to()])->toBe([51, 69])
        ->and([$empty->lastPage(), $empty->from(), $empty->to()])->toBe([1, 0, 0]);
});

test('builds the page window', function (int $page, int $lastPage, array $window) {
    expect((new EntryPage([], $lastPage * EntryPage::PER_PAGE, $page))->window())->toBe($window);
})->with([
    'page 1 of 2' => [1, 2, [1, 2]],
    'page 1 of 10' => [1, 10, [1, 2, null, 10]],
    'page 3 of 10' => [3, 10, [1, 2, 3, 4, null, 10]],
    'page 5 of 10' => [5, 10, [1, null, 4, 5, 6, null, 10]],
    'page 10 of 10' => [10, 10, [1, null, 9, 10]],
]);
