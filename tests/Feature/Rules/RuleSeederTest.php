<?php

use App\Enums\RuleSource;
use App\Models\Rule;
use App\Services\Groups\GroupCatalog;
use Database\Seeders\RuleSeeder;

function seededRule(string $pattern, string $direction = 'out'): ?Rule
{
    return Rule::query()->where('conditions.0.value', $pattern)->where('direction', $direction)->first();
}

test('seeds every configured rule once', function () {
    $this->seed(RuleSeeder::class);

    expect(Rule::count())->toBe(42)->toBe(count(config('expenses.rules')))
        ->and(Rule::all()->every(fn (Rule $rule) => $rule->source === RuleSource::System))->toBeTrue();

    $rent = seededRule('Miete');
    expect($rent->conditions)->toBe([['field' => 'purpose', 'operator' => 'contains', 'value' => 'Miete']])
        ->and($rent->group_key)->toBe('rent')
        ->and($rent->share_divisor)->toBe(3)
        ->and($rent->position)->toBe(1)
        ->and($rent->ignore)->toBeFalse();

    $reimbursement = seededRule('Miete', 'in');
    expect($reimbursement->ignore)->toBeTrue()
        ->and($reimbursement->group_key)->toBeNull();

    expect(seededRule('Abschluss')->conditions[0]['field'])->toBe('type');

    $groceries = Rule::query()->where('group_key', 'groceries')->orderBy('position')->get();
    expect($groceries->pluck('position')->all())->toBe(range(1, 8))
        ->and($groceries->map(fn (Rule $rule) => $rule->conditions[0]['value'])->all())
        ->toBe(['TEGUT', 'REWE', 'EDEKA', 'ALDI SUED', 'BAECKEREI HAPP', 'TEO FULDA', 'ROSSMANN', 'MUELLER']);
});

test('references only existing groups', function () {
    $this->seed(RuleSeeder::class);

    $groups = app(GroupCatalog::class)->keys();

    Rule::all()->each(function (Rule $rule) use ($groups) {
        if ($rule->ignore) {
            expect($rule->group_key)->toBeNull();
        } else {
            expect($rule->group_key)->toBeIn($groups);
        }
    });
});

test('is idempotent', function () {
    $this->seed(RuleSeeder::class);
    $ids = Rule::query()->pluck('id')->sort()->values()->all();

    $this->seed(RuleSeeder::class);

    expect(Rule::count())->toBe(42)
        ->and(Rule::query()->pluck('id')->sort()->values()->all())->toBe($ids);
});

test('never resets edited rules', function () {
    $this->seed(RuleSeeder::class);
    seededRule('Miete')->update(['share_divisor' => 2]);
    seededRule('REWE')->delete();

    $this->seed(RuleSeeder::class);

    expect(seededRule('Miete')->share_divisor)->toBe(2)
        ->and(seededRule('REWE'))->toBeNull()
        ->and(Rule::count())->toBe(41);
});

test('keeps manual rules', function () {
    $manual = Rule::factory()->manual()->withCondition('merchant', 'contains', 'REWE')->create(['position' => null]);

    $this->seed(RuleSeeder::class);

    expect(Rule::find($manual->id))->not->toBeNull()
        ->and(Rule::count())->toBe(43);
});

test('rejects invalid rule config', function (array $row) {
    config()->set('expenses.rules', [...config('expenses.rules'), $row]);

    expect(fn () => $this->seed(RuleSeeder::class))->toThrow(InvalidArgumentException::class)
        ->and(Rule::count())->toBe(0);
})->with([
    'unknown group' => [['field' => 'merchant', 'pattern' => 'ZOO', 'direction' => 'out', 'group_key' => 'pets']],
    'neither group nor ignore' => [['field' => 'merchant', 'pattern' => 'ZOO', 'direction' => 'out']],
    'group and ignore' => [['field' => 'merchant', 'pattern' => 'ZOO', 'direction' => 'out', 'group_key' => 'other', 'ignore' => true]],
    'share divisor 0' => [['field' => 'merchant', 'pattern' => 'ZOO', 'direction' => 'out', 'group_key' => 'other', 'share_divisor' => 0]],
    'unknown field' => [['field' => 'iban', 'pattern' => 'ZOO', 'direction' => 'out', 'group_key' => 'other']],
    'unknown direction' => [['field' => 'merchant', 'pattern' => 'ZOO', 'direction' => 'both', 'group_key' => 'other']],
    'empty pattern' => [['field' => 'merchant', 'pattern' => '  ', 'direction' => 'out', 'group_key' => 'other']],
    'duplicate' => [['field' => 'merchant', 'pattern' => 'TEGUT', 'direction' => 'out', 'group_key' => 'other']],
]);
