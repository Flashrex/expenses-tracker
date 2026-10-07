<?php

/**
 * Groups are only the initial set: the migration copies them into the groups collection once; at runtime groups come from the database (GroupCatalog).
 * "other" takes every outgoing entry no rule matches and has no rules of its own.
 *
 * @return array{groups: array<string, array{name: string, color: string, sort: int}>, rules: list<array{field: string, pattern: string, direction: string, group_key?: string, share_divisor?: int, ignore?: bool}>}
 */
return [
    'groups' => [
        'rent' => ['name' => 'Rent', 'color' => '#7f81d5', 'sort' => 1],
        'utilities' => ['name' => 'Electricity & Gas', 'color' => '#c6923a', 'sort' => 2],
        'groceries' => ['name' => 'Groceries & Personal Care', 'color' => '#329776', 'sort' => 3],
        'subscriptions' => ['name' => 'Subscriptions & Internet', 'color' => '#977bd7', 'sort' => 4],
        'hobbies' => ['name' => 'Hobbies & Entertainment', 'color' => '#cb6999', 'sort' => 5],
        'online_orders' => ['name' => 'Online Orders', 'color' => '#3a94bd', 'sort' => 6],
        'takeaway' => ['name' => 'Takeaway & Fast Food', 'color' => '#cc7b43', 'sort' => 7],
        'restaurants' => ['name' => 'Restaurants & Bars', 'color' => '#d06376', 'sort' => 8],
        'health' => ['name' => 'Health', 'color' => '#35978c', 'sort' => 9],
        'fees' => ['name' => 'Fees & Taxes', 'color' => '#bd9c35', 'sort' => 10],
        'other' => ['name' => 'Other', 'color' => '#9ba4b1', 'sort' => 11],
    ],

    'rules' => [
        ['field' => 'purpose', 'pattern' => 'Miete', 'direction' => 'out', 'group_key' => 'rent', 'share_divisor' => 3],
        ['field' => 'counterparty', 'pattern' => 'RhoenEnergie Fulda', 'direction' => 'out', 'group_key' => 'utilities', 'share_divisor' => 3],
        ['field' => 'merchant', 'pattern' => 'TEGUT', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'REWE', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'EDEKA', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'ALDI SUED', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'BAECKEREI HAPP', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'TEO FULDA', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'ROSSMANN', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'merchant', 'pattern' => 'MUELLER', 'direction' => 'out', 'group_key' => 'groceries'],
        ['field' => 'purpose', 'pattern' => 'Netflix + Router', 'direction' => 'out', 'group_key' => 'subscriptions'],
        ['field' => 'merchant', 'pattern' => 'Spotify', 'direction' => 'out', 'group_key' => 'subscriptions'],
        ['field' => 'merchant', 'pattern' => 'Discovery', 'direction' => 'out', 'group_key' => 'subscriptions'],
        ['field' => 'merchant', 'pattern' => 'AMAZON PRIM', 'direction' => 'out', 'group_key' => 'subscriptions'],
        ['field' => 'purpose', 'pattern' => 'ALDI TALK', 'direction' => 'out', 'group_key' => 'subscriptions'],
        ['field' => 'merchant', 'pattern' => 'E-Plus', 'direction' => 'out', 'group_key' => 'subscriptions'],
        ['field' => 'merchant', 'pattern' => 'steampowered', 'direction' => 'out', 'group_key' => 'hobbies'],
        ['field' => 'merchant', 'pattern' => 'STEAM', 'direction' => 'out', 'group_key' => 'hobbies'],
        ['field' => 'merchant', 'pattern' => 'CineStar', 'direction' => 'out', 'group_key' => 'hobbies'],
        ['field' => 'merchant', 'pattern' => 'Baderbetrieb', 'direction' => 'out', 'group_key' => 'hobbies'],
        ['field' => 'merchant', 'pattern' => 'Google Payment Ireland', 'direction' => 'out', 'group_key' => 'hobbies'],
        ['field' => 'merchant', 'pattern' => 'WWW.AMAZON', 'direction' => 'out', 'group_key' => 'online_orders'],
        ['field' => 'merchant', 'pattern' => 'AMAZON', 'direction' => 'out', 'group_key' => 'online_orders'],
        ['field' => 'merchant', 'pattern' => 'rebuy', 'direction' => 'out', 'group_key' => 'online_orders'],
        ['field' => 'merchant', 'pattern' => 'Takeaway.com', 'direction' => 'out', 'group_key' => 'takeaway'],
        ['field' => 'merchant', 'pattern' => 'Lieferando', 'direction' => 'out', 'group_key' => 'takeaway'],
        ['field' => 'merchant', 'pattern' => 'McDonalds', 'direction' => 'out', 'group_key' => 'takeaway'],
        ['field' => 'merchant', 'pattern' => 'UNI DONER', 'direction' => 'out', 'group_key' => 'takeaway'],
        ['field' => 'merchant', 'pattern' => 'Selecta', 'direction' => 'out', 'group_key' => 'takeaway'],
        ['field' => 'merchant', 'pattern' => 'Kiosk', 'direction' => 'out', 'group_key' => 'takeaway'],
        ['field' => 'merchant', 'pattern' => 'VIVA HAVANNA', 'direction' => 'out', 'group_key' => 'restaurants'],
        ['field' => 'merchant', 'pattern' => 'RESTAURANT PIZZERIA', 'direction' => 'out', 'group_key' => 'restaurants'],
        ['field' => 'merchant', 'pattern' => 'RISTORANTE LA ROMA', 'direction' => 'out', 'group_key' => 'restaurants'],
        ['field' => 'merchant', 'pattern' => 'LS CHUMBOS', 'direction' => 'out', 'group_key' => 'restaurants'],
        ['field' => 'merchant', 'pattern' => 'DAK-Gesundheit', 'direction' => 'out', 'group_key' => 'health'],
        ['field' => 'merchant', 'pattern' => 'Apotheke', 'direction' => 'out', 'group_key' => 'health'],
        ['field' => 'purpose', 'pattern' => 'Rundfunkbeitrag', 'direction' => 'out', 'group_key' => 'fees', 'share_divisor' => 3],
        ['field' => 'purpose', 'pattern' => 'Miete', 'direction' => 'in', 'ignore' => true],
        ['field' => 'purpose', 'pattern' => 'Rundfunkbeitrag', 'direction' => 'in', 'ignore' => true],
    ],
];
