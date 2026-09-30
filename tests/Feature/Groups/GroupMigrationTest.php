<?php

use App\Models\Rule;
use App\Services\Groups\GroupCatalog;

test('creates the default groups in order', function () {
    $catalog = app(GroupCatalog::class);

    expect($catalog->keys())->toBe([
        'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies',
        'online_orders', 'takeaway', 'restaurants', 'health', 'other',
    ])
        ->and($catalog->all()['rent'])->toBe(['name' => 'Rent', 'color' => '#6366f1', 'sort' => 1]);
});

test('converts old rules in place', function () {
    $collection = Rule::query()->toBase()->raw();
    $ids = $collection->insertMany([
        ['field' => 'purpose', 'pattern' => 'Miete', 'direction' => 'out', 'priority' => 100, 'group_key' => 'rent', 'share_divisor' => 3, 'ignore' => false, 'source' => 'seeded'],
        ['field' => 'merchant', 'pattern' => 'AMAZON PRIM', 'direction' => 'out', 'priority' => 200, 'group_key' => 'subscriptions', 'share_divisor' => 1, 'ignore' => false, 'source' => 'seeded'],
        ['field' => 'merchant', 'pattern' => 'Spotify', 'direction' => 'out', 'priority' => 100, 'group_key' => 'subscriptions', 'share_divisor' => 1, 'ignore' => false, 'source' => 'seeded'],
        ['field' => 'merchant', 'pattern' => 'TEGUT', 'direction' => 'out', 'priority' => 300, 'group_key' => 'groceries', 'share_divisor' => 1, 'ignore' => false, 'source' => 'manual'],
    ])->getInsertedIds();
    [$rent, $prime, $spotify, $manual] = array_map(fn ($id) => (string) $id, $ids);

    (require database_path('migrations/2026_09_30_200001_convert_rules_to_conditions.php'))->up();

    $rules = Rule::all()->keyBy(fn (Rule $rule) => (string) $rule->id);

    expect($rules->keys()->sort()->values()->all())->toBe(collect([$rent, $prime, $spotify, $manual])->sort()->values()->all())
        ->and($rules[$rent]->conditions)->toBe([['field' => 'purpose', 'operator' => 'contains', 'value' => 'Miete']])
        ->and($rules[$rent]->source->value)->toBe('system')
        ->and($rules[$rent]->position)->toBe(1)
        ->and($rules[$prime]->position)->toBe(1)
        ->and($rules[$spotify]->position)->toBe(2)
        ->and($rules[$manual]->source->value)->toBe('manual')
        ->and($rules[$manual]->position)->toBeNull();

    foreach ($collection->find() as $document) {
        expect(isset($document['pattern']) || isset($document['priority']) || isset($document['field']))->toBeFalse();
    }
});
