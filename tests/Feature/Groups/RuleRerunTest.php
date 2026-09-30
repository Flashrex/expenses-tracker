<?php

use App\Http\Controllers\RuleRerunController;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    importFixtureStatement();
});

/** Creates "Streaming" above Subscriptions & Internet with the two Done-when rules and returns its key. */
function rerunStreaming(): string
{
    return test()->postJson(route('groups.store'), [
        'name' => 'Streaming',
        'color' => '#d946ef',
        'before' => 'subscriptions',
        'rules' => [
            ['id' => null, 'direction' => 'out', 'share' => '1', 'conditions' => [['field' => 'merchant', 'operator' => 'contains', 'value' => 'Spotify']]],
            ['id' => null, 'direction' => 'out', 'share' => '1', 'conditions' => [
                ['field' => 'merchant', 'operator' => 'contains', 'value' => 'Discovery'],
                ['field' => 'amount', 'operator' => 'less_than', 'value' => '20'],
            ]],
        ],
    ])->assertCreated()->json('card.key');
}

function rerunTransaction(string $merchant): Transaction
{
    return Transaction::query()->where('merchant', $merchant)->sole();
}

function rerunDecide(Transaction $transaction, string $decision): void
{
    test()->postJson(route('groups.rerun.decide'), ['entries' => [(string) $transaction->id], 'decision' => $decision])->assertOk();
}

/** The markup of the preview row for $transaction. */
function rerunRow(string $html, Transaction $transaction): string
{
    preg_match('/data-rerun-row="'.$transaction->id.'".*?<\/li>/s', $html, $m);

    return html_entity_decode($m[0] ?? '');
}

/** Adds a rule to a group card and saves it, keeping the card's other rules. */
function rerunAddRule(string $group, array $conditions): void
{
    $card = collect(test()->get(route('groups'))->viewData('state')['cards'])->firstWhere('key', $group);

    test()->putJson(route('groups.update', $group), [
        'name' => $card['name'],
        'color' => $card['color'],
        'rules' => [
            ['id' => null, 'direction' => 'out', 'share' => '1', 'conditions' => $conditions],
            ...$card['rules'],
        ],
    ])->assertOk();
}

test('reports when everything matches', function () {
    $this->post(route('groups.rerun.store'))
        ->assertRedirect(route('groups'))
        ->assertSessionHas('status', 'All entries already match your rules.');

    $this->get(route('groups'))->assertSee('All entries already match your rules.');

    expect(session()->has(RuleRerunController::SESSION_KEY))->toBeFalse();
});

test('proposes Spotify and Discovery for Streaming', function () {
    $streaming = rerunStreaming();
    $spotify = rerunTransaction('Spotify');
    $discovery = rerunTransaction('Discovery Communication s Benelux');

    $this->post(route('groups.rerun.store'))->assertRedirect(route('groups.rerun'));

    $proposals = session(RuleRerunController::SESSION_KEY)['proposals'];

    expect(array_keys($proposals))->toEqualCanonicalizing([(string) $spotify->id, (string) $discovery->id])
        ->and($proposals[(string) $spotify->id])->toMatchArray(['group_key' => $streaming, 'share_divisor' => 1, 'ignored' => false])
        ->and($spotify->fresh()->group_key)->toBe('subscriptions');

    $html = html_entity_decode($this->get(route('groups.rerun'))->assertOk()->getContent());

    expect(substr_count($html, 'data-rerun-month='))->toBe(1)
        ->and($html)->toContain('data-rerun-month="2026-06"')
        ->toContain('June 2026')
        ->toContain('2 undecided')
        ->and(strpos($html, 'data-rerun-row="'.$discovery->id.'"'))->toBeLessThan(strpos($html, 'data-rerun-row="'.$spotify->id.'"'));

    $row = rerunRow($html, $spotify);

    expect($row)->toContain('−12,99 €')
        ->toMatch('/data-current>.*Subscriptions & Internet.*<\/span>\s*<svg/s')
        ->toMatch('/data-proposed>.*Streaming/s')
        ->toContain('Rule · merchant contains "Spotify"')
        ->toContain('aria-pressed="false"')
        ->and(rerunRow($html, $discovery))->toContain('−11,99 €')
        ->toContain('Rule · merchant contains "Discovery" AND amount is less than 20,00 €');
});

