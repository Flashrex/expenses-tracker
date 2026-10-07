<?php

use App\Models\Group;
use App\Models\Rule;
use App\Models\Transaction;
use App\Services\Groups\GroupCatalog;

test('creates the default groups in order', function () {
    $catalog = app(GroupCatalog::class);

    expect($catalog->keys())->toBe([
        'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies',
        'online_orders', 'takeaway', 'restaurants', 'health', 'fees', 'other',
    ])
        ->and($catalog->all()['rent'])->toBe(['name' => 'Rent', 'color' => '#7f81d5', 'sort' => 1]);
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

test('mutes the swatch colours and keeps custom ones', function () {
    Group::query()->where('key', 'rent')->update(['color' => '#6366f1']);
    Group::query()->where('key', 'health')->update(['color' => '#123456']);

    (require database_path('migrations/2026_10_07_100000_mute_group_colors.php'))->up();

    expect(Group::query()->where('key', 'rent')->sole()->color)->toBe('#7f81d5')
        ->and(Group::query()->where('key', 'health')->sole()->color)->toBe('#123456');
});

test('makes other the catch-all group', function () {
    Group::query()->where('key', 'fees')->delete();
    Group::reorder(['rent', 'other', 'utilities', 'groceries', 'subscriptions', 'hobbies', 'online_orders', 'takeaway', 'restaurants', 'health']);

    $fee = Rule::factory()->withCondition('purpose', 'contains', 'Rundfunkbeitrag')->create(['group_key' => 'other', 'share_divisor' => 3, 'position' => 1]);
    $lotto = Rule::factory()->withCondition('merchant', 'contains', 'LOTTO')->create(['group_key' => 'other', 'position' => 2]);
    $always = Rule::factory()->manual()->withCondition('merchant', 'contains', 'Kiosk')->create(['group_key' => 'other', 'position' => null]);
    $rent = Rule::factory()->withCondition('purpose', 'contains', 'Miete')->create(['group_key' => 'rent', 'position' => 1]);

    importedMonth('2026-06', [
        ['merchant' => 'Rundfunk', 'amount_cents' => -5508, 'group_key' => 'other', 'share_divisor' => 3, 'rule_id' => $fee->id],
        ['merchant' => 'LOTTO', 'amount_cents' => -500, 'group_key' => 'other', 'rule_id' => $lotto->id],
        ['merchant' => 'Open', 'amount_cents' => -700],
        ['merchant' => 'Picked', 'amount_cents' => -300, 'group_key' => 'other'],
        ['merchant' => 'Salary', 'amount_cents' => 5000, 'direction' => 'in'],
        ['merchant' => 'Skipped', 'amount_cents' => -100, 'ignored' => true],
    ]);

    (require database_path('migrations/2026_10_07_100001_make_other_the_catch_all_group.php'))->up();
    app(GroupCatalog::class)->forget();

    $entries = Transaction::all()->keyBy('merchant');

    expect(app(GroupCatalog::class)->keys())->toBe([
        'rent', 'utilities', 'groceries', 'subscriptions', 'hobbies', 'online_orders', 'takeaway', 'restaurants', 'health', 'fees', 'other',
    ])
        ->and(app(GroupCatalog::class)->get('fees'))->toBe(['name' => 'Fees & Taxes', 'color' => '#bd9c35', 'sort' => 10])
        ->and($fee->fresh()->group_key)->toBe('fees')
        ->and($fee->fresh()->share_divisor)->toBe(3)
        ->and(Rule::find($lotto->id))->toBeNull()
        ->and(Rule::find($always->id))->toBeNull()
        ->and($rent->fresh())->not->toBeNull()
        ->and($entries['Rundfunk']->only('group_key', 'share_divisor', 'rule_id', 'unmatched'))->toBe(['group_key' => 'fees', 'share_divisor' => 3, 'rule_id' => $fee->id, 'unmatched' => false])
        ->and($entries['LOTTO']->only('group_key', 'rule_id', 'unmatched'))->toBe(['group_key' => 'other', 'rule_id' => null, 'unmatched' => true])
        ->and($entries['Open']->only('group_key', 'unmatched'))->toBe(['group_key' => 'other', 'unmatched' => true])
        ->and($entries['Picked']->only('group_key', 'unmatched'))->toBe(['group_key' => 'other', 'unmatched' => false])
        ->and($entries['Salary']->group_key)->toBeNull()
        ->and($entries['Skipped']->group_key)->toBeNull();
});
