<?php

use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The fixture's AMAZON entry of −48,98 €. */
function amazonEntry(): Transaction
{
    return Transaction::query()->where('merchant', 'AMAZON')->where('amount_cents', -4898)->sole();
}

function saveDescription(Transaction $transaction, ?string $description): TestResponse
{
    return test()->putJson(route('entries.description.update', $transaction->id), ['description' => $description]);
}

/** Decoded markup of one Overview entry row. */
function describedRow(string $html, Transaction $transaction): string
{
    preg_match('/data-entry="'.$transaction->id.'".*?<\/li>/s', $html, $match);

    return html_entity_decode($match[0] ?? '');
}

test('saves a description and shows it on the overview', function () {
    importFixtureStatement();
    $amazon = amazonEntry();

    saveDescription($amazon, "  Birthday gift for Anna\n")->assertOk()->assertExactJson(['description' => 'Birthday gift for Anna']);

    $row = describedRow($this->get(route('overview', ['month' => '2026-06', 'q' => 'amazon']))->getContent(), $amazon);

    expect($amazon->fresh()->description)->toBe('Birthday gift for Anna')
        ->and($row)->toMatch('/AMAZON<\/span>\s*<span data-row-description[^>]*title="Birthday gift for Anna"[^>]*>Birthday gift for Anna</')
        ->toMatch('/<textarea[^>]*data-description-field[^>]*>Birthday gift for Anna<\/textarea>/');
});

test('keeps line breaks', function () {
    importFixtureStatement();

    saveDescription(amazonEntry(), "Gift\nfor Anna")->assertOk();

    expect(amazonEntry()->description)->toBe("Gift\nfor Anna");
});

test('removes the description when saved empty', function (?string $empty) {
    importFixtureStatement();
    $amazon = amazonEntry();
    saveDescription($amazon, 'Birthday gift for Anna');

    saveDescription($amazon, $empty)->assertOk()->assertExactJson(['description' => null]);

    expect($amazon->fresh()->description)->toBeNull()
        ->and(describedRow($this->get(route('overview', ['month' => '2026-06', 'q' => 'amazon']))->getContent(), $amazon))
        ->toContain('style="display: none"');
})->with(['empty' => '', 'whitespace' => " \n  ", 'null' => null]);

test('rejects descriptions longer than 500 characters', function () {
    importFixtureStatement();

    saveDescription(amazonEntry(), str_repeat('ä', 500))->assertOk();
    saveDescription(amazonEntry(), str_repeat('a', 501))->assertUnprocessable()->assertJsonValidationErrors('description');

    expect(amazonEntry()->description)->toBe(str_repeat('ä', 500));
});

test('searches descriptions', function () {
    importFixtureStatement();
    $amazon = amazonEntry();
    saveDescription($amazon, 'Birthday gift for Anna');

    $found = $this->get(route('overview', ['month' => '2026-06', 'q' => 'birthday gift']))->assertSee('1–1 of 1')->getContent();
    expect($found)->toContain('data-entry="'.$amazon->id.'"');

    saveDescription($amazon, '');

    $this->get(route('overview', ['month' => '2026-06', 'q' => 'birthday gift']))->assertSee('No entries match these filters');
});

test('refuses guests', function () {
    importFixtureStatement();
    $amazon = amazonEntry();
    auth()->logout();

    saveDescription($amazon, 'Nope')->assertUnauthorized();

    expect($amazon->fresh()->description)->toBeNull();
});