test('applies accepted and declined decisions', function () {
    $streaming = rerunStreaming();
    $spotify = rerunTransaction('Spotify');
    $discovery = rerunTransaction('Discovery Communication s Benelux');
    $spotifyRule = Rule::query()->where('group_key', $streaming)->where('position', 1)->sole();

    $this->post(route('groups.rerun.store'));
    rerunDecide($spotify, 'accept');
    $this->postJson(route('groups.rerun.decide'), ['entries' => [(string) $discovery->id], 'decision' => 'decline'])
        ->assertOk()
        ->assertJson(['undecided' => 0, 'decisions' => [(string) $spotify->id => 'accept', (string) $discovery->id => 'decline']]);

    $this->post(route('groups.rerun.apply'))
        ->assertRedirect(route('groups'))
        ->assertSessionHas('status', 'Updated 1 entry.');

    $spotify->refresh();
    $discovery->refresh();

    expect($spotify->group_key)->toBe($streaming)
        ->and($spotify->rule_id)->toBe((string) $spotifyRule->id)
        ->and($discovery->group_key)->toBe('subscriptions')
        ->and($discovery->rule_id)->toBeNull()
        ->and($discovery->declined)->toBe(['group_key' => $streaming, 'share_divisor' => 1, 'ignored' => false])
        ->and(session()->has(RuleRerunController::SESSION_KEY))->toBeFalse();

    $streamingPage = html_entity_decode($this->get(route('overview', ['month' => '2026-06', 'group' => $streaming]))->assertOk()->getContent());
    $subscriptionsPage = html_entity_decode($this->get(route('overview', ['month' => '2026-06', 'group' => 'subscriptions', 'q' => 'Discovery']))->assertOk()->getContent());

    expect($streamingPage)->toContain('Spotify')
        ->toMatch('/data-grouped-by[^>]*>\s*Rule · merchant contains "Spotify"\s*</')
        ->and($subscriptionsPage)->toContain('Discovery Communication s Benelux')
        ->toMatch('/data-grouped-by[^>]*>\s*Picked manually\s*</');
});

test('does not propose a declined entry again', function () {
    $streaming = rerunStreaming();
    $spotify = rerunTransaction('Spotify');
    $discovery = rerunTransaction('Discovery Communication s Benelux');

    $this->post(route('groups.rerun.store'));
    rerunDecide($spotify, 'accept');
    rerunDecide($discovery, 'decline');
    $this->post(route('groups.rerun.apply'));

    $this->post(route('groups.rerun.store'))
        ->assertRedirect(route('groups'))
        ->assertSessionHas('status', 'All entries already match your rules.');

    $card = collect($this->get(route('groups'))->viewData('state')['cards'])->firstWhere('key', $streaming);
    $card['rules'][1]['share'] = 2;
    $this->putJson(route('groups.update', $streaming), $card)->assertOk();

    $this->post(route('groups.rerun.store'))->assertRedirect(route('groups.rerun'));

    expect(array_keys(session(RuleRerunController::SESSION_KEY)['proposals']))->toBe([(string) $discovery->id]);
});

test('keeps decisions across a reload', function () {
    rerunStreaming();
    $spotify = rerunTransaction('Spotify');

    $this->post(route('groups.rerun.store'));
    rerunDecide($spotify, 'accept');

    $html = $this->get(route('groups.rerun'))->assertOk()->getContent();

    expect(rerunRow($html, $spotify))->toMatch('/data-accept[^>]*aria-pressed="true"/')
        ->and($html)->toContain('1 undecided');
});

