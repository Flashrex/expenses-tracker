<?php

use App\Models\Group;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Groups\GroupCatalog;
use Database\Seeders\RuleSeeder;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(RuleSeeder::class);
});

/**
 * A card payload with one merchant rule per value.
 *
 * @param  list<array<string, mixed>>  $rules
 * @return array<string, mixed>
 */
function groupsCard(string $name = 'Streaming', array $rules = [], string $color = '#d946ef'): array
{
    return ['name' => $name, 'color' => $color, 'rules' => $rules];
}

/** @return array<string, mixed> */
function groupsRule(string $value = 'Spotify', string $field = 'merchant', string $operator = 'contains', ?string $id = null, string|int $share = 1): array
{
    return ['id' => $id, 'direction' => 'out', 'share' => (string) $share, 'conditions' => [['field' => $field, 'operator' => $operator, 'value' => $value]]];
}

function groupsRentRule(): Rule
{
    return Rule::query()->where('group_key', 'rent')->sole();
}

test('saves a new group with its rules', function () {
    $response = $this->postJson(route('groups.store'), [
        ...groupsCard(rules: [
            groupsRule('Spotify'),
            ['id' => null, 'direction' => 'out', 'share' => '1', 'conditions' => [
                ['field' => 'merchant', 'operator' => 'contains', 'value' => 'Discovery'],
                ['field' => 'amount', 'operator' => 'less_than', 'value' => '20'],
            ]],
        ]),
        'before' => 'subscriptions',
    ])->assertCreated();

    $key = $response->json('card.key');
    $keys = app(GroupCatalog::class)->keys();

    expect($key)->toMatch('/^[0-9a-z]{26}$/')
        ->and(Group::query()->where('key', $key)->sole()->only('name', 'color'))->toBe(['name' => 'Streaming', 'color' => '#d946ef'])
        ->and($keys[array_search('subscriptions', $keys, true) - 1])->toBe($key)
        ->and($response->json('card.rules.1.conditions.1'))->toBe(['field' => 'amount', 'operator' => 'less_than', 'value' => '20,00']);

    $rules = Rule::query()->where('group_key', $key)->orderBy('position')->get();

    expect($rules->pluck('position')->all())->toBe([1, 2])
        ->and($rules[1]->conditions)->toBe([
            ['field' => 'merchant', 'operator' => 'contains', 'value' => 'Discovery'],
            ['field' => 'amount', 'operator' => 'less_than', 'value' => 2000],
        ]);
});

test('puts a new group before Other without a card below it', function () {
    $key = $this->postJson(route('groups.store'), groupsCard())->assertCreated()->json('card.key');

    expect(array_slice(app(GroupCatalog::class)->keys(), -2))->toBe([$key, 'other']);
});

test('updates name, colour and rules in place', function () {
    $rent = groupsRentRule();

    $response = $this->putJson(route('groups.update', 'rent'), groupsCard('Housing', [
        groupsRule('Miete', 'purpose', id: (string) $rent->id, share: 2),
        groupsRule('Hausverwaltung', 'counterparty'),
    ], '#EF4444'))->assertOk();

    $rules = Rule::query()->where('group_key', 'rent')->orderBy('position')->get();

    expect(Group::query()->where('key', 'rent')->sole()->only('name', 'color'))->toBe(['name' => 'Housing', 'color' => '#ef4444'])
        ->and($rules)->toHaveCount(2)
        ->and((string) $rules[0]->id)->toBe((string) $rent->id)
        ->and($rules[0]->share_divisor)->toBe(2)
        ->and($rules[1]->conditions[0]['value'])->toBe('Hausverwaltung')
        ->and($response->json('card.name'))->toBe('Housing')
        ->and(array_column($response->json('card.rules'), 'id'))->toBe($rules->map(fn (Rule $rule) => (string) $rule->id)->all());

    $this->putJson(route('groups.update', 'rent'), groupsCard('Housing', [groupsRule('Miete', 'purpose', id: (string) $rent->id)]))->assertOk();

    expect(Rule::query()->where('group_key', 'rent')->count())->toBe(1);
});

test('rejects invalid cards', function (Closure $payload, string $key, string $message, bool $update = false) {
    $groups = Group::count();
    $rules = Rule::count();
    $request = $update
        ? $this->putJson(route('groups.update', 'groceries'), $payload())
        : $this->postJson(route('groups.store'), $payload());

    $request->assertUnprocessable()->assertJsonValidationErrors([$key => $message]);

    expect(Group::count())->toBe($groups)->and(Rule::count())->toBe($rules);
})->with([
    'empty name' => [fn () => groupsCard(''), 'name', 'Enter a name.'],
    'long name' => [fn () => groupsCard(str_repeat('a', 41)), 'name', 'Use at most 40 characters.'],
    'duplicate name' => [fn () => groupsCard('RENT'), 'name', 'Another group already has this name.'],
    'duplicate name on update' => [fn () => groupsCard(' rent '), 'name', 'Another group already has this name.', true],
    'no conditions' => [fn () => groupsCard(rules: [['id' => null, 'direction' => 'out', 'share' => '1', 'conditions' => []]]), 'rules.0.conditions', 'Add at least one condition.'],
    'blank value' => [fn () => groupsCard(rules: [groupsRule('  ')]), 'rules.0.conditions.0.value', 'Enter a value.'],
    'long value' => [fn () => groupsCard(rules: [groupsRule(str_repeat('a', 101))]), 'rules.0.conditions.0.value', 'Use at most 100 characters.'],
    'zero amount' => [fn () => groupsCard(rules: [groupsRule('0', 'amount', 'greater_than')]), 'rules.0.conditions.0.value', 'Enter an amount above 0 with at most 2 decimals.'],
    'three decimals' => [fn () => groupsCard(rules: [groupsRule('1,234', 'amount', 'greater_than')]), 'rules.0.conditions.0.value', 'Enter an amount above 0 with at most 2 decimals.'],
    'text amount' => [fn () => groupsCard(rules: [groupsRule('abc', 'amount', 'greater_than')]), 'rules.0.conditions.0.value', 'Enter an amount above 0 with at most 2 decimals.'],
    'text operator on amount' => [fn () => groupsCard(rules: [groupsRule('5', 'amount', 'contains')]), 'rules.0.conditions.0.operator', 'Pick an operator.'],
    'share 0' => [fn () => groupsCard(rules: [groupsRule(share: 0)]), 'rules.0.share', 'Use a whole number from 1 to 10.'],
    'share 11' => [fn () => groupsCard(rules: [groupsRule(share: 11)]), 'rules.0.share', 'Use a whole number from 1 to 10.'],
]);

