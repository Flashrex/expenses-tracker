<?php

use App\Enums\RuleDirection;
use App\Enums\RuleSource;
use App\Models\Rule;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RuleSeeder;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function uploadForReview(bool $seeded = true): void
{
    if ($seeded) {
        test()->seed(RuleSeeder::class);
    }

    test()->actingAs(test()->user)
        ->post(route('upload.store'), ['statements' => [statementUpload()]])
        ->assertRedirect(route('upload.review', '2026-06'));
}

/** @return list<int> indexes of the fixture entries with merchant TEGUT */
function tegutIndexes(): array
{
    $indexes = [];

    foreach (fixtureStatement()->entries as $i => $entry) {
        if ($entry->merchant === 'TEGUT') {
            $indexes[] = $i;
        }
    }

    return $indexes;
}

function manualRules()
{
    return Rule::query()->where('source', 'manual');
}

/** The opening tag of the first element carrying $attribute (quoted values may contain ">"). */
function openingTag(string $html, string $tag, string $attribute): ?string
{
    $inTag = '(?:[^>"]|"[^"]*")*';

    return preg_match('/<'.$tag.'\b'.$inTag.$attribute.$inTag.'>/', $html, $m) ? $m[0] : null;
}

/** Whether the server rendered the plain attribute (not Alpine's :binding of it). */
function hasAttribute(?string $tag, string $attribute): bool
{
    return (bool) preg_match('/\s'.$attribute.'[\s>=]/', (string) $tag);
}

test('redirects guests from the queue routes', function () {
    $this->post('/upload/review/2026-06/assign')->assertRedirect(route('login'));
    $this->post('/upload/review/2026-06/always')->assertRedirect(route('login'));
    $this->postJson('/upload/review/2026-06/assign')->assertUnauthorized();
    $this->postJson('/upload/review/2026-06/always')->assertUnauthorized();
});

test('shows the unmatched transfer in the review queue', function () {
    uploadForReview();

    $html = $this->get(route('upload.review', '2026-06'))
        ->assertOk()
        ->assertSee('To review')
        ->assertSeeText('1 to review')
        ->assertSee('Always use this group for')
        ->assertSee('Assign a group to every entry to confirm.')
        ->assertSeeInOrder(['To review', 'Echtzeitüberweisung', 'Netflix + Router'])
        ->getContent();

    expect(substr_count($html, 'data-review-entry='))->toBe(1)
        ->and($html)->toContain('data-review-entry="65"')
        ->and(substr_count($html, 'data-pick='))->toBe(10)
        ->and(hasAttribute(openingTag($html, 'button', 'data-confirm'), 'disabled'))->toBeTrue()
        ->and(substr_count($html, 'data-review-row='))->toBe(68);
});

test('hides the queue when every entry is matched', function () {
    $this->seed(RuleSeeder::class);
    Rule::factory()->manual()->withCondition('merchant', 'contains', 'Echtzeitüberweisung')->create(['direction' => RuleDirection::Out, 'group_key' => 'other', 'position' => null]);
    uploadForReview(seeded: false);

    $html = $this->get(route('upload.review', '2026-06'))
        ->assertOk()
        ->assertDontSee('To review')
        ->getContent();

    expect($html)->not->toContain('data-review-counter')
        ->and($html)->not->toContain('data-review-done')
        ->and(hasAttribute(openingTag($html, 'button', 'data-confirm'), 'disabled'))->toBeFalse()
        ->and(substr_count($html, 'data-review-row='))->toBe(69);
});

test('assigns a group to a queue entry', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])
        ->assertOk()
        ->assertJsonPath('entries.65.group', 'other')
        ->assertJsonPath('entries.65.always', false)
        ->assertJsonPath('open', 0);

    expect(session('statement_import.months.2026-06.picks'))->toBe([65 => 'other'])
        ->and(Transaction::count())->toBe(0)
        ->and(manualRules()->count())->toBe(0);

    $html = $this->get(route('upload.review', '2026-06'))->assertOk()->getContent();

    expect(openingTag($html, 'span', 'data-review-done'))->not->toContain('display: none')
        ->and(openingTag($html, 'span', 'data-review-counter'))->toContain('display: none')
        ->and(hasAttribute(openingTag($html, 'button', 'data-confirm'), 'disabled'))->toBeFalse()
        ->and(openingTag($html, 'button', 'data-pick="other"'))->toContain('aria-pressed="true"');
});

