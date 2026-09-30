<?php

use App\Http\Middleware\DiscardPendingStatementImport;
use App\Models\Rule;
use App\Models\User;
use App\Services\Groups\GroupCatalog;
use Database\Seeders\RuleSeeder;

test('redirects guests', function () {
    $this->get(route('groups'))->assertRedirect(route('login'));
    $this->putJson(route('groups.order'), ['groups' => ['rent']])->assertUnauthorized();
});

test('shows the user menu with the email', function () {
    $user = User::factory()->create(['email' => 'menu@example.com']);

    $this->actingAs($user)->get(route('overview'))
        ->assertOk()
        ->assertSee('data-user-menu', false)
        ->assertSee('menu@example.com')
        ->assertSee(route('groups'))
        ->assertSee('Groups &amp; rules', false)
        ->assertSee(route('logout'))
        ->assertSee('Log out');
});

test('lists groups, rules and always rules in order', function () {
    $this->seed(RuleSeeder::class);
    $manual = Rule::factory()->manual()->withCondition('merchant', 'contains', 'Kiosk Nord')->create(['group_key' => 'takeaway', 'position' => null]);

    $response = $this->actingAs(User::factory()->create())->get(route('groups'))->assertOk();
    $state = $response->viewData('state');

    expect(array_column($state['cards'], 'key'))->toBe(app(GroupCatalog::class)->keys());

    $rent = $state['cards'][0];
    expect($rent['rules'])->toHaveCount(1)
        ->and($rent['rules'][0])->toMatchArray([
            'direction' => 'out',
            'share' => 3,
            'conditions' => [['field' => 'purpose', 'operator' => 'contains', 'value' => 'Miete']],
        ]);

    expect($state['ignored']['rules'])->toHaveCount(2)
        ->and(array_column($state['ignored']['rules'], 'direction'))->toBe(['in', 'in'])
        ->and($state['ignored']['rules'][0])->not->toHaveKey('share');

    $takeaway = collect($state['cards'])->firstWhere('key', 'takeaway');
    expect($takeaway['always'])->toBe([['id' => (string) $manual->id, 'text' => 'merchant contains "Kiosk Nord"']])
        ->and(collect($takeaway['rules'])->pluck('id'))->not->toContain((string) $manual->id);

    $response->assertSee('data-block="amount"', false)->assertSee('Rerun all rules');
});

test('discards a pending upload', function () {
    $this->actingAs(User::factory()->create());
    $this->seed(RuleSeeder::class);
    $this->post(route('upload.store'), ['statements' => [statementUpload()]])->assertRedirect(route('upload.review', '2026-06'));
    expect(session()->has(DiscardPendingStatementImport::SESSION_KEY))->toBeTrue();

    $this->get(route('groups'))->assertOk();

    expect(session()->has(DiscardPendingStatementImport::SESSION_KEY))->toBeFalse();
});
