<?php

use App\Models\Rule;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'me@example.com']);
});

function uploadJuneStatement(): void
{
    test()->actingAs(test()->user)
        ->post(route('upload.store'), ['statements' => [statementUpload()]])
        ->assertRedirect(route('upload.review', '2026-06'));
}

test('redirects guests from the upload routes', function () {
    $this->get('/upload/review/2026-06')->assertRedirect(route('login'));
    $this->post('/upload')->assertRedirect(route('login'));
    $this->post('/upload/review/2026-06/confirm')->assertRedirect(route('login'));
    $this->post('/upload/discard')->assertRedirect(route('login'));
});

test('shows the drop zone', function () {
    $this->actingAs($this->user)
        ->get(route('upload'))
        ->assertOk()
        ->assertSee('Drop your ING statements here')
        ->assertSee('name="statements[]"', false)
        ->assertSee('multiple', false)
        ->assertSee('accept="application/pdf,.pdf"', false)
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertDontSee('Imported');
});

test('shows imported months as chips', function () {
    Statement::factory()->create(['period' => '2026-05', 'number' => 5]);
    Statement::factory()->create(['period' => '2026-06']);
    Statement::factory()->create(['period' => '2026-07', 'number' => 7, 'confirmed_at' => null]);

    $this->actingAs($this->user)
        ->get(route('upload'))
        ->assertSee('Imported')
        ->assertSeeInOrder(['Jun 2026', 'May 2026'])
        ->assertDontSee('Jul 2026');
});

test('rejects a file that is not a pdf', function () {
    $this->actingAs($this->user)
        ->post(route('upload.store'), ['statements' => [UploadedFile::fake()->create('a.txt', 10, 'text/plain')]])
        ->assertSessionHasErrors(['statements' => 'Please choose a PDF file (max. 10 MB).'])
        ->assertSessionMissing('statement_import');
});

test('rejects a missing file', function () {
    $this->actingAs($this->user)
        ->post(route('upload.store'), [])
        ->assertSessionHasErrors(['statements' => 'Please choose a PDF file (max. 10 MB).']);
});

