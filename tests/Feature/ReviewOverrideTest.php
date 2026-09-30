<?php

use App\Enums\RuleDirection;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->seed(RuleSeeder::class);
});

/** Uploads the June fixture; index 1 is "Miete" (rent ÷3), 60 salary, 63 ignored, 65 the only queue entry. */
function uploadJuneForOverride(): void
{
    test()->post(route('upload.store'), ['statements' => [statementUpload()]])
        ->assertRedirect(route('upload.review', '2026-06'));
}

function overrideEntry(int $entry, string $group, string $period = '2026-06'): TestResponse
{
    return test()->postJson(route('upload.override', $period), compact('entry', 'group'));
}

/** The markup of the grouped entry row $index, or of the To review entry with data-review-entry. */
function reviewRow(string $html, int $index, string $attribute = 'data-review-row'): string
{
    preg_match('/'.$attribute.'="'.$index.'".*?<\/li>/s', $html, $m);

    return html_entity_decode($m[0] ?? '');
}

/** The opening tag of the first element in $html carrying $attribute. */
function reviewTagWith(string $html, string $attribute): string
{
    preg_match('/<[a-z]+\b(?:[^>"]|"[^"]*")*\s'.$attribute.'[\s>=](?:[^>"]|"[^"]*")*>/', $html, $m);

    return $m[0] ?? '';
}

function reviewRentTransaction(): Transaction
{
    return Transaction::query()->where('purpose', 'Miete')->where('amount_cents', '<', 0)->sole();
}

test('redirects guests from the override route', function () {
    auth()->logout();

    $this->post('/upload/review/2026-06/override')->assertRedirect(route('login'));
    $this->postJson('/upload/review/2026-06/override')->assertUnauthorized();
});

test('overrides the group of a rule entry', function () {
    uploadJuneForOverride();

    overrideEntry(1, 'other')->assertOk()->assertExactJson(['overrides' => ['1' => 'other']]);

    expect(session('statement_import.months.2026-06.overrides'))->toBe([1 => 'other']);
});

test('resets when the rule group is picked', function () {
    uploadJuneForOverride();
    overrideEntry(1, 'other')->assertOk();

    $response = overrideEntry(1, 'rent')->assertOk();

    expect($response->getContent())->toBe('{"overrides":{}}')
        ->and(session('statement_import.months.2026-06.overrides'))->toBe([]);
});

test('rejects overrides of entries without a rule group', function (int $entry) {
    uploadJuneForOverride();

    overrideEntry($entry, 'other')->assertUnprocessable()->assertJsonValidationErrors(['entry' => 'This entry cannot be regrouped.']);

    expect(session('statement_import.months.2026-06.overrides'))->toBe([]);
})->with(['queue entry' => 65, 'income' => 60, 'ignored' => 63, 'missing' => 999]);

test('rejects unknown groups', function () {
    uploadJuneForOverride();

    overrideEntry(1, 'nope')->assertUnprocessable()->assertJsonValidationErrors('group');
});

test('answers 409 when nothing is pending', function () {
    uploadJuneForOverride();
    $this->post(route('upload.discard'));

    overrideEntry(1, 'other')->assertConflict()->assertJson(['redirect' => route('upload')]);
});

test('keeps the override across a reload', function () {
    uploadJuneForOverride();
    overrideEntry(1, 'other')->assertOk();

    $row = reviewRow($this->get(route('upload.review', '2026-06'))->assertOk()->getContent(), 1);

    expect(reviewTagWith($row, 'data-override-marker'))->not->toContain('display: none')
        ->and($row)->toMatch('/data-group-chip.*?<span class="truncate"[^>]*>Other<\/span>/s')
        ->and(reviewTagWith($row, 'data-reset'))->toContain('title="Reset to Rent"')->not->toContain('display: none')
        ->and(reviewTagWith($row, 'data-share'))->toContain('display: none')
        ->and($row)->toMatch('/data-grouped-by[^>]*><span[^>]*>Picked manually<\/span>/');
});

test('renders a clickable chip only for rule entries', function () {
    uploadJuneForOverride();

    $html = $this->get(route('upload.review', '2026-06'))->assertOk()->getContent();
    $row = reviewRow($html, 1);

    preg_match_all('/data-pick-override="([a-z_]+)"/', $row, $picks);
    preg_match_all('/data-pick-override="([a-z_]+)"[^>]*aria-checked="true"/', $row, $checked);

    expect(substr_count($html, 'data-group-chip'))->toBe(64)
        ->and(reviewRow($html, 60))->not->toContain('data-group-chip')
        ->and(reviewRow($html, 63))->not->toContain('data-group-chip')
        ->and(reviewRow($html, 65, 'data-review-entry'))->not->toContain('data-group-chip')
        ->and($picks[1])->toBe(array_keys(config('expenses.groups')))
        ->and($checked[1])->toBe(['rent']);
});

