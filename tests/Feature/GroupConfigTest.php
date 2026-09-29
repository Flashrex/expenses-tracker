<?php

test('defines the ten groups in order', function () {
    $groups = config('expenses.groups');

    expect(array_keys($groups))->toBe([
        'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies',
        'online_orders', 'takeaway', 'restaurants', 'health', 'other',
    ])
        ->and(array_column($groups, 'sort'))->toBe(range(1, 10))
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
            'Other',
        ]);

    foreach ($groups as $group) {
        expect($group['color'])->toMatch('/^#[0-9a-f]{6}$/');
    }
});

test('has a matching tailwind token for every group colour', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach (config('expenses.groups') as $key => $group) {
        $token = '--color-group-'.str_replace('_', '-', $key).': '.$group['color'].';';

        expect($css)->toContain($token);
    }
});