test('rejects a pdf larger than 10 MB', function () {
    $this->actingAs($this->user)
        ->post(route('upload.store'), ['statements' => [UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')]])
        ->assertSessionHasErrors(['statements' => 'Please choose a PDF file (max. 10 MB).']);
});

test('rejects a pdf that is not an ING statement', function () {
    $this->actingAs($this->user)
        ->from(route('upload'))
        ->post(route('upload.store'), ['statements' => [UploadedFile::fake()->createWithContent('x.pdf', blankPdf())]])
        ->assertRedirect(route('upload'))
        ->assertSessionHasErrors(['statements' => "This doesn't look like an ING statement."]);

    expect(Statement::count())->toBe(0);
});

test('parses the upload and shows the review', function () {
    Storage::fake('local');

    uploadJuneStatement();

    expect(session('statement_import.months.2026-06.entries'))->toHaveCount(69)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Statement::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);

    $response = $this->get(route('upload.review', '2026-06'))
        ->assertOk()
        ->assertSee('June 2026')
        ->assertSee('Statement 6 · 69 entries')
        ->assertSee('Confirm import')
        ->assertSee('Discard')
        ->assertSee('−1.158,20 €')
        ->assertSee('+1.348,19 €')
        ->assertSee('2.272,02 €')
        ->assertSee('1.952,30 €')
        ->assertSee('Netflix + Router')
        ->assertSee('01.06.')
        ->assertDontSee('already imported');

    $html = $response->getContent();

    expect(substr_count($html, 'data-review-entry='))->toBe(65)
        ->and(substr_count($html, 'data-review-row='))->toBe(4);
});

test('keeps the pending import when the review is reloaded', function () {
    uploadJuneStatement();

    $this->get(route('upload.review', '2026-06'))->assertOk();
    $this->get(route('upload.review', '2026-06'))->assertOk();
});

test('redirects to upload when nothing is pending', function () {
    $this->actingAs($this->user);

    $this->get(route('upload.review', '2026-06'))->assertRedirect(route('upload'));
    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    expect(Statement::count())->toBe(0);
});

test('stores the statement and entries on confirm', function () {
    uploadJuneStatement();
    assignOpenEntries();

    $this->post(route('upload.confirm', '2026-06'))
        ->assertRedirect(route('upload'))
        ->assertSessionHas('status', 'June 2026 imported · 69 entries')
        ->assertSessionMissing('statement_import');

    expect(Statement::count())->toBe(1);

    $statement = Statement::first();

    expect($statement->number)->toBe(6)
        ->and($statement->period)->toBe('2026-06')
        ->and($statement->statement_date->toDateString())->toBe('2026-06-30')
        ->and($statement->old_balance_cents)->toBe(227202)
        ->and($statement->new_balance_cents)->toBe(195230)
        ->and($statement->confirmed_at)->not->toBeNull();

    $transactions = Transaction::all();

    expect($transactions)->toHaveCount(69)
        ->and(Transaction::sum('amount_cents'))->toBe(-31972);

    foreach ($transactions as $transaction) {
        expect((string) $transaction->statement_id)->toBe((string) $statement->id)
            ->and($transaction->period)->toBe('2026-06')
            ->and($transaction->group_key)->toBe($transaction->direction === 'out' ? 'other' : null)
            ->and($transaction->share_divisor)->toBe(1)
            ->and($transaction->ignored)->toBeFalse()
            ->and($transaction->rule_id)->toBeNull();
    }

    $energy = Transaction::where('amount_cents', -19700)->sole();

    expect($energy->type)->toBe('Lastschrift')
        ->and($energy->counterparty)->toBe('RhoenEnergie Fulda GmbH')
        ->and($energy->merchant)->toBe('RhoenEnergie Fulda')
        ->and($energy->direction)->toBe('out')
        ->and($energy->booked_on->toDateString())->toBe('2026-06-01');

    $this->get(route('upload'))
        ->assertSee('June 2026 imported · 69 entries')
        ->assertSee('Jun 2026');
});

test('asks to replace an already imported statement', function () {
    $old = Statement::factory()->create();
    Transaction::factory()->count(2)->create(['statement_id' => $old->id]);

    uploadJuneStatement();

    $this->get(route('upload.review', '2026-06'))
        ->assertSee('June 2026 (statement 6) is already imported. Confirming replaces it.')
        ->assertSee('Replace import')
        ->assertSee('Cancel')
        ->assertDontSee('Confirm import');
});

test('replaces the existing statement on confirm', function () {
    $old = Statement::factory()->create();
    Transaction::factory()->count(2)->create(['statement_id' => $old->id]);

    uploadJuneStatement();
    assignOpenEntries();

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    expect(Statement::count())->toBe(1)
        ->and((string) Statement::first()->id)->not->toBe((string) $old->id)
        ->and(Transaction::count())->toBe(69)
        ->and(Transaction::where('statement_id', $old->id)->count())->toBe(0);
});

test('does not treat another month with the same number as duplicate', function () {
    Statement::factory()->create(['period' => '2025-06']);

    uploadJuneStatement();

    $this->get(route('upload.review', '2026-06'))->assertDontSee('already imported');
    assignOpenEntries();
    $this->post(route('upload.confirm', '2026-06'));

    expect(Statement::count())->toBe(2);
});

test('discards the pending import', function () {
    uploadJuneStatement();

    $this->post(route('upload.discard'))
        ->assertRedirect(route('upload'))
        ->assertSessionMissing('statement_import');

    expect(Statement::count())->toBe(0);
});

test('discards the pending import when leaving the review', function (string $name) {
    uploadJuneStatement();

    $this->get(route($name))->assertSessionMissing('statement_import');
    $this->get(route('upload.review', '2026-06'))->assertRedirect(route('upload'));

    expect(Statement::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
})->with(['overview', 'trends', 'upload']);

test('marks upload as active on the review page', function () {
    uploadJuneStatement();

    $html = $this->get(route('upload.review', '2026-06'))->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(preg_match('/<a[^>]*href="'.preg_quote(route('upload'), '/').'"[^>]*aria-current="page"/', $html))->toBe(1);
});

function mieteOutRuleId(): string
{
    return Rule::query()->where('conditions.0.value', 'Miete')->where('direction', 'out')->first()->id;
}

test('groups entries on the review page', function () {
    $this->seed(RuleSeeder::class);
    uploadJuneStatement();

    $response = $this->get(route('upload.review', '2026-06'))
        ->assertOk()
        ->assertSee('Groceries & Personal Care')
        ->assertSee('ignored');

    $html = $response->getContent();

    expect(substr_count($html, 'data-assignment="group"'))->toBe(64)
        ->and(substr_count($html, 'data-assignment="ignored"'))->toBe(3)
        ->and(substr_count($html, 'data-assignment="unassigned"'))->toBe(0)
        ->and(substr_count($html, 'data-assignment="income"'))->toBe(1)
        ->and(substr_count($html, 'data-group="rent"'))->toBe(1)
        ->and(substr_count($html, 'data-share'))->toBe(3)
        ->and(substr_count($html, ' / 3)'))->toBe(3)
        ->and(substr_count($html, 'No group'))->toBe(0)
        ->and(substr_count($html, 'data-review-entry='))->toBe(1)
        ->and(substr_count($html, 'data-row-toggle'))->toBe(69);
});

test('keeps the rule results in the pending import', function () {
    $this->seed(RuleSeeder::class);
    uploadJuneStatement();

    expect(session('statement_import.months.2026-06.assignments'))->toHaveCount(69)
        ->and(session('statement_import.months.2026-06.assignments')[1])->toBe([
            'group_key' => 'rent',
            'share_divisor' => 3,
            'ignored' => false,
            'rule_id' => mieteOutRuleId(),
        ]);
});

test('stores the rule results on confirm', function () {
    $this->seed(RuleSeeder::class);
    uploadJuneStatement();
    assignOpenEntries();

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rent = Transaction::where('amount_cents', -115820)->sole();

    expect($rent->group_key)->toBe('rent')
        ->and($rent->share_divisor)->toBe(3)
        ->and($rent->ignored)->toBeFalse()
        ->and($rent->rule_id)->toBe(mieteOutRuleId())
        ->and($rent->rule->conditions[0]['value'])->toBe('Miete');

    $ignored = Transaction::where('ignored', true)->get();

    expect($ignored)->toHaveCount(3)
        ->and($ignored->every(fn (Transaction $transaction) => $transaction->group_key === null))->toBeTrue();

    $transfer = Transaction::where('amount_cents', -20000)->sole();

    expect($transfer->group_key)->toBe('other')
        ->and($transfer->rule_id)->toBeNull()
        ->and($transfer->ignored)->toBeFalse();

    $salary = Transaction::where('amount_cents', 134819)->sole();

    expect($salary->group_key)->toBeNull()
        ->and($salary->ignored)->toBeFalse()
        ->and(Transaction::whereNotNull('group_key')->count())->toBe(65);

    $ruleIds = Transaction::whereNotNull('rule_id')->pluck('rule_id')->unique();

    expect(Rule::query()->whereIn('_id', $ruleIds->all())->count())->toBe($ruleIds->count());
});

test('stores what was matched at upload time', function () {
    $this->seed(RuleSeeder::class);
    uploadJuneStatement();
    assignOpenEntries();

    Rule::query()->delete();

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    $rent = Transaction::where('amount_cents', -115820)->sole();

    expect($rent->group_key)->toBe('rent')
        ->and($rent->share_divisor)->toBe(3);
});

test('imports without rules', function () {
    uploadJuneStatement();

    $html = $this->get(route('upload.review', '2026-06'))->assertOk()->getContent();

    expect(substr_count($html, 'data-review-entry='))->toBe(65)
        ->and(substr_count($html, 'data-assignment="income"'))->toBe(4)
        ->and(strip_tags($html))->toContain('65 to review');

    assignOpenEntries();

    $this->post(route('upload.confirm', '2026-06'))->assertRedirect(route('upload'));

    foreach (Transaction::all() as $transaction) {
        expect($transaction->group_key)->toBe($transaction->direction === 'out' ? 'other' : null)
            ->and($transaction->ignored)->toBeFalse()
            ->and($transaction->rule_id)->toBeNull();
    }
});
