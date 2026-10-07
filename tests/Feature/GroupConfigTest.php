<?php

test('defines the eleven groups in order', function () {
    $groups = config('expenses.groups');

    expect(array_keys($groups))->toBe([
        'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies',
        'online_orders', 'takeaway', 'restaurants', 'health', 'fees', 'other',
    ])
        ->and(array_column($groups, 'sort'))->toBe(range(1, 11))
        ->and(array_column($groups, 'name'))->toBe([
            'Rent',
            'Electricity & Gas',
            'Groceries & Personal Care',
            'Subscriptions & Internet',
            'Hobbies & Entertainment',
            'Online Orders',
            'Takeaway & Fast Food',
            'Restaurants & Bars',
            'Health',
            'Fees & Taxes',
            'Other',
        ]);

    foreach ($groups as $group) {
        expect($group['color'])->toMatch('/^#[0-9a-f]{6}$/');
    }
});
