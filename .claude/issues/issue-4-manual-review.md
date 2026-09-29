# Issue 4: Manual review of unassigned entries

> Source: `.claude/project-plan.md` › "Issue 4 – Manual review of unassigned entries"
> Planned: 2026-09-29
> Depends on: Issue 1 (`.claude/issues/issue-1-foundation.md`: layouts, primary button style, `config/expenses.php` groups, Alpine in `resources/js/app.js`, heroicons, `DatabaseMigrations`), Issue 2 (`.claude/issues/issue-2-statement-upload-parsing.md`: `StatementUploadController`, `DiscardPendingStatementImport`, review page, `ParsedEntry`/`ParsedStatement`, `Money`/`Period`, test helpers, `[x-cloak]` CSS), Issue 3 (`.claude/issues/issue-3-automatic-grouping.md`: `Rule` model + factory, enums, `RuleSeeder`, `TextNormalizer`, `RuleMatch`, `RuleMatcher`, `<x-group-chip>`, `assignments` in the pending import, `fixtureStatement()`/`entry()` test helpers).

## Goal

Every outgoing entry that no rule matched gets a group before the import can be confirmed. Those entries appear first on the review page in a "To review" queue with a one-click group picker and an optional "Always use this group for ‹merchant›" checkbox, which applies at once to other unassigned entries of that merchant in the same upload and is saved as a `manual` rule on confirm.

Original "Done when": **you can assign the −200 transfer, tick "always", confirm, and a later upload matches it automatically.**

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run every PHP, Artisan, Composer, Node and test command through Sail: `vendor/bin/sail …`. Host PHP lacks `ext-mongodb`, so host `php artisan` fails. If containers are down, run `vendor/bin/sail up -d` first.
- Issues 1–3 must be fully implemented first. Check: `app/Models/Rule.php`, `app/Services/Rules/RuleMatcher.php`, `app/Services/Rules/RuleMatch.php`, `app/Services/Rules/TextNormalizer.php`, `resources/views/components/group-chip.blade.php` exist, `session('statement_import.assignments')` is written in `StatementUploadController::store()`, and `vendor/bin/sail artisan test --compact` is green. If not, stop and report.
- Load these project skills before writing the matching code: `laravel-best-practices` (controller, form requests, service class, middleware), `tailwindcss-development` (Blade views, Alpine-bound classes), `testing-best-practices` (Pest tests).
- Use Boost `search-docs` for any framework or package API you are unsure about (Laravel 13, laravel-mongodb 5.11, Pest 5, Tailwind v4 incl. the CSS-variable shorthand `bg-(--group)/15` used in Step 5, Alpine 3).
- After editing PHP files run `vendor/bin/sail bin pint --dirty --format agent`.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| PHP (Sail runtime) | 8.4 | `compose.yaml` |
| Laravel | 13.33.0. CSRF middleware `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` accepts the `X-CSRF-TOKEN` header (checked in vendor source) | `composer.lock`, vendor |
| MongoDB driver | `mongodb/laravel-mongodb` 5.11.0; replica set, `DB::transaction()` works. Query builder does **not** convert `BackedEnum` values: pass `->value` strings in `where()` / `updateOrCreate()` search arrays | `composer.lock`, Issue 3 plan |
| Tests | Pest 5.2.1, `pestphp/pest-plugin-laravel` 5; Feature tests use `DatabaseMigrations`; Unit tests do not boot Laravel. `postJson()` / `assertJsonPath()` available. No browser-testing plugin installed | `composer.json` |
| Session | `mongodb` driver locally, `array` in tests; session data persists across requests within one test | Issue 2 plan |
| Frontend | Tailwind v4 (CSS-first `resources/css/app.css`), Alpine ^3.17 started in `resources/js/app.js` (Issue 1). No axios, no `csrf-token` meta tag in the layout | `package.json`, Issue 1 plan |

### Current state of the codebase

Today the repository is still the MongoDB-switched Laravel skeleton (only `User`, default migrations, `welcome.blade.php`). Issues 1–3 are planned but not yet implemented. This plan assumes their **finished** state as specified in their plan files:

- `routes/web.php`: auth group `Route::middleware(['auth', DiscardPendingStatementImport::class])` with `upload` (GET), `upload.store` (POST `/upload`), `upload.review` (GET `/upload/review`), `upload.confirm` (POST `/upload/confirm`), `upload.discard` (POST `/upload/discard`) on `StatementUploadController`. **Two routes added.**
- `app/Http/Middleware/DiscardPendingStatementImport.php`: `const SESSION_KEY = 'statement_import'`; forgets the key unless `$request->routeIs('upload.review', 'upload.confirm', 'upload.discard')`. **Allowlist extended.**
- `app/Http/Controllers/StatementUploadController.php`:
  - `store()` parses, runs `RuleMatcher::fromDatabase()->matchAll($parsed->entries)` and puts `$parsed->toArray() + ['assignments' => list of RuleMatch::toArray()]` into the session.
  - private `pendingAssignments(ParsedStatement $statement): array` → `list<RuleMatch>` (falls back to `RuleMatch::none()` for all entries if missing/wrong length).
  - `review()` passes `statement`, `existing`, `rows` (`list<array{entry: ParsedEntry, match: RuleMatch}>`) to `pages.upload-review`.
  - `confirm()` deletes a duplicate statement and creates the statement plus transactions (with `group_key`, `share_divisor`, `ignored`, `rule_id` from the same-index `RuleMatch`) inside `DB::transaction()`, forgets the session key, redirects to `upload` with `status` "`<Period label>` imported · `<n>` entries".
  - **Modified in this issue.**
- `app/Services/Rules/RuleMatch.php`: `final readonly`, `?string $groupKey`, `int $shareDivisor`, `bool $ignored`, `?string $ruleId`; `none()`, `fromRule()`, `toArray()`/`fromArray()` (keys `group_key`, `share_divisor`, `ignored`, `rule_id`), `state(string $direction)` → `ignored` | `group` | `unassigned` (out, no group) | `income`. Unchanged.
- `app/Services/Rules/TextNormalizer.php`: `static normalize(?string): string` (uppercase, umlaut folding, whitespace removed). Pure PHP. Unchanged.
- `app/Services/Rules/RuleMatcher.php`: loads **all** rules (seeded and manual), sorts by priority desc, pattern length desc, `_id` asc; first match wins. Unchanged (manual rules are picked up automatically on the next upload).
- `app/Models/Rule.php`: fillable `field, pattern, direction, priority, group_key, share_divisor, ignore, source`; enum casts `RuleField`, `RuleDirection`, `RuleSource`; defaults priority 100, share 1, ignore false. `RuleFactory` has states `manual()` and `ignoring()`. **Gets a constant.**
- `database/seeders/RuleSeeder.php`: syncs only `source = seeded` rules; never touches `manual` rules. Unchanged.
- `app/Services/Statements/ParsedEntry.php`: `bookedOn, valueOn, type, ?counterparty, purpose, merchant, amountCents`, `direction()`. Unchanged.
- `resources/views/pages/upload-review.blade.php`: optional amber duplicate banner; sticky summary card (title, subline "Statement 6 · 69 entries", Discard + "Confirm import"/"Replace import" buttons, 4 figures); table card with one `<tr>` per entry (`data-assignment`, `data-group`), group chip / `÷n` badge / "ignored" badge / dashed "No group" chip under the merchant. `@use('App\Support\Money')`, `@use('App\Support\Period')`. **Modified in this issue.**
- `resources/views/components/group-chip.blade.php`: `<x-group-chip group="rent" />` (dot in the group colour + name). Unchanged.
- `resources/js/app.js`: imports Alpine, `window.Alpine = Alpine; Alpine.start();`. **Modified.**
- `tests/Pest.php`: `DatabaseMigrations` for Feature; helpers `fixturePath()`, `statementUpload()`, `blankPdf()`, `fixtureStatement()`, `entry()`. **Extended.**
- `tests/Feature/StatementUploadTest.php` (Issues 2 + 3): several tests confirm an upload **without** assigning the unmatched entries. After this issue confirm is blocked while entries are open, so those tests are updated in Step 7 (exact list there).

