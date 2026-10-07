<?php

use App\Models\Rule;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Statements\ImportBatch;
use Database\Seeders\RuleSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The pending batch as stored in the session. */
function pendingBatch(): ImportBatch
{
    return ImportBatch::fromArray(session('statement_import'));
}

/** Uploads April, May and June with seeded rules, so entry 65 is the only open entry per month. */
function uploadSpring(): void
{
    test()->seed(RuleSeeder::class);
    uploadMonths(['2026-04', '2026-05', '2026-06'])->assertRedirect('/upload/review/2026-04');
}

function confirmApril(): void
{
    assignOpenEntries('other', '2026-04');
    test()->post(route('upload.confirm', '2026-04'))->assertRedirect('/upload/review/2026-05');
}

test('uploads several statements as a batch in period order', function () {
    uploadMonths(['2026-06', '2026-04', '2026-05'])->assertRedirect('/upload/review/2026-04');

    expect(array_keys(session('statement_import.months')))->toBe(['2026-04', '2026-05', '2026-06'])
        ->and(Statement::count())->toBe(0);
});

test('keeps a single upload unchanged', function () {
    $this->post(route('upload.store'), ['statements' => [statementUpload()]])
        ->assertRedirect('/upload/review/2026-06');

    $this->get(route('upload.review', '2026-06'))
        ->assertOk()
        ->assertDontSee('data-stepper', false)
        ->assertDontSee('Skip')
        ->assertSee('Discard');

    assignOpenEntries();

    $this->post(route('upload.confirm', '2026-06'))
        ->assertRedirect(route('upload'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'June 2026 imported · 69 entries']);
});

test('rejects more than 12 files', function () {
    $this->post(route('upload.store'), ['statements' => array_map(fn () => statementUpload(), range(1, 13))])
        ->assertSessionHasErrors(['statements' => 'You can upload up to 12 statements at once.'])
        ->assertSessionMissing('statement_import');
});

test('skips and reports bad files', function () {
    $this->post(route('upload.store'), ['statements' => [
        monthUpload('2026-06'),
        UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'),
        UploadedFile::fake()->createWithContent('invoice.pdf', blankPdf()),
    ]])->assertRedirect('/upload/review/2026-06');

    expect(pendingBatch()->periods())->toBe(['2026-06']);

    $this->get(route('upload.review', '2026-06'))
        ->assertSee('data-failed-notice', false)
        ->assertSee("Couldn't import 3 files", false)
        ->assertSeeInOrder([
            'notes.txt – not a PDF',
            'big.pdf – larger than 10 MB',
            "invoice.pdf – doesn't look like an ING statement",
        ]);
});

test('shows every error when no file is valid', function () {
    $files = fn () => ['statements' => [
        UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        UploadedFile::fake()->createWithContent('invoice.pdf', blankPdf()),
    ]];

    $this->post(route('upload.store'), $files())
        ->assertRedirect(route('upload'))
        ->assertSessionHasErrors([
            'statements' => 'None of the files could be imported.',
            'files' => 'notes.txt – not a PDF',
        ])
        ->assertSessionHasErrors(['files' => "invoice.pdf – doesn't look like an ING statement"])
        ->assertSessionMissing('statement_import');

    $this->followingRedirects()
        ->post(route('upload.store'), $files())
        ->assertSee('data-failed-files', false)
        ->assertSee('None of the files could be imported.')
        ->assertSee('notes.txt – not a PDF');
});

test('reports duplicates inside the batch', function () {
    $this->post(route('upload.store'), ['statements' => [
        monthUpload('2026-06', 'a.pdf'),
        monthUpload('2026-06', 'b.pdf'),
        monthUpload('2026-05'),
    ]])->assertRedirect('/upload/review/2026-05');

    expect(pendingBatch()->periods())->toBe(['2026-05', '2026-06'])
        ->and(pendingBatch()->failed())->toBe(['b.pdf – duplicate of June 2026']);

    $this->get(route('upload.review', '2026-05'))->assertSee('b.pdf – duplicate of June 2026');
});