test('rejects invalid assignments', function (array $payload) {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), $payload)->assertUnprocessable();

    expect(session('statement_import.months.2026-06.picks'))->toBeEmpty();
})->with([
    'unknown group' => [['entry' => 65, 'group' => 'pets']],
    'rule-matched entry' => [['entry' => 1, 'group' => 'other']],
    'income entry' => [['entry' => 60, 'group' => 'other']],
    'ignored entry' => [['entry' => 63, 'group' => 'other']],
    'missing entry index' => [['entry' => 999, 'group' => 'other']],
    'no entry' => [['group' => 'other']],
    'no group' => [['entry' => 65]],
]);

test('refuses always without a group', function () {
    uploadForReview();

    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('always');

    expect(session('statement_import.months.2026-06.always'))->toBeEmpty();
});

test('answers 409 when nothing is pending', function () {
    $this->actingAs($this->user);

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 0, 'group' => 'other'])
        ->assertConflict()
        ->assertJson(['redirect' => route('upload')]);

    $this->postJson(route('upload.always', '2026-06'), ['entry' => 0, 'always' => true])
        ->assertConflict()
        ->assertJson(['redirect' => route('upload')]);
});

test('keeps the pending import while assigning', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])->assertOk();

    $html = $this->get(route('upload.review', '2026-06'))->assertOk()->getContent();

    expect(hasAttribute(openingTag($html, 'input', 'data-always'), 'checked'))->toBeTrue();
});

test('applies always to the other unassigned entries of the merchant', function () {
    uploadForReview(seeded: false);
    $tegut = tegutIndexes();

    expect(count($tegut))->toBeGreaterThanOrEqual(2);

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => $tegut[0], 'group' => 'groceries'])->assertOk();
    $response = $this->postJson(route('upload.always', '2026-06'), ['entry' => $tegut[0], 'always' => true])
        ->assertOk()
        ->assertJsonPath('open', 65 - count($tegut));

    foreach ($tegut as $i) {
        $response->assertJsonPath("entries.$i.group", 'groceries')
            ->assertJsonPath("entries.$i.always", true);
    }

    expect(session('statement_import.months.2026-06.always'))->toBe(['TEGUT' => ['merchant' => 'TEGUT', 'group_key' => 'groceries']]);
});

test('returns followers to unassigned when always is unticked', function () {
    uploadForReview(seeded: false);
    $tegut = tegutIndexes();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => $tegut[0], 'group' => 'groceries'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => $tegut[0], 'always' => true])->assertOk();

    $this->postJson(route('upload.always', '2026-06'), ['entry' => $tegut[0], 'always' => false])
        ->assertOk()
        ->assertJsonPath("entries.{$tegut[0]}.group", 'groceries')
        ->assertJsonPath("entries.{$tegut[1]}.group", null)
        ->assertJsonPath('open', 64);
});

test('blocks confirm while entries are unassigned', function () {
    uploadForReview();

    $this->post(route('upload.confirm', '2026-06'))
        ->assertRedirect(route('upload.review', '2026-06'))
        ->assertSessionHas('review_error', '1 entry still needs a group.');

    expect(session('statement_import'))->not->toBeNull()
        ->and(Statement::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);

    $html = $this->get(route('upload.review', '2026-06'))->assertOk()->getContent();

    expect($html)->toMatch('/<div[^>]*data-review-error[^>]*>.*?1 entry still needs a group\./s');
});

test('counts all open entries when blocking confirm', function () {
    uploadForReview(seeded: false);

    $this->post(route('upload.confirm', '2026-06'))
        ->assertSessionHas('review_error', '65 entries still need a group.');

    expect(Statement::count())->toBe(0);
});

test('does not need groups for income and ignored entries', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();

    $this->post(route('upload.confirm', '2026-06'))
        ->assertRedirect(route('upload'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'June 2026 imported · 69 entries']);

    $salary = Transaction::where('amount_cents', 134819)->sole();
    $ignored = Transaction::where('ignored', true)->get();

    expect($salary->group_key)->toBeNull()
        ->and($salary->ignored)->toBeFalse()
        ->and($ignored)->toHaveCount(3)
        ->and($ignored->every(fn (Transaction $transaction) => $transaction->group_key === null))->toBeTrue();
});