### Fixture facts (from Issue 3's analysis, used by tests)

With seeded rules the June fixture (`tests/Fixtures/ing-2026-06.pdf`, 69 entries, 0-based indexes) has 64 grouped, 3 ignored, 1 income (#60, +1.348,19 €) and **exactly 1 unassigned outgoing entry: #65**, type `Echtzeitüberweisung`, amount −20000, counterparty null, purpose `''`, **merchant `Echtzeitüberweisung`**, booked on 29.06.

Without any rules: 65 outgoing entries are unassigned and 4 incoming entries are income (#32 +18,36, #60, #63, #64). Entries with merchant `TEGUT` exist several times (count them from `fixtureStatement()` in tests; do not hardcode).

### Domain rules that apply

- Groups are fixed in `config/expenses.php` (`groups`: 10 keys `rent`, `utilities`, `groceries`, `subscriptions`, `hobbies`, `online_orders`, `takeaway`, `restaurants`, `health`, `other`, each with `name`, `color`, `sort`).
- Rules live in the `rules` collection: `field` (`merchant`/`counterparty`/`purpose`/`type`), `pattern` ("contains" after normalization), `direction` (`in`/`out`/`any`), `priority` (higher wins), `group_key`, `share_divisor`, `ignore`, `source` (`seeded`/`manual`).
- **Unmatched outgoing entries stay unassigned** until the user picks a group. "Other" is only set by explicit rules or the user.
- Income (incoming, not ignored) is stored and not grouped. Ignored entries are stored but excluded from totals. **Neither needs a group.**
- Entries are editable only during upload; once confirmed they are final. Nothing about the pending import (picks, "always" choices) is written to the database before confirm.
- Matching normalizes text (uppercase, no whitespace, umlauts folded) via `TextNormalizer`.

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | How a group pick reaches the server | **Alpine + `fetch`.** Each chip click / checkbox change POSTs JSON to a new route that updates the pending import in the session and returns the new queue state; the page updates in place (no reload). The server is the single source of truth: Alpine only renders the state it gets back. |
| 2 | Queue layout | **Separate "To review" card** between the summary card and the table. It holds every entry no rule matched (Issue 3 state `unassigned` at upload time), in statement order, each with its chip picker always visible. Entries **stay in the card after a pick** (no jumping rows). The table below holds all other entries, in statement order; queue entries are not repeated there. |
| 3 | Picker scope | **Only queue entries** get the picker and can be re-picked any number of times until confirm. Rule-matched, ignored and income entries stay read-only. There is no "clear" action; the only way back to unassigned is unticking "always" on another entry (Decision 7). |
| 4 | Picker chip look | **Dot + name, wrapping.** One button per group in config order, same look as `<x-group-chip>`; the selected chip gets a border, ring and 15 % background in the group colour. Exact classes in Step 5. |
| 5 | Counter | **Pill in the summary card**, next to the "Statement 6 · 69 entries" subline: amber "`N` to review" while `N > 0`, green "✓ All assigned" at 0. No pill when the queue is empty from the start. |
| 6 | Disabled confirm | "Confirm import"/"Replace import" is `disabled` (dimmed, `cursor-not-allowed`) while `N > 0`, with the line **"Assign a group to every entry to confirm."** under the buttons. The server also refuses (Decision 9). |
| 7 | Unticking "always" | Entries that got their group **only** through the tick go back to unassigned; the entry where you untick keeps its group (as a normal pick). No rule is saved. |
| 8 | Priority of manual rules | **300** (seeded rules use 100/200), so the user's choices win. Stored as `Rule::MANUAL_PRIORITY`. |
| 9 | Error texts | Save fails (network, 4xx other than 409, 5xx): rose line in the To review card **"Couldn't save your choice. Please try again."**; the UI keeps the previous state (pick/checkbox rolled back). Pending import gone: server answers `409` with `{"redirect": "<route('upload')>"}` and the page navigates there. Confirm forced with open entries: redirect back to the review with a rose banner **"1 entry still needs a group."** / **"`N` entries still need a group."** |
| 10 | When the checkbox can be ticked | **Only after the entry has a group**; before that it is `disabled`. Ticking applies the entry's group at once to the other unassigned entries of the same merchant. |
| 11 | "Same merchant" in the current upload (settled here) | Queue entries whose `TextNormalizer::normalize($merchant)` is **equal**. (On later imports the saved rule matches with "contains", like every rule.) |
| 12 | Picking on an entry whose merchant has "always" on (settled here) | The pick also changes the "always" group for that merchant, so all entries that follow it switch too. Entries with their own different pick keep it. |
| 13 | Manual rule contents (settled here) | `field` `merchant`, `pattern` = the merchant text of the entry where the box was ticked (as displayed, not normalized), `direction` `out`, `priority` 300, `group_key` = the merchant's "always" group at confirm, `share_divisor` 1, `ignore` false, `source` `manual`. Written with `updateOrCreate` keyed on `source`/`field`/`pattern`/`direction`, so it never duplicates. |
| 14 | `rule_id` of manually assigned transactions (settled here) | Entries whose group comes from an "always" merchant (the ticked entry and its followers, i.e. every queue entry of that merchant whose final group equals the "always" group) get that manual rule's id. Plain picks get `rule_id` null. Always `share_divisor` 1, `ignored` false. |
| 15 | Success message after confirm (settled here) | Unchanged: "June 2026 imported · 69 entries". |

## Scope

### In scope

- `ReviewQueue` service (pure PHP): queue membership, picks, "always" per merchant, open count, JSON state, final matches.
- Pending-import session keys `picks` and `always`.
- Routes `upload.assign` and `upload.always` (JSON), their form requests, middleware allowlist.
- Confirm: server-side block while entries are open; manual rules created on confirm; manual assignments stored.
- Review page: counter pill, disabled confirm + hint, error banner, "To review" card with chip picker and "always" checkbox; queue entries removed from the table (the Issue 3 "No group" chip goes away).
- Alpine component `reviewQueue` in `resources/js/review-queue.js`.
- Tests: unit tests for `ReviewQueue`, feature tests for the queue, updates of Issue 2/3 tests affected by the confirm block.

### Out of scope

- Overview dashboard, KPI tiles, charts, aggregation, ECharts (Issue 5). Trends (Issue 6).
- Changing groups of rule-matched, ignored or income entries (Decision 3); a "clear pick" action; share divisors on manual assignments or manual rules (always 1); ignore rules created by the user.
- Rule management UI, editing/deleting manual rules, re-running rules on stored transactions, editing entries after confirm, editable groups (project "Out of scope").
- Grouping income.
- A `beforeunload` warning when leaving with picks (leaving discards everything, as in Issue 2).
- Changing the upload page or the success message.

## Prerequisites

Containers running:

```sh
vendor/bin/sail up -d
```

No new packages (Composer or npm). No new `.env` keys. No migrations (the `rules` collection exists since Issue 3).

## Implementation steps

### Step 1: `ReviewQueue` service and manual-priority constant

**Files**
- New: `app/Services/Statements/ReviewQueue.php`
- Modify: `app/Models/Rule.php` — add `public const MANUAL_PRIORITY = 300;`

`ReviewQueue` must not use the container, facades or app helpers (`config()`, `session()`, …): it is unit-tested without Laravel. It may use `ParsedEntry`, `RuleMatch`, `TextNormalizer`.

```php
final class ReviewQueue
{
    /**
     * @param  list<ParsedEntry>  $entries
     * @param  list<RuleMatch>  $matches  same length and order as $entries (Issue 3 results)
     * @param  array<int, string>  $picks  entry index => group key, hand picks
     * @param  array<string, array{merchant: string, group_key: string}>  $always  normalized merchant => choice
     */
    public function __construct(array $entries, array $matches, array $picks = [], array $always = []) {}

    /** @return list<int> indexes whose match state is 'unassigned', ascending */
    public function queue(): array;

    public function contains(int $index): bool;
    public function groupFor(int $index): ?string;   // $picks[$i] ?? $always[key]['group_key'] ?? null
    public function isAlways(int $index): bool;      // entry follows the merchant's "always" choice
    public function openCount(): int;                // queue entries with groupFor() === null

    public function pick(int $index, string $groupKey): void;
    public function setAlways(int $index, bool $on): void;

    /** @return array<int, string> */
    public function picks(): array;
    /** @return array<string, array{merchant: string, group_key: string}> */
    public function always(): array;

    /** @return array{entries: array<int, array{group: ?string, always: bool}>, open: int} */
    public function state(): array;

    /**
     * @param  array<string, string>  $ruleIds  normalized merchant => id of the saved manual rule
     * @return list<RuleMatch>
     */
    public function finalMatches(array $ruleIds): array;
}
```

Behaviour (`key(i)` = `TextNormalizer::normalize($entries[$i]->merchant)`):

- Constructor: ignore `picks` for indexes not in the queue and `always` entries whose key belongs to no queue entry (defensive; the controller never writes them).
- `queue()`: indexes `i` where `$matches[$i]->state($entries[$i]->direction()) === 'unassigned'`.
- `isAlways(i)`: `isset(always[key(i)]) && groupFor(i) === always[key(i)]['group_key']`. An entry of the same merchant with its own different pick is **not** "always" (its checkbox shows unticked).
- `pick(i, g)`: throws `\InvalidArgumentException` if `i` is not in the queue. If `isAlways(i)` (checked **before** the pick), also sets `always[key(i)]['group_key'] = g` (Decision 12). Then sets `picks[i] = g`.
- `setAlways(i, true)`: throws `\InvalidArgumentException` if `i` is not in the queue, `\LogicException` if `groupFor(i) === null`. Sets `picks[i] = groupFor(i)` and `always[key(i)] = ['merchant' => $entries[$i]->merchant, 'group_key' => groupFor(i)]`. Every other queue entry of the same key without its own pick now follows (through `groupFor`).
- `setAlways(i, false)`: throws `\InvalidArgumentException` if `i` is not in the queue. If `! isAlways(i)`, nothing changes. Otherwise sets `picks[i] = groupFor(i)` (the entry keeps its group), then `unset(always[key(i)])`. Followers without their own pick return to `null` (Decision 7).
- `state()`: one item per queue index, `['group' => groupFor(i), 'always' => isAlways(i)]`, plus `'open' => openCount()`.
- `finalMatches($ruleIds)`: copy of `$matches`; for every queue index `i`: `new RuleMatch(groupFor(i), 1, false, $ruleId)` where `$ruleId = $ruleIds[key(i)] ?? null` if `isAlways(i)`, else `null` (Decision 14). Non-queue matches are returned unchanged.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `tests/Unit/ReviewQueueTest.php` (Step 7); the rest of the app is untouched, so `vendor/bin/sail artisan test --compact` stays green.

### Step 2: Routes, middleware, form requests

**Files**
- Modify: `routes/web.php` — two routes in the existing auth group, next to the other `upload.*` routes
- Modify: `app/Http/Middleware/DiscardPendingStatementImport.php` — allowlist
- New: `app/Http/Requests/AssignReviewEntryRequest.php`
- New: `app/Http/Requests/ToggleAlwaysRuleRequest.php`

| Method | URI | Name | Action |
|---|---|---|---|
| POST | `/upload/assign` | `upload.assign` | `StatementUploadController@assign` |
| POST | `/upload/always` | `upload.always` | `StatementUploadController@always` |

Middleware: `$request->routeIs('upload.review', 'upload.confirm', 'upload.discard', 'upload.assign', 'upload.always')` keeps the pending import.

Form requests (`vendor/bin/sail artisan make:request <Name> --no-interaction`), `authorize()` → `true`:

- `AssignReviewEntryRequest::rules()`: `'entry' => ['required', 'integer', 'min:0']`, `'group' => ['required', 'string', Rule::in(array_keys(config('expenses.groups')))]` (`Illuminate\Validation\Rule`; the name clashes with `App\Models\Rule` only if both are imported, which they are not here).
- `ToggleAlwaysRuleRequest::rules()`: `'entry' => ['required', 'integer', 'min:0']`, `'always' => ['required', 'boolean']`.

Both are called with `Accept: application/json`, so validation failures return `422` JSON automatically. No custom messages (never shown to the user; the page shows Decision 9's generic text).

**Commands**

```sh
vendor/bin/sail artisan make:request AssignReviewEntryRequest --no-interaction
vendor/bin/sail artisan make:request ToggleAlwaysRuleRequest --no-interaction
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** after Step 3, `vendor/bin/sail artisan route:list --path=upload` lists 7 routes.

### Step 3: Controller: queue state, assign, always, confirm guard, manual rules

**Files**
- Modify: `app/Http/Controllers/StatementUploadController.php`

Session layout of the pending import after this issue (key `DiscardPendingStatementImport::SESSION_KEY`):

```php
[
    // Issue 2: number, period, statement_date, old_balance_cents, new_balance_cents, entries
    // Issue 3: assignments (list of RuleMatch arrays)
    'picks' => [65 => 'other'],                                                   // array<int, string>
    'always' => ['ECHTZEITUEBERWEISUNG' => ['merchant' => 'Echtzeitüberweisung', 'group_key' => 'other']],
]
```

Write `picks` and `always` **as whole arrays** (`session([SESSION_KEY.'.picks' => $queue->picks(), SESSION_KEY.'.always' => $queue->always()])`). Never address keys inside `always` with dot notation: normalized merchants can contain dots (`WWW.AMAZON`). `store()` does not write them (absent = empty); a fresh upload replaces the whole `statement_import` value, so old picks never leak into a new upload.

Changes (keep everything else from Issues 2/3):

- Private helper `reviewQueue(ParsedStatement $statement): ReviewQueue` → `new ReviewQueue($statement->entries, $this->pendingAssignments($statement), session(SESSION_KEY.'.picks', []), session(SESSION_KEY.'.always', []))`. Private helper `storeReviewQueue(ReviewQueue $queue): void` writes both arrays as above.
- **`review()`**: build `$queue = $this->reviewQueue($statement)`. Pass to the view, in addition to `statement` and `existing`:
  - `queueRows`: `list<array{index: int, entry: ParsedEntry}>` for `$queue->queue()`;
  - `rows`: Issue 3's rows **without** queue indexes (keep statement order);
  - `queueState`: `$queue->state()`;
  - `open`: `$queue->openCount()`.
- **`assign(AssignReviewEntryRequest $request): JsonResponse`**:
  - No pending import → `response()->json(['redirect' => route('upload')], 409)`.
  - `$index = $request->integer('entry')`; if `! $queue->contains($index)` → `throw ValidationException::withMessages(['entry' => 'This entry cannot be assigned.'])` (422).
  - `$queue->pick($index, $request->string('group')->toString())`; store; `return response()->json($queue->state())`.
- **`always(ToggleAlwaysRuleRequest $request): JsonResponse`**: same 409 and 422 (`entry`) handling; if `$request->boolean('always')` and `$queue->groupFor($index) === null` → `ValidationException::withMessages(['always' => 'Pick a group first.'])` (422). Otherwise `$queue->setAlways($index, $request->boolean('always'))`; store; return `$queue->state()`.
- **`confirm()`** (after the existing "no pending data" redirect):
  - `$queue = $this->reviewQueue($parsed)`. If `($open = $queue->openCount()) > 0` → `return redirect()->route('upload.review')->with('review_error', $open === 1 ? '1 entry still needs a group.' : "{$open} entries still need a group.")`. Nothing is written; the pending import stays.
  - Inside the existing `DB::transaction()`, **before** creating transactions: for each `$key => $choice` of `$queue->always()`:

    ```php
    $rule = Rule::query()->updateOrCreate(
        ['source' => RuleSource::Manual->value, 'field' => RuleField::Merchant->value, 'pattern' => $choice['merchant'], 'direction' => RuleDirection::Out->value],
        ['priority' => Rule::MANUAL_PRIORITY, 'group_key' => $choice['group_key'], 'share_divisor' => 1, 'ignore' => false],
    );
    $ruleIds[$key] = $rule->id;
    ```

  - Build the transaction rows from `$queue->finalMatches($ruleIds)` instead of `pendingAssignments()` (same fields as Issue 3: `group_key`, `share_divisor`, `ignored`, `rule_id`).
  - Everything else (duplicate replace, session forget, success redirect/message) unchanged.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan route:list --path=upload
```

**Step check:** `route:list --path=upload` shows `upload`, `upload.store`, `upload.review`, `upload.confirm`, `upload.discard`, `upload.assign`, `upload.always`, each with `auth` and `DiscardPendingStatementImport`.

### Step 4: Alpine component

**Files**
- New: `resources/js/review-queue.js`
- Modify: `resources/js/app.js` — register it before `Alpine.start()`

`resources/js/app.js`:

```js
import Alpine from 'alpinejs';
import reviewQueue from './review-queue';

window.Alpine = Alpine;
Alpine.data('reviewQueue', reviewQueue);
Alpine.start();
```

`resources/js/review-queue.js`:

```js
export default ({ state, assignUrl, alwaysUrl, csrf }) => ({
    state,          // { entries: { [index]: { group, always } }, open }
    busy: false,
    error: false,

    pick(entry, group) {
        return this.send(assignUrl, { entry, group });
    },

    async toggleAlways(entry, event) {
        const ok = await this.send(alwaysUrl, { entry, always: event.target.checked });
        if (!ok) event.target.checked = this.state.entries[entry].always;
    },

    async send(url, body) {
        if (this.busy) return false;
        this.busy = true;
        this.error = false;
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            if (response.status === 409) {
                window.location.href = (await response.json()).redirect;
                return false;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.state = await response.json();
            return true;
        } catch {
            this.error = true;
            return false;
        } finally {
            this.busy = false;
        }
    },
});
```

The UI always renders from `state`, so a failed pick needs no explicit rollback; only the native checkbox is reset (above).

**Commands**

```sh
vendor/bin/sail npm run build
```

**Step check:** build exits 0; `grep -l 'X-CSRF-TOKEN' public/build/assets/app-*.js` matches one file.

### Step 5: Review page

**Files**
- Modify: `resources/views/pages/upload-review.blade.php`

1. **Alpine root**: the existing outer `<div class="mx-auto max-w-3xl space-y-4">` gets
   `x-data="reviewQueue(@js(['state' => $queueState, 'assignUrl' => route('upload.assign'), 'alwaysUrl' => route('upload.always'), 'csrf' => csrf_token()]))"`.
   Always present (with an empty queue the state is `{entries: [], open: 0}`).

2. **Error banner** (first child, above the duplicate banner): `@if (session('review_error'))` → `<div role="alert" data-review-error class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300"><x-heroicon-o-exclamation-circle class="size-5 shrink-0" />{{ session('review_error') }}</div>`.

3. **Counter pill** (summary card, same line as "Statement 6 · 69 entries": wrap subline and pill in `<div class="flex flex-wrap items-center gap-2">`), only `@if (count($queueRows) > 0)`:
   - `<span data-review-counter x-show="state.open > 0" @style(['display: none' => $open === 0]) class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/10 dark:text-amber-300"><span x-text="state.open">{{ $open }}</span> to review</span>` (visible text "`N` to review"; `@style` renders `style="display: none;"`).
   - `<span data-review-done x-show="state.open === 0" @style(['display: none' => $open > 0]) class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300"><x-heroicon-m-check class="size-3.5" />All assigned</span>`.

4. **Confirm button** (both labels): add `data-confirm`, `@disabled($open > 0)`, `:disabled="state.open > 0"` and the classes `disabled:cursor-not-allowed disabled:opacity-50` (add `disabled:hover:bg-emerald-600 dark:disabled:hover:bg-emerald-500` so hover does not change a disabled button). Under the button row (inside the card, right-aligned on `sm:`): `<p x-show="state.open > 0" @style(['display: none' => $open === 0]) class="mt-2 text-xs text-slate-500 sm:text-right dark:text-slate-400">Assign a group to every entry to confirm.</p>`.

5. **"To review" card** (between summary card and table), only `@if (count($queueRows) > 0)`:

   ```blade
   <section aria-labelledby="review-heading" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
       <h2 id="review-heading" class="flex items-center gap-2 px-4 pt-4 text-sm font-semibold">
           <x-heroicon-o-queue-list class="size-5 text-amber-600 dark:text-amber-400" /> To review
       </h2>
       <p x-show="error" x-cloak role="alert" class="px-4 pt-2 text-sm text-rose-600 dark:text-rose-400">Couldn't save your choice. Please try again.</p>
       <ul class="divide-y divide-slate-100 dark:divide-slate-800">
           @foreach ($queueRows as ['index' => $i, 'entry' => $entry]) … @endforeach
       </ul>
   </section>
   ```

   Each `<li data-review-entry="{{ $i }}" class="space-y-3 px-4 py-3">`:
   - Line 1 `<div class="flex items-baseline gap-3">`: date (`Carbon::parse($entry->bookedOn)->format('d.m.')`, `w-12 shrink-0 text-sm tabular-nums text-slate-500 dark:text-slate-400`), merchant (`min-w-0 flex-1 truncate text-sm font-medium`, `title` = merchant), amount (`Money::format($entry->amountCents, true)`, `whitespace-nowrap text-sm font-medium tabular-nums text-rose-600 dark:text-rose-400`; queue entries are always outgoing).
   - Picker `<div role="group" aria-label="Group for {{ $entry->merchant }}" class="flex flex-wrap gap-1.5">`, one button per `config('expenses.groups')` entry in config order:

     ```blade
     <button type="button" data-pick="{{ $key }}"
         style="--group: {{ $group['color'] }}"
         @click="pick({{ $i }}, '{{ $key }}')"
         :disabled="busy"
         :aria-pressed="state.entries[{{ $i }}].group === '{{ $key }}'"
         aria-pressed="{{ $queueState['entries'][$i]['group'] === $key ? 'true' : 'false' }}"
         :class="state.entries[{{ $i }}].group === '{{ $key }}'
             ? 'border-(--group) bg-(--group)/15 ring-1 ring-(--group) text-slate-900 dark:text-slate-100'
             : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600'"
         class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait dark:focus-visible:outline-emerald-400">
         <span class="size-2 shrink-0 rounded-full bg-(--group)"></span>{{ $group['name'] }}
     </button>
     ```

     Also server-render the initial state in the static `class` attribute (no flash before Alpine starts): compute `$selected = $queueState['entries'][$i]['group'] === $key` and add the selected string when true, the unselected string otherwise (`@class`). Alpine's `:class` then takes over. Both class strings must appear literally in the Blade file so Tailwind generates them.
   - Checkbox `<label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400" :class="state.entries[{{ $i }}].group === null && 'opacity-50'">` with `<input type="checkbox" data-always class="size-4 rounded border-slate-300 text-emerald-600 focus-visible:outline-emerald-600 dark:border-slate-600 dark:bg-slate-800" :checked="state.entries[{{ $i }}].always" :disabled="busy || state.entries[{{ $i }}].group === null" @change="toggleAlways({{ $i }}, $event)" @checked($queueState['entries'][$i]['always']) @disabled($queueState['entries'][$i]['group'] === null)>` and the text `Always use this group for <span class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->merchant }}</span>`.

6. **Table**: loop over `$rows` (queue entries are no longer in it). Remove the Issue 3 `unassigned` branch (the dashed "No group" chip) — table rows can no longer be `unassigned`. Keep everything else (`data-assignment`, chips, `÷n`, ignored style). If `$rows` is empty, do not render the table card.

**Commands**

```sh
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** built CSS contains the group-colour classes: `grep -c -- '--group' public/build/assets/app-*.css` prints a number ≥ 1.

### Step 6: Test helper

**Files**
- Modify: `tests/Pest.php` — add:

```php
/** Assigns every still-open queue entry of the pending import to $group via the JSON route. */
function assignOpenEntries(string $group = 'other'): void
{
    $import = session('statement_import');

    foreach ($import['assignments'] as $i => $assignment) {
        $merchantKey = \App\Services\Rules\TextNormalizer::normalize($import['entries'][$i]['merchant']);
        $open = $assignment['group_key'] === null
            && ! $assignment['ignored']
            && $import['entries'][$i]['amount_cents'] < 0
            && ! isset(($import['picks'] ?? [])[$i])
            && ! isset(($import['always'] ?? [])[$merchantKey]);

        if ($open) {
            test()->postJson(route('upload.assign'), ['entry' => $i, 'group' => $group])->assertOk();
        }
    }
}
```

It skips entries already picked by hand and entries whose merchant has an "always" choice, so "always" followers keep following (and get the manual `rule_id` on confirm).

**Step check:** used by Step 7.

### Step 7: Tests (new and updated)

**Files**
- New: `tests/Unit/ReviewQueueTest.php` (`vendor/bin/sail artisan make:test --pest --unit ReviewQueueTest --no-interaction`)
- New: `tests/Feature/ReviewQueueTest.php` (`vendor/bin/sail artisan make:test --pest ReviewQueueTest --no-interaction`)
- Modify: `tests/Feature/StatementUploadTest.php` — the Issue 2/3 tests listed as "modify" in the Tests table

**Commands**

```sh
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** all tests pass.

## Tests

Test runner and command: `vendor/bin/sail artisan test --compact`

**Unit tests** (`tests/Unit/ReviewQueueTest.php`, no Laravel): build entries with `entry()` from `tests/Pest.php` and matches with `new RuleMatch(...)` / `RuleMatch::none()`. Standard setup used below: `e0 = entry(-399, 'VISA TEGUT FILIALE 5020')`, `e1 = entry(-499, 'VISA TEGUT FILIALE 1234')`, `e2 = entry(-250, 'VISA TE GUT FILIALE 7')` (merchant `TE GUT`, normalizes to `TEGUT`), `e3 = entry(-1000, 'VISA REWE MARKT')`, `e4 = entry(-20000, null, '', 'Echtzeitüberweisung')`, `e5 = entry(134819, 'CGS GmbH', '', 'Gehalt/Rente')` (income), `e6 = entry(51540, null, 'Miete', 'Gutschrift/Dauerauftrag')` with match `new RuleMatch(null, 1, true, 'r-ignore')`, `e7 = entry(-115820, null, 'Miete', 'Dauerauftrag/Terminueberw.')` with match `new RuleMatch('rent', 3, false, 'r-rent')`; all others `RuleMatch::none()`.

**Feature tests** use `DatabaseMigrations`, `User::factory()->create()` + `actingAs`, `statementUpload()`, `fixtureStatement()`. "seeded" = `$this->seed(RuleSeeder::class)` before the upload. Manual rule lookups: `Rule::query()->where('source', 'manual')` (string values).

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `tests/Unit/ReviewQueueTest.php` (new) | `queues only unmatched outgoing entries` | standard setup → `queue()` = `[0, 1, 2, 3, 4]`; `contains(5)`, `contains(6)`, `contains(7)` false |
| same | `counts open entries` | fresh → `openCount()` 5; after `pick(4, 'other')` → 4 |
| same | `assigns one entry` | `pick(0, 'groceries')` → `groupFor(0)` `groceries`; `groupFor(1)` and `groupFor(2)` null; `picks()` = `[0 => 'groceries']` |
| same | `re-picks an entry` | `pick(0, 'groceries')`, `pick(0, 'health')` → `groupFor(0)` `health` |
| same | `refuses entries outside the queue` | `pick(7, 'other')` and `setAlways(5, true)` throw `InvalidArgumentException` |
| same | `refuses always without a group` | `setAlways(0, true)` throws `LogicException` |
| same | `applies always to the other unassigned entries of the merchant` | `pick(1, 'health')`, `pick(0, 'groceries')`, `setAlways(0, true)` → `groupFor(2)` `groceries` (normalized merchant `TE GUT`), `groupFor(1)` stays `health`, `groupFor(3)` null; `isAlways(0)` and `isAlways(2)` true, `isAlways(1)` false (own pick `health`); `always()` = `['TEGUT' => ['merchant' => 'TEGUT', 'group_key' => 'groceries']]`; `openCount()` 2 |
| same | `follows a new pick while always is on` | after the above, `pick(0, 'other')` → `groupFor(2)` `other`, `always()['TEGUT']['group_key']` `other`, `groupFor(1)` `health` |
| same | `ignores unticking on an entry that does not follow always` | same setup as `applies always…`, then `setAlways(1, false)` → `always()` unchanged, `groupFor(2)` still `groceries` |
| same | `moves always to another group when ticked on a differing entry` | same setup as `applies always…`, then `setAlways(1, true)` → `always()['TEGUT']['group_key']` `health`, `groupFor(2)` `health`, `groupFor(0)` `groceries` (own pick), `isAlways(0)` false |
| same | `returns followers to unassigned when always is unticked` | `pick(0, 'groceries')`, `setAlways(0, true)`, `setAlways(0, false)` → `groupFor(0)` `groceries`, `groupFor(2)` null, `always()` `[]` |
| same | `keeps the group of a follower that unticks` | `pick(0, 'groceries')`, `setAlways(0, true)`, `setAlways(2, false)` → `groupFor(2)` `groceries` (now a pick), `groupFor(0)` `groceries`, `always()` `[]` |
| same | `restores its state from arrays` | after picks and an always tick, `new ReviewQueue($entries, $matches, $q->picks(), $q->always())` → same `state()` |
| same | `exports the state for the page` | `pick(0, 'groceries')`, `setAlways(0, true)` → `state()` = `['entries' => [0 => ['group' => 'groceries', 'always' => true], 1 => ['group' => 'groceries', 'always' => true], 2 => ['group' => 'groceries', 'always' => true], 3 => ['group' => null, 'always' => false], 4 => ['group' => null, 'always' => false]], 'open' => 2]` |
| same | `builds the final matches` | `pick(1, 'health')`, `pick(0, 'groceries')`, `setAlways(0, true)`, `pick(3, 'groceries')`, `pick(4, 'other')`; `finalMatches(['TEGUT' => 'r-manual'])` → #0 and #2 `('groceries', 1, false, 'r-manual')`; #1 `('health', 1, false, null)`; #3 `('groceries', 1, false, null)`; #4 `('other', 1, false, null)`; #5, #6, #7 identical to the input matches |
| `tests/Feature/ReviewQueueTest.php` (new) | `redirects guests from the queue routes` | guest `post('/upload/assign')`, `post('/upload/always')` → redirect `route('login')`; `postJson` to both → 401 |
| same | `shows the unmatched transfer in the review queue` | seeded; upload; `get(route('upload.review'))` 200: `assertSee('To review')`; `substr_count` of `data-review-entry=` = 1 and `data-review-entry="65"` present; `substr_count` of `data-pick=` = 10; `assertSeeText('1 to review')`; `assertSee('Always use this group for')`; `assertSee('Assign a group to every entry to confirm.')`; the confirm button tag (regex on `<button[^>]*data-confirm[^>]*>`) contains `disabled`; `substr_count('<tr')` = 69 (68 table entries + header); `assertSeeInOrder(['To review', 'Echtzeitüberweisung', 'Netflix + Router'])` |
| same | `hides the queue when every entry is matched` | seeded + `Rule::factory()->manual()->create(['field' => RuleField::Merchant, 'pattern' => 'Echtzeitüberweisung', 'direction' => RuleDirection::Out, 'group_key' => 'other'])`; upload; review: `assertDontSee('To review')`, no `data-review-counter`, no `data-review-done`, confirm button has no `disabled`, `<tr` count 70 |
| same | `assigns a group to a queue entry` | seeded; upload; `postJson(route('upload.assign'), ['entry' => 65, 'group' => 'other'])` → 200, `assertJsonPath('entries.65.group', 'other')`, `assertJsonPath('entries.65.always', false)`, `assertJsonPath('open', 0)`; `session('statement_import.picks')` = `[65 => 'other']`; `Transaction::count()` 0, `Rule::query()->where('source', 'manual')->count()` 0; follow-up review: the `<span data-review-done …>` opening tag (regex) does not contain `display: none` and the `<span data-review-counter …>` tag does; confirm button not `disabled`; the `data-pick="other"` button has `aria-pressed="true"` |
| same | `rejects invalid assignments` (dataset) | seeded; upload; each → 422 and `session('statement_import.picks')` stays null: `['entry' => 65, 'group' => 'pets']`; `['entry' => 1, 'group' => 'other']` (rule-matched); `['entry' => 60, 'group' => 'other']` (income); `['entry' => 63, 'group' => 'other']` (ignored); `['entry' => 999, 'group' => 'other']`; `['group' => 'other']`; `['entry' => 65]` |
| same | `refuses always without a group` | seeded; upload; `postJson(route('upload.always'), ['entry' => 65, 'always' => true])` → 422 with error key `always`; `session('statement_import.always')` null |
| same | `answers 409 when nothing is pending` | no upload; `postJson(route('upload.assign'), ['entry' => 0, 'group' => 'other'])` and `postJson(route('upload.always'), ['entry' => 0, 'always' => true])` → 409, `assertJson(['redirect' => route('upload')])` |
| same | `keeps the pending import while assigning` | seeded; upload; assign 65; tick always 65 → both 200; `get(route('upload.review'))` 200; `data-always` checkbox of entry 65 has `checked` |
| same | `applies always to the other unassigned entries of the merchant` | no rules; upload; `$tegut` = indexes of `fixtureStatement()->entries` with merchant `TEGUT` (assert ≥ 2); assign `$tegut[0]` `groceries`; `postJson(route('upload.always'), ['entry' => $tegut[0], 'always' => true])` → 200; for every `$i` in `$tegut`: `entries.$i.group` `groceries`, `entries.$i.always` true; `open` = 65 − `count($tegut)`; `session('statement_import.always')` = `['TEGUT' => ['merchant' => 'TEGUT', 'group_key' => 'groceries']]` |
| same | `returns followers to unassigned when always is unticked` | as above, then `['entry' => $tegut[0], 'always' => false]` → `entries.{$tegut[0]}.group` `groceries`; `entries.{$tegut[1]}.group` null; `open` = 64 |
| same | `blocks confirm while entries are unassigned` | seeded; upload; `post(route('upload.confirm'))` → redirect `route('upload.review')`, `assertSessionHas('review_error', '1 entry still needs a group.')`, `session('statement_import')` still set, `Statement::count()` 0, `Transaction::count()` 0; follow-up review shows `1 entry still needs a group.` inside `data-review-error` |
| same | `counts all open entries when blocking confirm` | no rules; upload; confirm → `assertSessionHas('review_error', '65 entries still need a group.')`; `Statement::count()` 0 |
| same | `does not need groups for income and ignored entries` | seeded; upload; assign 65 `other`; confirm → redirect `route('upload')` with status `June 2026 imported · 69 entries`; the 134819 transaction `group_key` null, `ignored` false; 3 transactions `ignored` true with `group_key` null |
| same | `stores the manual assignment on confirm` | seeded; upload; assign 65 `other`; confirm → the −20000 transaction: `group_key` `other`, `share_divisor` 1, `ignored` false, `rule_id` null; `Rule::query()->where('source', 'manual')->count()` 0; `Transaction::whereNotNull('group_key')->count()` 65 |
| same | `creates the rule only when ticked and only on confirm` | seeded; upload; assign 65 `other`; tick always 65 → manual rule count still 0; confirm → exactly 1 manual rule: `field` `RuleField::Merchant`, `pattern` `Echtzeitüberweisung`, `direction` `RuleDirection::Out`, `priority` 300, `group_key` `other`, `share_divisor` 1, `ignore` false, `source` `RuleSource::Manual`; the −20000 transaction `rule_id` = its id; `Rule::count()` = 43 (42 seeded + 1) |
| same | `does not create the rule when unticked again` | seeded; upload; assign 65, tick, untick; confirm → manual rule count 0; the −20000 transaction `group_key` `other`, `rule_id` null |
| same | `does not create the rule on discard` | seeded; upload; assign + tick 65; `post(route('upload.discard'))` → manual rule count 0 |
| same | `discards the picks when leaving the review` | seeded; upload; assign 65; `get(route('overview'))`; `postJson(route('upload.assign'), ['entry' => 65, 'group' => 'other'])` → 409 |
| same | `uses the manual rule on the next import` | seeded; upload; assign 65 `other`; tick; confirm; upload the fixture again → `session('statement_import.assignments.65')` = `['group_key' => 'other', 'share_divisor' => 1, 'ignored' => false, 'rule_id' => <manual rule id>]`; review: no `data-review-entry=`, no `data-review-counter`, `Replace import` button without `disabled`; confirm → redirect `route('upload')`; the new −20000 transaction `group_key` `other`, `rule_id` = manual rule id; manual rule count still 1 |
| same | `stores the always group for every entry of the merchant` | no rules; upload; `$tegut` as above; assign `$tegut[0]` `groceries`; tick always; then `assignOpenEntries('other')`; confirm → one manual rule with pattern `TEGUT`, `group_key` `groceries`; every transaction with merchant `TEGUT` has `group_key` `groceries` and `rule_id` = that rule's id; no other transaction has a `rule_id`; `Transaction::whereNull('group_key')->count()` 4 (the incoming ones) |
| `tests/Feature/StatementUploadTest.php` (modify) | `parses the upload and shows the review` (Issue 2) | replace the `substr_count($html, '<tr') = 70` assertion by: `substr_count($html, 'data-review-entry=')` = 65 and `substr_count($html, '<tr')` = 5 (4 income rows + header), so 65 + 4 = 69 entries are shown; all other assertions unchanged (the texts they look for are still on the page) |
| same | `stores the statement and entries on confirm` (Issue 2) | call `assignOpenEntries()` after the upload, before confirm; replace "every transaction has `group_key` null" by: the 65 outgoing transactions have `group_key` `other`, the 4 incoming ones `null`; all 69 keep `share_divisor` 1, `ignored` false, `rule_id` null; everything else unchanged |
| same | `replaces the existing statement on confirm` (Issue 2) | call `assignOpenEntries()` before confirm; assertions unchanged |
| same | `does not treat another month with the same number as duplicate` (Issue 2) | call `assignOpenEntries()` before confirm; assertions unchanged |
| same | `groups entries on the review page` (Issue 3) | `data-assignment="group"` 64, `data-assignment="ignored"` 3, `data-assignment="income"` 1 unchanged; `data-assignment="unassigned"` now **0**; `No group` now **0**; `data-review-entry=` 1; `<tr` count now **69** |
| same | `stores the rule results on confirm` (Issue 3) | call `assignOpenEntries()` before confirm; the −20000 transaction now `group_key` `other`, `rule_id` null, `ignored` false; `whereNotNull('group_key')->count()` now **65**; other assertions unchanged |
| same | `stores what was matched at upload time` (Issue 3) | call `assignOpenEntries()` before `Rule::query()->delete()`; assertions unchanged |
| same | `imports without rules` (Issue 3) | review: `data-review-entry=` 65 (replaces "`No group` 65 times"), `data-assignment="income"` 4, `assertSeeText('65 to review')`; then `assignOpenEntries()`; confirm → the 65 outgoing transactions `group_key` `other`, the 4 incoming `null`; all `ignored` false, `rule_id` null |

All other Issue 1–3 tests stay unchanged and must pass.

## Automated verification

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `vendor/bin/sail artisan test --compact` | All tests pass (Issues 1–4), 0 failures |
| 2 | Code style | `vendor/bin/sail bin pint --format agent` | No files changed |
| 3 | Frontend builds | `vendor/bin/sail npm run build` | Exit 0 |
| 4 | Component bundled | `grep -l 'X-CSRF-TOKEN' public/build/assets/app-*.js` | One file matched |
| 5 | Group-colour picker classes generated | `grep -c -- '--group' public/build/assets/app-*.css` | ≥ 1 |
| 6 | Routes | `vendor/bin/sail artisan route:list --path=upload` | 7 routes incl. `upload.assign` (POST `upload/assign`) and `upload.always` (POST `upload/always`), each with `auth` and `DiscardPendingStatementImport` |
| 7 | Manual rules only via the app | Boost `database-query-mongodb` count `rules` with `{source: "manual"}` in the default DB before and after check 1 | Same count (tests use the `testing` DB) |
| 8 | Seeder leaves manual rules alone | `vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction`, then count `rules` by `source` | `seeded` = 42; `manual` unchanged from check 7 |
| 9 | No errors logged | Boost `last-error` and `read-log-entries` (last 20) | No new errors or exceptions |

## Manual verification

- [ ] Start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run build`; seed the rules: `vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction`; log in at http://localhost. If June 2026 is already imported with a manual rule for `Echtzeitüberweisung` from earlier testing, delete that rule first (Boost `database-query-mongodb` or tinker: `App\Models\Rule::where('source', 'manual')->delete()`).
- [ ] Upload `tests/Fixtures/ing-2026-06.pdf` → review page. The summary card shows an amber pill "1 to review"; "Confirm import" (or "Replace import") is dimmed, the cursor shows not-allowed on hover, and "Assign a group to every entry to confirm." sits under the buttons.
- [ ] Below the summary card a "To review" card lists "29.06. Echtzeitüberweisung −200,00 €" with 10 chips (colour dot + name, in config order) and a dimmed, unclickable "Always use this group for **Echtzeitüberweisung**" checkbox. The table below no longer contains this entry and shows no "No group" chips.
- [ ] Click "Other" → the chip gets a grey ring/tint instantly without a page reload; the pill turns green "✓ All assigned"; the confirm button becomes active and the hint disappears; the checkbox becomes clickable.
- [ ] Click "Health", then "Other" again → the selection moves each time; only one chip is selected.
- [ ] Tick "Always use this group for Echtzeitüberweisung". Reload the page → pick and tick are still there.
- [ ] In the browser dev tools, set Network to "Offline" and click a chip → rose "Couldn't save your choice. Please try again." appears and the selection stays as before; go back online, click again → message disappears, pick applies.
- [ ] Click "Confirm import"/"Replace import" → Upload page with "June 2026 imported · 69 entries".
- [ ] Upload the same PDF again → no "To review" card, no pill, the transfer shows an "Other" chip in the table, the confirm button is active. Click "Replace import".
- [ ] "Always" with several entries: temporarily empty the rules (`vendor/bin/sail artisan tinker --execute 'App\Models\Rule::query()->delete();'`), upload the PDF → "65 to review". Pick "Groceries & Personal Care" on a TEGUT row and tick "always" → all TEGUT rows switch to that chip at once and the counter drops accordingly. Untick → the other TEGUT rows go back to no selection; the one you unticked keeps its chip. Click "Discard", then restore the rules with `vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction` (this does not recreate deleted manual rules).
- [ ] Keyboard: Tab into the To review card → each chip and the checkbox get a visible emerald focus outline; Enter/Space selects a chip.
- [ ] Light mode: selected chip clearly tinted in its group colour (check a few: Rent indigo, Groceries green, Other grey); amber pill, green pill, rose banner/line readable.
- [ ] Dark mode (switch the OS theme, reload): chips on dark background, selected tint visible, disabled confirm button still readable but clearly inactive, no white boxes.
- [ ] Narrow window (~375 px): the 10 chips wrap over several lines inside the card, no horizontal scroll; merchant truncates, amount stays on one line; the pill wraps under the subline if needed.

## Requirement traceability

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| After upload you land in a review queue | Steps 3, 5 | `shows the unmatched transfer in the review queue`, `hides the queue when every entry is matched` | #1; manual: "To review" card |
| Unassigned entries appear first | Steps 3, 5 (queue card above the table, queue entries removed from the table) | `shows the unmatched transfer in the review queue` (`assertSeeInOrder`, `<tr` count), `parses the upload and shows the review`, `groups entries on the review page` | #1; manual: "To review" card |
| Counter ("3 to review") in the header | Steps 1, 3, 5 | `counts open entries`, `shows the unmatched transfer in the review queue` (`1 to review`), `assigns a group to a queue entry` (`All assigned`), `imports without rules` (`65 to review`) | #1; manual: pill |
| Group picker: row of coloured chips, one click | Steps 2, 3, 4, 5 | `assigns one entry`, `re-picks an entry`, `assigns a group to a queue entry`, `rejects invalid assignments`, `answers 409 when nothing is pending`, `redirects guests from the queue routes` | #1, #4, #5, #6; manual: click chip, light/dark, narrow, keyboard, error line |
| "Always use this group for ‹merchant›" applies immediately to other unassigned entries of the merchant in this upload | Steps 1, 3, 4, 5 | `applies always to the other unassigned entries of the merchant` (unit + feature), `follows a new pick while always is on`, `returns followers to unassigned when always is unticked` (unit + feature), `keeps the group of a follower that unticks`, `refuses always without a group` (unit + feature) | #1; manual: TEGUT "always" |
| Saved as a `manual` rule on confirm (nothing persists before confirm) | Steps 1, 3 | `creates the rule only when ticked and only on confirm`, `does not create the rule when unticked again`, `does not create the rule on discard`, `discards the picks when leaving the review`, `builds the final matches`, `stores the always group for every entry of the merchant` | #1, #7, #8 |
| "Confirm import" disabled while outgoing entries are unassigned | Steps 3, 5 | `blocks confirm while entries are unassigned`, `counts all open entries when blocking confirm`, `shows the unmatched transfer in the review queue` (`disabled`) | #1; manual: dimmed button + hint |
| Income and ignored entries need no group | Steps 1, 3 | `queues only unmatched outgoing entries`, `rejects invalid assignments` (#60, #63), `does not need groups for income and ignored entries` | #1 |
| Tests: confirm blocked with open entries | Step 7 | `blocks confirm while entries are unassigned`, `counts all open entries when blocking confirm` | #1 |
| Tests: manual assignment stored | Step 7 | `stores the manual assignment on confirm`, `stores the always group for every entry of the merchant` | #1 |
| Tests: rule created only when ticked and only on confirm | Step 7 | `creates the rule only when ticked and only on confirm`, `does not create the rule when unticked again`, `does not create the rule on discard` | #1, #7 |
| Tests: rule used on next import | Step 7 | `uses the manual rule on the next import` | #1 |
| Done when: assign the −200 transfer, tick "always", confirm, and a later upload matches it automatically | Steps 1–5 | `uses the manual rule on the next import` | #1; manual: assign → tick → confirm → re-upload without queue |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] With seeded rules, uploading `tests/Fixtures/ing-2026-06.pdf` shows "1 to review" with the −200,00 € Echtzeitüberweisung in the "To review" card and a disabled confirm button; picking a group enables it.
- [ ] Ticking "Always use this group for Echtzeitüberweisung" and confirming creates exactly one `manual` rule (merchant / `Echtzeitüberweisung` / out / priority 300); uploading the statement again shows no review queue and the transfer already grouped.
- [ ] No rule or transaction is written before confirm; discarding or leaving the review drops all picks.
