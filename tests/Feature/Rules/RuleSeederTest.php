<?php

use App\Enums\RuleField;
use App\Enums\RuleSource;
use App\Models\Rule;
use Database\Seeders\RuleSeeder;

/** The configured rules without the one for the given pattern (merchant/out). */
function rulesWithout(string $pattern): array
{
    return array_values(array_filter(
        config('expenses.rules'),
        fn (array $row) => ! ($row['pattern'] === $pattern && $row['field'] === 'merchant' && $row['direction'] === 'out'),
    ));
}

function seededRule(string $pattern, string $direction = 'out'): ?Rule
{
    return Rule::query()->where('pattern', $pattern)->where('direction', $direction)->first();
}

test('seeds every configured rule', function () {
    $this->seed(RuleSeeder::class);

    expect(Rule::count())->toBe(42)->toBe(count(config('expenses.rules')))
        ->and(Rule::all()->every(fn (Rule $rule) => $rule->source === RuleSource::Seeded))->toBeTrue();

    $rent = seededRule('Miete');
    expect($rent->group_key)->toBe('rent')
        ->and($rent->share_divisor)->toBe(3)
        ->and($rent->priority)->toBe(100)
        ->and($rent->ignore)->toBeFalse();

    expect(seededRule('AMAZON PRIM')->priority)->toBe(200);

    $reimbursement = seededRule('Miete', 'in');
    expect($reimbursement->ignore)->toBeTrue()
        ->and($reimbursement->group_key)->toBeNull();

    expect(seededRule('Abschluss')->field)->toBe(RuleField::Type);
});

test('references only configured groups', function () {
    $this->seed(RuleSeeder::class);

    $groups = array_keys(config('expenses.groups'));

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

test('syncs changed and removed config rules', function () {
    $this->seed(RuleSeeder::class);
    $tegutId = seededRule('TEGUT')->id;

    $rules = array_map(
        fn (array $row) => $row['pattern'] === 'TEGUT' ? [...$row, 'group_key' => 'other'] : $row,
        rulesWithout('REWE'),
    );
    $rules[] = ['field' => 'merchant', 'pattern' => 'NORMA', 'direction' => 'out', 'group_key' => 'groceries'];
    config()->set('expenses.rules', $rules);

    $this->seed(RuleSeeder::class);

    $tegut = seededRule('TEGUT');
    expect($tegut->group_key)->toBe('other')
        ->and($tegut->id)->toBe($tegutId)
        ->and(seededRule('REWE'))->toBeNull()
        ->and(seededRule('NORMA'))->not->toBeNull()
        ->and(Rule::count())->toBe(42);
});

test('keeps manual rules', function () {
    $manual = Rule::factory()->manual()->create(['pattern' => 'REWE']);
    config()->set('expenses.rules', rulesWithout('REWE'));

    $this->seed(RuleSeeder::class);

    expect(Rule::find($manual->id))->not->toBeNull();
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