test('dismisses the notice for the rest of the batch', function () {
    $this->post(route('upload.store'), ['statements' => [
        monthUpload('2026-04'),
        monthUpload('2026-05'),
        UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ]]);

    $this->get(route('upload.review', '2026-04'))->assertSee('data-failed-notice', false);

    $this->postJson(route('upload.notice.dismiss'))->assertNoContent();

    $this->get(route('upload.review', '2026-04'))->assertDontSee('data-failed-notice', false);
    $this->get(route('upload.review', '2026-05'))->assertDontSee('data-failed-notice', false);
});

test('shows the stepper for a batch', function () {
    uploadSpring();

    $html = $this->get(route('upload.review', '2026-04'))
        ->assertOk()
        ->assertSee('data-stepper', false)
        ->assertSeeInOrder(['Apr 2026', 'May 2026', 'Jun 2026'])
        ->assertSee('Skip')
        ->assertSee('Discard remaining')
        ->getContent();

    expect($html)->toMatch('/data-step="2026-04" data-step-state="open">\s*<span aria-current="step"/')
        ->and($html)->toContain('data-step="2026-05" data-step-state="open"')
        ->and($html)->toContain('data-step="2026-06" data-step-state="open"')
        ->and($html)->toContain('href="'.route('upload.review', '2026-05').'"')
        ->and($html)->toContain('href="'.route('upload.review', '2026-06').'"')
        ->and($html)->not->toContain('href="'.route('upload.review', '2026-04').'"');
});

test('confirms only the current month and moves to the next', function () {
    uploadSpring();
    confirmApril();

    expect(Statement::count())->toBe(1)
        ->and(Statement::first()->period)->toBe('2026-04')
        ->and(Transaction::count())->toBe(69)
        ->and(Transaction::where('period', '2026-04')->count())->toBe(69)
        ->and(pendingBatch()->isPending('2026-05'))->toBeTrue()
        ->and(pendingBatch()->isPending('2026-06'))->toBeTrue();

    $this->get(route('upload.review', '2026-05'))
        ->assertOk()
        ->assertSee('data-step="2026-04" data-step-state="confirmed"', false);
});

test('skips a month', function () {
    uploadSpring();

    $this->post(route('upload.skip', '2026-04'))->assertRedirect('/upload/review/2026-05');
    $this->post(route('upload.skip', '2026-05'))->assertRedirect('/upload/review/2026-06');

    expect(Statement::where('period', '2026-05')->count())->toBe(0);

    $this->get(route('upload.review', '2026-06'))
        ->assertSee('data-step="2026-05" data-step-state="skipped"', false)
        ->assertDontSee('href="'.route('upload.review', '2026-05').'"', false);
});

test('discards the remaining months and keeps confirmed ones', function () {
    uploadSpring();
    confirmApril();

    $this->post(route('upload.discard'))
        ->assertRedirect(route('upload'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => '1 month imported · 69 entries (2 discarded)'])
        ->assertSessionMissing('statement_import');

    expect(Statement::pluck('period')->all())->toBe(['2026-04']);
});

test('shows the replace warning inside a batch', function () {
    importedMonth('2026-05');
    uploadMonths(['2026-04', '2026-05']);

    $this->get(route('upload.review', '2026-05'))
        ->assertSee('May 2026 (statement 5) is already imported. Confirming replaces it.')
        ->assertSee('Skip this month')
        ->assertSee('Replace import')
        ->assertSee('action="'.route('upload.skip', '2026-05').'"', false)
        ->assertDontSee('Cancel');
});