test('stores the manual assignment on confirm', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $transfer = Transaction::where('amount_cents', -20000)->sole();

    expect($transfer->group_key)->toBe('other')
        ->and($transfer->share_divisor)->toBe(1)
        ->and($transfer->ignored)->toBeFalse()
        ->and($transfer->rule_id)->toBeNull()
        ->and(manualRules()->count())->toBe(0)
        ->and(Transaction::whereNotNull('group_key')->count())->toBe(65);
});

test('creates the rule only when ticked and only on confirm', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])->assertOk();

    expect(manualRules()->count())->toBe(0);

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rule = manualRules()->sole();

    expect($rule->conditions)->toBe([['field' => 'merchant', 'operator' => 'contains', 'value' => 'Echtzeitüberweisung']])
        ->and($rule->direction)->toBe(RuleDirection::Out)
        ->and($rule->position)->toBeNull()
        ->and($rule->group_key)->toBe('other')
        ->and($rule->share_divisor)->toBe(1)
        ->and($rule->ignore)->toBeFalse()
        ->and($rule->source)->toBe(RuleSource::Manual)
        ->and(Transaction::where('amount_cents', -20000)->sole()->rule_id)->toBe($rule->id)
        ->and(Rule::count())->toBe(43);
});

test('does not create the rule when unticked again', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => false])->assertOk();
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $transfer = Transaction::where('amount_cents', -20000)->sole();

    expect(manualRules()->count())->toBe(0)
        ->and($transfer->group_key)->toBe('other')
        ->and($transfer->rule_id)->toBeNull();
});

test('does not create the rule on discard', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])->assertOk();
    $this->post(route('upload.discard'))->assertRedirect(route('upload'));

    expect(manualRules()->count())->toBe(0);
});

test('discards the picks when leaving the review', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->get(route('overview'));

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertConflict();
});

test('uses the manual rule on the next import', function () {
    uploadForReview();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])->assertOk();
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rule = manualRules()->sole();

    uploadForReview(seeded: false);

    expect(session('statement_import.months.2026-06.assignments.65'))->toBe([
        'group_key' => 'other',
        'share_divisor' => 1,
        'ignored' => false,
        'rule_id' => $rule->id,
    ]);

    $html = $this->get(route('upload.review', '2026-06'))
        ->assertOk()
        ->assertSee('Replace import')
        ->getContent();

    expect($html)->not->toContain('data-review-entry=')
        ->and($html)->not->toContain('data-review-counter')
        ->and(hasAttribute(openingTag($html, 'button', 'data-confirm'), 'disabled'))->toBeFalse();

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $transfer = Transaction::where('amount_cents', -20000)->sole();

    expect($transfer->group_key)->toBe('other')
        ->and($transfer->rule_id)->toBe($rule->id)
        ->and(manualRules()->count())->toBe(1);
});

test('stores the always group for every entry of the merchant', function () {
    uploadForReview(seeded: false);
    $tegut = tegutIndexes();

    $this->postJson(route('upload.assign', '2026-06'), ['entry' => $tegut[0], 'group' => 'groceries'])->assertOk();
    $this->postJson(route('upload.always', '2026-06'), ['entry' => $tegut[0], 'always' => true])->assertOk();
    assignOpenEntries('other');

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rule = manualRules()->sole();
    $tegutTransactions = Transaction::where('merchant', 'TEGUT')->get();

    expect($rule->conditions[0]['value'])->toBe('TEGUT')
        ->and($rule->group_key)->toBe('groceries')
        ->and($tegutTransactions)->toHaveCount(count($tegut))
        ->and($tegutTransactions->every(fn (Transaction $transaction) => $transaction->group_key === 'groceries' && $transaction->rule_id === $rule->id))->toBeTrue()
        ->and(Transaction::whereNotNull('rule_id')->count())->toBe(count($tegut))
        ->and(Transaction::whereNull('group_key')->count())->toBe(4);
});