test('shows entry details for every entry', function () {
    uploadJuneForOverride();

    $html = $this->get(route('upload.review', '2026-06'))->assertOk()->getContent();
    $rent = reviewRow($html, 1);
    $ignored = reviewRow($html, 63);
    $queued = reviewRow($html, 65, 'data-review-entry');

    expect(substr_count($html, 'data-entry-details'))->toBe(69)
        ->and($rent)->toContain('Dauerauftrag/Terminueberw.')
        ->toMatch('/Counterparty<\/dt>\s*<dd[^>]*>—<\/dd>/')
        ->toContain('01.06.2026')
        ->toContain('−386,07 € (−1.158,20 € / 3)')
        ->toMatch('/data-grouped-by[^>]*><span[^>]*>Rule · purpose contains "Miete"<\/span>/')
        ->toMatch('/data-purpose[^>]*>Miete</')
        ->and($ignored)->toMatch('/data-grouped-by[^>]*>Rule · purpose contains "Miete"</')
        ->toContain('· ignored')
        ->and(reviewRow($html, 60))->toMatch('/data-grouped-by[^>]*>Not grouped</')
        ->and($queued)->toContain('>Unassigned<')
        ->toMatch('/data-grouped-by[^>]*><span[^>]*>Not grouped<\/span>/');
});

test('names manual rules like the overview', function () {
    Rule::factory()->manual()->withCondition('merchant', 'contains', 'Echtzeitüberweisung')->create([
        'direction' => RuleDirection::Out,
        'group_key' => 'other',
        'position' => null,
    ]);
    uploadJuneForOverride();

    $row = reviewRow($this->get(route('upload.review', '2026-06'))->assertOk()->getContent(), 65);

    expect($row)->toContain('data-group-chip')
        ->toMatch('/data-grouped-by[^>]*><span[^>]*>Manual rule · merchant contains "Echtzeitüberweisung"<\/span>/');
});

test('describes hand picks and always choices in the queue', function () {
    uploadJuneForOverride();
    $this->postJson(route('upload.assign', '2026-06'), ['entry' => 65, 'group' => 'health'])->assertOk();

    expect(reviewRow($this->get(route('upload.review', '2026-06'))->getContent(), 65, 'data-review-entry'))
        ->toMatch('/data-grouped-by[^>]*><span[^>]*>Picked manually<\/span>/');

    $this->postJson(route('upload.always', '2026-06'), ['entry' => 65, 'always' => true])->assertOk();

    expect(reviewRow($this->get(route('upload.review', '2026-06'))->getContent(), 65, 'data-review-entry'))
        ->toMatch('/data-grouped-by[^>]*><span[^>]*>Always use for Echtzeitüberweisung<\/span>/');
});

test('keeps the override for its month only', function () {
    uploadMonths(['2026-05', '2026-06']);

    overrideEntry(1, 'other', '2026-05')->assertOk();
    $this->get(route('upload.review', '2026-06'))->assertOk();

    expect(session('statement_import.months.2026-06.overrides'))->toBe([])
        ->and(reviewTagWith(reviewRow($this->get(route('upload.review', '2026-05'))->getContent(), 1), 'data-override-marker'))
        ->not->toContain('display: none');
});

test('drops the override with a skipped month', function () {
    uploadMonths(['2026-05', '2026-06']);
    overrideEntry(1, 'other', '2026-05')->assertOk();

    $this->post(route('upload.skip', '2026-05'))->assertRedirect(route('upload.review', '2026-06'));
    assignOpenEntries('other', '2026-06');
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    expect(Transaction::query()->where('period', '2026-05')->exists())->toBeFalse()
        ->and(reviewRentTransaction()->group_key)->toBe('rent');
});

test('stores the override on confirm without touching the rule', function () {
    uploadJuneForOverride();
    $rules = Rule::count();

    overrideEntry(1, 'other')->assertOk();
    overrideEntry(1, 'rent')->assertOk();
    overrideEntry(1, 'other')->assertOk();
    assignOpenEntries();
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rent = reviewRentTransaction();
    $rentRule = Rule::query()->where('conditions.0.field', 'purpose')->where('conditions.0.value', 'Miete')->where('direction', 'out')->sole();
    $energy = Transaction::query()->where('merchant', 'RhoenEnergie Fulda')->first();

    expect($rent->group_key)->toBe('other')
        ->and($rent->share_divisor)->toBe(1)
        ->and($rent->rule_id)->toBeNull()
        ->and($rent->ignored)->toBeFalse()
        ->and($rentRule->group_key)->toBe('rent')
        ->and($rentRule->share_divisor)->toBe(3)
        ->and(Rule::count())->toBe($rules)
        ->and($energy->group_key)->toBe('utilities')
        ->and($energy->share_divisor)->toBe(3);
});

test('shows picked manually on the overview after confirm', function () {
    uploadJuneForOverride();
    overrideEntry(1, 'other')->assertOk();
    assignOpenEntries();
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $html = $this->get(route('overview', ['month' => '2026-06', 'group' => 'other']))->assertOk()->getContent();
    preg_match('/data-entry="'.reviewRentTransaction()->id.'".*?<\/li>/s', $html, $m);

    expect(html_entity_decode($m[0] ?? ''))->toContain('−1.158,20 €')
        ->toContain('>Other<')
        ->not->toContain('÷3')
        ->toMatch('/data-grouped-by[^>]*>Picked manually</');
});

test('replaces an import with the override', function () {
    importFixtureStatement();
    uploadJuneForOverride();

    overrideEntry(1, 'other')->assertOk();
    assignOpenEntries();
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rent = reviewRentTransaction();

    expect($rent->group_key)->toBe('other')
        ->and($rent->rule_id)->toBeNull();
});
