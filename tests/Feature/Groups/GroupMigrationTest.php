<?php

use App\Services\Groups\GroupCatalog;

test('creates the default groups in order', function () {
    $catalog = app(GroupCatalog::class);

    expect($catalog->keys())->toBe([
        'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies',
        'online_orders', 'takeaway', 'restaurants', 'health', 'other',
    ])
        ->and($catalog->all()['rent'])->toBe(['name' => 'Rent', 'color' => '#6366f1', 'sort' => 1]);
});