test('keeps the own name when updating a group', function () {
    $this->putJson(route('groups.update', 'rent'), groupsCard('RENT', [groupsRule('Miete', 'purpose', id: (string) groupsRentRule()->id, share: 3)]))
        ->assertOk();

    expect(Group::query()->where('key', 'rent')->sole()->name)->toBe('RENT');
});

test('rejects rule ids of another card', function () {
    $tegut = Rule::query()->where('conditions.0.value', 'TEGUT')->sole();

    $this->putJson(route('groups.update', 'rent'), groupsCard('Rent', [groupsRule('X', id: (string) $tegut->id)]))
        ->assertConflict();

    expect($tegut->fresh()->conditions[0]['value'])->toBe('TEGUT')
        ->and($tegut->fresh()->group_key)->toBe('groceries')
        ->and(groupsRentRule()->conditions[0]['value'])->toBe('Miete');
});

test('saves ignore rules without share', function () {
    $response = $this->putJson(route('groups.ignored.update'), ['rules' => [
        ['id' => null, 'direction' => 'in', 'conditions' => [['field' => 'purpose', 'operator' => 'contains', 'value' => 'Erstattung']]],
    ]])->assertOk();

    $rules = Rule::query()->where('ignore', true)->get();

    expect($rules)->toHaveCount(1)
        ->and($rules[0]->group_key)->toBeNull()
        ->and($rules[0]->share_divisor)->toBe(1)
        ->and($rules[0]->position)->toBe(1)
        ->and($response->json('card.rules.0'))->not->toHaveKey('share');
});

test('saves the group order', function () {
    $reversed = array_reverse(app(GroupCatalog::class)->keys());

    $this->putJson(route('groups.order'), ['groups' => $reversed])->assertNoContent();

    expect(app(GroupCatalog::class)->keys())->toBe($reversed);

    $this->putJson(route('groups.order'), ['groups' => array_slice($reversed, 1)])->assertConflict();

    expect(app(GroupCatalog::class)->keys())->toBe($reversed);
});

test('deletes a group and moves its entries', function () {
    importFixtureStatement();
    Rule::factory()->manual()->withCondition('merchant', 'contains', 'Netflix')->create(['group_key' => 'subscriptions', 'position' => null]);
    $before = Transaction::query()->where('group_key', 'subscriptions')->get()->keyBy(fn (Transaction $transaction) => (string) $transaction->id);

    $this->deleteJson(route('groups.destroy', 'subscriptions'), ['move_to' => 'other'])->assertNoContent();

    expect(Group::query()->where('key', 'subscriptions')->exists())->toBeFalse()
        ->and(Rule::query()->where('group_key', 'subscriptions')->count())->toBe(0)
        ->and(app(GroupCatalog::class)->keys())->not->toContain('subscriptions');

    $moved = Transaction::query()->whereKey($before->keys()->all())->get();

    expect($moved)->toHaveCount($before->count())
        ->and($moved->every(fn (Transaction $transaction) => $transaction->group_key === 'other'
            && $transaction->rule_id === null
            && $transaction->share_divisor === $before[(string) $transaction->id]->share_divisor))->toBeTrue();

    $spotify = html_entity_decode($this->get(route('overview', ['month' => '2026-06', 'group' => 'other', 'q' => 'Spotify']))->assertOk()->getContent());

    expect($spotify)->toMatch('/data-grouped-by[^>]*>\s*Picked manually\s*</');
});

test('cannot delete Other', function () {
    $this->deleteJson(route('groups.destroy', 'other'), ['move_to' => 'rent'])->assertForbidden();

    expect(Group::query()->where('key', 'other')->exists())->toBeTrue();
});

test('rejects moving entries into the deleted group', function () {
    $this->deleteJson(route('groups.destroy', 'rent'), ['move_to' => 'rent'])->assertUnprocessable();

    expect(Group::query()->where('key', 'rent')->exists())->toBeTrue();
});

test('deletes only always rules through the rule route', function () {
    $manual = Rule::factory()->manual()->withCondition('merchant', 'contains', 'Kiosk Nord')->create(['group_key' => 'takeaway', 'position' => null]);

    $this->deleteJson(route('rules.destroy', $manual))->assertNoContent();
    $this->deleteJson(route('rules.destroy', groupsRentRule()))->assertNotFound();

    expect(Rule::find($manual->id))->toBeNull()
        ->and(groupsRentRule())->not->toBeNull();
});