test('refuses to apply undecided changes', function () {
    $streaming = rerunStreaming();
    $spotify = rerunTransaction('Spotify');

    $this->post(route('groups.rerun.store'));
    rerunDecide($spotify, 'accept');

    $this->post(route('groups.rerun.apply'))
        ->assertRedirect(route('groups.rerun'))
        ->assertSessionHas('rerun_error', 'Decide every change first.');

    expect($spotify->fresh()->group_key)->toBe('subscriptions')
        ->and(Transaction::query()->where('group_key', $streaming)->count())->toBe(0);
});

test('cancel changes nothing', function () {
    rerunStreaming();
    $before = Transaction::query()->get()->map->result()->all();

    $this->post(route('groups.rerun.store'));
    $this->post(route('groups.rerun.cancel'))->assertRedirect(route('groups'));

    expect(session()->has(RuleRerunController::SESSION_KEY))->toBeFalse()
        ->and(Transaction::query()->get()->map->result()->all())->toBe($before);
});

test('does not propose entries whose result comes from another rule', function () {
    $tegut = Transaction::query()->where('merchant', 'TEGUT')->first();
    $ruleId = $tegut->rule_id;

    rerunAddRule('groceries', [['field' => 'merchant', 'operator' => 'contains', 'value' => 'TEG']]);

    $this->post(route('groups.rerun.store'))
        ->assertRedirect(route('groups'))
        ->assertSessionHas('status', 'All entries already match your rules.');

    expect($tegut->fresh()->rule_id)->toBe($ruleId);
});

test('includes manual picks', function () {
    $transfer = Transaction::query()->where('amount_cents', -20000)->sole();
    expect($transfer->group_key)->toBe('other')->and($transfer->rule_id)->toBeNull();

    rerunAddRule('health', [['field' => 'type', 'operator' => 'contains', 'value' => 'Echtzeit']]);

    $this->post(route('groups.rerun.store'))->assertRedirect(route('groups.rerun'));

    expect(session(RuleRerunController::SESSION_KEY)['proposals'])->toHaveKey((string) $transfer->id)
        ->and(session(RuleRerunController::SESSION_KEY)['proposals'][(string) $transfer->id]['group_key'])->toBe('health');
});

test('proposes ignoring', function () {
    $spotify = rerunTransaction('Spotify');
    $ignored = $this->get(route('groups'))->viewData('state')['ignored']['rules'];

    $this->putJson(route('groups.ignored.update'), ['rules' => [
        ['id' => null, 'direction' => 'out', 'conditions' => [['field' => 'merchant', 'operator' => 'contains', 'value' => 'Spotify']]],
        ...$ignored,
    ]])->assertOk();

    // Group rules win over ignore rules, so the subscriptions rule for Spotify has to go first.
    $card = collect($this->get(route('groups'))->viewData('state')['cards'])->firstWhere('key', 'subscriptions');
    $card['rules'] = array_values(array_filter($card['rules'], fn (array $rule) => $rule['conditions'][0]['value'] !== 'Spotify'));
    $this->putJson(route('groups.update', 'subscriptions'), $card)->assertOk();

    $this->post(route('groups.rerun.store'))->assertRedirect(route('groups.rerun'));

    expect(session(RuleRerunController::SESSION_KEY)['proposals'][(string) $spotify->id])
        ->toMatchArray(['group_key' => null, 'share_divisor' => 1, 'ignored' => true]);

    $row = rerunRow($this->get(route('groups.rerun'))->getContent(), $spotify);

    expect($row)->toMatch('/data-proposed>.*ignored/s');
});

test('redirects without a pending rerun', function () {
    $this->get(route('groups.rerun'))->assertRedirect(route('groups'));
    $this->postJson(route('groups.rerun.decide'), ['entries' => ['x'], 'decision' => 'accept'])
        ->assertConflict()
        ->assertJson(['redirect' => route('groups')]);
});