test('applies a new always rule to the remaining months but not to manual picks', function () {
    uploadSpring();

    $this->postJson(route('upload.assign', '2026-05'), ['entry' => 65, 'group' => 'health'])->assertOk();
    $this->postJson(route('upload.assign', '2026-04'), ['entry' => 65, 'group' => 'other'])->assertOk();
    $this->postJson(route('upload.always', '2026-04'), ['entry' => 65, 'always' => true])->assertOk();
    $this->post(route('upload.confirm', '2026-04'))->assertRedirect('/upload/review/2026-05');

    $rule = Rule::query()->where('source', 'manual')->sole();

    $this->get(route('upload.review', '2026-05'))
        ->assertOk()
        ->assertSee('data-review-entry="65"', false);

    expect(pendingBatch()->reviewQueue('2026-05')->state()['entries'][65]['group'])->toBe('health')
        ->and(session('statement_import.months.2026-05.assignments.65.rule_id'))->toBeNull()
        ->and(session('statement_import.months.2026-06.assignments.65.rule_id'))->toBe($rule->id)
        ->and(pendingBatch()->reviewQueue('2026-06')->openCount())->toBe(0);
});

test('discards unconfirmed months when leaving the flow', function (string $name) {
    uploadSpring();
    confirmApril();

    $this->get(route($name))->assertSessionMissing('statement_import');
    $this->get(route('upload.review', '2026-05'))->assertRedirect(route('upload'));

    expect(Statement::pluck('period')->all())->toBe(['2026-04']);
})->with(['overview', 'trends', 'upload']);

test('redirects unknown or finished months to the current one', function () {
    uploadSpring();
    confirmApril();

    $this->get('/upload/review/2026-01')->assertRedirect('/upload/review/2026-05');
    $this->get('/upload/review/2026-04')->assertRedirect('/upload/review/2026-05');
    $this->get('/upload/review')->assertRedirect('/upload/review/2026-05');

    $this->postJson(route('upload.assign', '2026-04'), ['entry' => 65, 'group' => 'other'])
        ->assertConflict()
        ->assertJson(['redirect' => route('upload.review')]);
});

test('lands on upload with a summary after the last month', function () {
    $this->seed(RuleSeeder::class);
    uploadMonths(['2026-06', '2026-04', '2026-05'])->assertRedirect('/upload/review/2026-04');

    confirmApril();

    $this->post(route('upload.skip', '2026-05'))->assertRedirect('/upload/review/2026-06');

    assignOpenEntries('other', '2026-06');

    $this->post(route('upload.confirm', '2026-06'))
        ->assertRedirect(route('upload'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => '2 months imported · 138 entries (1 skipped)'])
        ->assertSessionMissing('statement_import');

    $this->get(route('upload'))
        ->assertSee('2 months imported · 138 entries (1 skipped)')
        ->assertSeeInOrder(['Jun 2026', 'Apr 2026'])
        ->assertDontSee('May 2026');
});

test('reports nothing imported when every month is skipped', function () {
    uploadMonths(['2026-04', '2026-05']);

    $this->post(route('upload.skip', '2026-04'))->assertRedirect('/upload/review/2026-05');
    $this->post(route('upload.skip', '2026-05'))
        ->assertRedirect(route('upload'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'Nothing imported (2 skipped)']);

    expect(Statement::count())->toBe(0);
});

test('redirects an upload over the post size limit', function () {
    $this->call('POST', route('upload.store'), [], [], [], ['CONTENT_LENGTH' => 101 * 1024 * 1024])
        ->assertRedirect(route('upload', ['too_large' => 1]));

    $this->get(route('upload', ['too_large' => 1]))->assertSee('Upload too large, try fewer files.');
    $this->get(route('upload'))->assertDontSee('Upload too large, try fewer files.');
});

test('passes the upload limits to the drop zone', function () {
    $this->get(route('upload'))
        ->assertSee('uploadDropzone(', false)
        ->assertSee('maxFiles', false)
        ->assertSee('104857600', false)
        ->assertSee('Drop your ING statements here')
        ->assertSee('or click to choose PDFs (up to 12)');
});
