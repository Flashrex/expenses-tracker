<?php

use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'me@example.com']);
});

function uploadJuneStatement(): void
{
    test()->actingAs(test()->user)
        ->post(route('upload.store'), ['statement' => statementUpload()])
        ->assertRedirect(route('upload.review'));
}

test('redirects guests from the upload routes', function () {
    $this->get('/upload/review')->assertRedirect(route('login'));
    $this->post('/upload')->assertRedirect(route('login'));
    $this->post('/upload/confirm')->assertRedirect(route('login'));
    $this->post('/upload/discard')->assertRedirect(route('login'));
});

test('shows the drop zone', function () {
    $this->actingAs($this->user)
        ->get(route('upload'))
        ->assertOk()
        ->assertSee('Drop your ING statement here')
        ->assertSee('name="statement"', false)
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
        ->post(route('upload.store'), ['statement' => UploadedFile::fake()->create('a.txt', 10, 'text/plain')])
        ->assertSessionHasErrors(['statement' => 'Please choose a PDF file (max. 10 MB).'])
        ->assertSessionMissing('statement_import');
});

test('rejects a missing file', function () {
    $this->actingAs($this->user)
        ->post(route('upload.store'), [])
        ->assertSessionHasErrors(['statement' => 'Please choose a PDF file (max. 10 MB).']);
});

test('rejects a pdf larger than 10 MB', function () {
    $this->actingAs($this->user)
        ->post(route('upload.store'), ['statement' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])
        ->assertSessionHasErrors(['statement' => 'Please choose a PDF file (max. 10 MB).']);
});

test('rejects a pdf that is not an ING statement', function () {
    $this->actingAs($this->user)
        ->from(route('upload'))
        ->post(route('upload.store'), ['statement' => UploadedFile::fake()->createWithContent('x.pdf', blankPdf())])
        ->assertRedirect(route('upload'))
        ->assertSessionHasErrors(['statement' => "This doesn't look like an ING statement."]);

    expect(Statement::count())->toBe(0);
});

test('parses the upload and shows the review', function () {
    Storage::fake('local');

    uploadJuneStatement();

    expect(session('statement_import.entries'))->toHaveCount(69)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Statement::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);

    $response = $this->get(route('upload.review'))
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

    expect(substr_count($response->getContent(), '<tr'))->toBe(70);
});

test('keeps the pending import when the review is reloaded', function () {
    uploadJuneStatement();

    $this->get(route('upload.review'))->assertOk();
    $this->get(route('upload.review'))->assertOk();
});

test('redirects to upload when nothing is pending', function () {
    $this->actingAs($this->user);

    $this->get(route('upload.review'))->assertRedirect(route('upload'));
    $this->post(route('upload.confirm'))->assertRedirect(route('upload'));

    expect(Statement::count())->toBe(0);
});

test('stores the statement and entries on confirm', function () {
    uploadJuneStatement();

    $this->post(route('upload.confirm'))
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
            ->and($transaction->group_key)->toBeNull()
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

    $this->get(route('upload.review'))
        ->assertSee('June 2026 (statement 6) is already imported. Confirming replaces it.')
        ->assertSee('Replace import')
        ->assertSee('Cancel')
        ->assertDontSee('Confirm import');
});

test('replaces the existing statement on confirm', function () {
    $old = Statement::factory()->create();
    Transaction::factory()->count(2)->create(['statement_id' => $old->id]);

    uploadJuneStatement();

    $this->post(route('upload.confirm'))->assertRedirect(route('upload'));

    expect(Statement::count())->toBe(1)
        ->and((string) Statement::first()->id)->not->toBe((string) $old->id)
        ->and(Transaction::count())->toBe(69)
        ->and(Transaction::where('statement_id', $old->id)->count())->toBe(0);
});

test('does not treat another month with the same number as duplicate', function () {
    Statement::factory()->create(['period' => '2025-06']);

    uploadJuneStatement();

    $this->get(route('upload.review'))->assertDontSee('already imported');
    $this->post(route('upload.confirm'));

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
    $this->get(route('upload.review'))->assertRedirect(route('upload'));

    expect(Statement::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
})->with(['overview', 'trends', 'upload']);

test('marks upload as active on the review page', function () {
    uploadJuneStatement();

    $html = $this->get(route('upload.review'))->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(preg_match('/<a[^>]*href="'.preg_quote(route('upload'), '/').'"[^>]*aria-current="page"/', $html))->toBe(1);
});
