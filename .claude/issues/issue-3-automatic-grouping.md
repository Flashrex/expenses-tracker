# Issue 3: Automatic grouping via rules

> Source: `.claude/project-plan.md` › "Issue 3 – Automatic grouping via rules"
> Planned: 2026-09-28
> Depends on: Issue 1 (`.claude/issues/issue-1-foundation.md`: `config/expenses.php` groups, layouts, heroicons, `tests/Pest.php` with `DatabaseMigrations`) and Issue 2 (`.claude/issues/issue-2-statement-upload-parsing.md`: `Statement`/`Transaction` models, `IngStatementParser`, `ParsedEntry`/`ParsedStatement`, `StatementUploadController`, review page, fixture `tests/Fixtures/ing-2026-06.pdf`).

## Goal

Entries arrive on the review screen already grouped, ignored, or marked as a ÷3 share wherever a rule matches. Rules live in a `rules` collection, seeded (and kept in sync) from a `rules` section in `config/expenses.php`, and a matching service applies them with text normalization and priorities. The match results are stored with each transaction on confirm.

Original "Done when": **the June fixture imports with everything grouped except genuinely unknown entries (e.g. the `Echtzeitüberweisung` −200 with no readable recipient).**

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run every PHP, Artisan, Composer, Node and test command through Sail: `vendor/bin/sail …`. Host PHP lacks `ext-mongodb`, so host `php artisan` fails. If containers are down, run `vendor/bin/sail up -d` first.
- Issues 1 and 2 must be fully implemented first. Check: `config/expenses.php` has `groups`, `app/Services/Statements/IngStatementParser.php` and `app/Http/Controllers/StatementUploadController.php` exist, `resources/views/pages/upload-review.blade.php` exists, and `vendor/bin/sail artisan test --compact` is green. If not, stop and report.
- Load these project skills before writing the matching code: `laravel-best-practices` (models, enums, migrations, seeders, services, controller), `tailwindcss-development` (Blade views/components), `testing-best-practices` (Pest tests).
- Use Boost `search-docs` for any framework or package API you are unsure about (Laravel 13, laravel-mongodb 5.11, Pest 5, Tailwind v4).
- After editing PHP files run `vendor/bin/sail bin pint --dirty --format agent`.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| PHP (Sail runtime) | 8.4 (`mb_strtoupper('ß')` returns `SS`) | `compose.yaml`, checked |
| Laravel | 13.33.0 | `composer.lock` |
| MongoDB driver | `mongodb/laravel-mongodb` 5.11.0; server `mongodb/mongodb-atlas-local:8.0` (replica set). `Model::getIdAttribute()` returns the `_id` as a string. The **query builder does not convert `BackedEnum` values** (no enum handling in `src/Query/Builder.php`), so always pass `->value` strings in `where()` clauses. Enum casts on models work for reading/writing attributes. | `composer.lock`, vendor source |
| Tests | Pest 5.2.1; Feature tests use `DatabaseMigrations` (Issue 1); Unit tests do not boot Laravel | Issue 1/2 plans |
| Frontend | Tailwind v4 (CSS-first), heroicons via `blade-ui-kit/blade-heroicons` | Issue 1 plan |

### Current state of the codebase

Today the repository is still the MongoDB-switched Laravel skeleton; Issues 1 and 2 are planned but not yet implemented. This plan assumes their **finished** state, as specified in their plan files:

- `config/expenses.php` (Issue 1): only a `groups` key, keyed by group key in sort order: `rent`, `utilities`, `groceries`, `subscriptions`, `hobbies`, `online_orders`, `takeaway`, `restaurants`, `health`, `other`, each `['name' => …, 'color' => '#rrggbb', 'sort' => n]`. Names: Rent, Electricity & Gas, Groceries & Personal Care, Subscriptions & Internet, Hobbies & Entertainment, Online Orders, Takeaway & Fast Food, Restaurants & Bars, Health, Other. PHPDoc shape `@return array{groups: array<string, array{name: string, color: string, sort: int}>}`. **Extended in this issue.**
- `database/seeders/DatabaseSeeder.php` (Issue 1): empty `run()`. **Extended in this issue.**
- `database/migrations/0001_01_01_00000{0,1,2}_*.php` and (Issue 2) `2026_09_28_100000_create_statements_collection.php`, `2026_09_28_100001_create_transactions_collection.php`, style `Schema::create('<collection>', function (Blueprint $collection) { … })` with `MongoDB\Laravel\Schema\Blueprint`.
- `app/Models/Transaction.php` (Issue 2): fillable includes `group_key, share_divisor, ignored, rule_id`; casts `share_divisor => integer`, `ignored => boolean`; `$attributes` defaults `group_key null, share_divisor 1, ignored false, rule_id null`. **Gets a `rule()` relation.**
- `app/Services/Statements/ParsedEntry.php` (Issue 2): `final readonly` with `bookedOn, valueOn, type, ?counterparty, purpose (lines joined with "\n"), merchant, amountCents`, `direction(): 'in'|'out'`, `toArray()`, `fromArray()`. `ParsedStatement` has `entries` (`list<ParsedEntry>`), `toArray()` / `fromArray()` reading only its own keys. Unchanged.
- `app/Services/Statements/MerchantDeriver.php` (Issue 2): `derive(string $type, ?string $counterparty, string $purpose): string`. Unchanged; used by tests to build synthetic entries.
- `app/Http/Middleware/DiscardPendingStatementImport.php` (Issue 2): `const SESSION_KEY = 'statement_import'`; forgets it outside `upload.review|confirm|discard`. Unchanged (the assignments live under the same key, so they are discarded with it).
- `app/Http/Controllers/StatementUploadController.php` (Issue 2): `store()` puts `$parsed->toArray()` into the session; `review()` passes `statement` and `existing` to `pages.upload-review`; `confirm()` creates the statement and `createMany()` transactions with `group_key null, share_divisor 1, ignored false, rule_id null` inside `DB::transaction()`. **Modified in this issue.**
- `resources/views/pages/upload-review.blade.php` (Issue 2): table with Date, Merchant (`truncate max-w-0 w-full`, `title` = full name), Amount cells; `<thead class="sr-only">`. **Modified in this issue.**
- `tests/Pest.php` (Issue 1/2): `DatabaseMigrations` for Feature; helpers `fixturePath()`, `statementUpload()`, `blankPdf()`. **Extended in this issue.**
- `tests/Feature/StatementUploadTest.php` (Issue 2): its tests never seed rules, so they keep passing unchanged (all transactions stay ungrouped without rules).
- No `app/Enums`, no `app/Services/Rules`, no `Rule` model, no `rules` collection.

### Fixture analysis (done while planning; do not repeat as a separate step)

The June fixture was parsed with Issue 2's algorithm and matched against the rule set in Step 2. Entry indexes below are 0-based positions in `ParsedStatement::entries`:

| Result | Count | Entries |
|---|---|---|
| Grouped (outgoing) | 64 | rent 1, utilities 2, groceries 27, subscriptions 6, hobbies 10, online_orders 7, takeaway 3, restaurants 4, health 1, other 3 |
| Ignored | 3 | #32 `Rundfunkbeitrag (3 Monate)` +18,36; #63 `Miete` +515,40; #64 `Miete 313,34 und Nebenkosten …` +515,40 |
| Income (no rule) | 1 | #60 `CGS Clinical Guideline Serv ices` +1.348,19 (`Gehalt/Rente`) |
| Unassigned (outgoing, no rule) | 1 | #65 `Echtzeitüberweisung` −200,00 |

Raw sums per group (cents, before the share divisor): rent −115820, utilities −38800, groceries −29349, subscriptions −7145, hobbies −7420, online_orders −15835, takeaway −6118, restaurants −9210, health −15065, other −6945.

Nine seeded rules have no matching entry in the fixture (MUELLER, CineStar, Google Payment Ireland, Lieferando, McDonalds, Selecta, Kiosk, Apotheke, outgoing Rundfunkbeitrag). They are tested with synthetic entries. Three fixture entries match two rules of the same group at equal priority; the longer pattern wins (`WWW.AMAZON` over `AMAZON`, `steampowered` over `STEAM`, `ALDI TALK` over `E-Plus`), which the `records the matching rule` test pins down.

### Domain rules that apply

- Groups are fixed and defined in `config/expenses.php`. `.claude/groups.md` is not read at runtime.
- **Rules live in the DB** (`rules` collection), seeded from `config/expenses.php`. A rule matches an entry by field (`merchant`/`counterparty`/`purpose`/`type`) + "contains" pattern + direction (`in`/`out`/`any`), with a priority, and either assigns a **group** (optionally with a **share divisor**) or marks the entry **ignored**. Fields: `field, pattern, direction, priority, group_key (nullable), share_divisor, ignore, source (seeded/manual)`.
- **Shared costs**: rent, Strom/Gas and Rundfunkbeitrag count as outgoing ÷ 3. The share sits on the rule, not the group.
- **Roommate reimbursements are ignored** (incoming `Miete`, incoming `Rundfunkbeitrag`). Ignored entries are stored but excluded from all totals.
- **Income** is stored and not grouped.
- **Unmatched outgoing entries stay unassigned** (`group_key` null). "Other" is only set by explicit rules or by the user.
- **Matching normalizes text**: uppercase, remove whitespace, fold umlauts (Ä→AE, Ö→OE, Ü→UE, ß→SS), for both the entry text and the pattern. Evaluate by priority; first match wins.
- Entries are editable only during upload; once confirmed they are final. No re-running rules on stored transactions.
- Counted amount of an entry = `amount_cents / share_divisor`, only for non-ignored entries (used from Issue 5 on; nothing in this issue computes it).

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | Do group rules match incoming entries (refunds)? | **No.** Every group rule has direction `out`. Only the two ignore rules have direction `in`. An incoming refund from a known merchant is treated as income (no group, not ignored). |
| 2 | Seeder behaviour on re-run | **Sync from config.** Idempotent upsert of `source = seeded` rules keyed by (`field`, `pattern`, `direction`); seeded rules no longer in config are deleted; `manual` rules are never touched. The whole config is validated before anything is written. |
| 3 | Group chip placement on the review table | **Under the merchant name**, on a second line in the merchant cell. Still 3 columns. |
| 4 | Amount for ÷3 entries | **Full bank amount** in the amount column plus a `÷3` badge next to the chip. No counted amount on the review page. |
| 5 | Look of ignored entries | **Whole row at `opacity-50`, amount struck through (`line-through`)**, grey "ignored" badge under the merchant. |
| 6 | Unmatched outgoing entry | **Dashed "No group" chip** under the merchant. (Issue 4 replaces it with the picker.) Incoming non-ignored entries (income) get no chip line at all. |
| 7 | Summary card In / Out | **Unchanged raw bank sums** (Issue 2 behaviour). |
| 8 | Field of the outgoing Rundfunkbeitrag ÷3 rule | **`purpose`**, same field as the incoming ignore rule. |
| 9 | Field per pattern (settled from the fixture data, not asked) | `purpose` for `Miete`, `Netflix + Router`, `ALDI TALK`, `Rundfunkbeitrag`; `counterparty` for `RhoenEnergie Fulda`; `type` for `Abschluss`; `merchant` for all others. Full table in Step 2. |
| 10 | Priority semantics (settled here) | Integer, **higher wins**. Default `100`; `AMAZON PRIM` and `Baderbetrieb` get `200`. Ties: longer normalized pattern first, then older rule (`_id` ascending). |
| 11 | When matching runs (settled here) | Once, in `upload.store` right after parsing. The results are kept in the session next to the parsed entries and written on confirm, so the review shows exactly what gets stored. |

## Scope

### In scope

- Enums `RuleField`, `RuleDirection`, `RuleSource`.
- `rules` collection (migration), `Rule` model + factory, `Transaction::rule()` relation.
- `rules` section in `config/expenses.php` with the 42 seeded rules.
- `RuleSeeder` (validate + sync), called from `DatabaseSeeder`.
- `TextNormalizer`, `RuleMatch` value object, `RuleMatcher` service.
- Matching on upload, match results kept in the session, stored on confirm (`group_key`, `share_divisor`, `ignored`, `rule_id`).
- Review table: group chip, `÷n` badge, "ignored" badge (dimmed row), "No group" chip; new `<x-group-chip>` component.
- Tests: normalization, seeder, each seeded rule, priorities, directions, upload/review/confirm integration.

### Out of scope

- Review queue, sorting unassigned entries first, "3 to review" counter, group picker, "Always use this group for ‹merchant›", manual rules, blocking confirm while entries are unassigned (Issue 4). Confirm stays enabled whatever is unassigned.
- Counted amounts, KPI tiles, charts, aggregation (Issues 5, 6). Overview/Trends keep their empty states.
- Rule management UI, editing groups, re-running rules on stored transactions, editing entries after confirm (project "Out of scope").
- Grouping incoming entries / refunds (Decision 1), changing the summary card figures (Decision 7).
- Deployment of the seeder to production (deployment pipeline is out of scope; the command is documented in Step 4).

## Prerequisites

Containers running:

```sh
vendor/bin/sail up -d
```

No new packages (Composer or npm). No new `.env` keys.

## Implementation steps

### Step 1: Enums, `rules` collection, `Rule` model, factory

**Files**
- New: `app/Enums/RuleField.php`
- New: `app/Enums/RuleDirection.php`
- New: `app/Enums/RuleSource.php`
- New: `database/migrations/2026_09_28_100002_create_rules_collection.php`
- New: `app/Models/Rule.php`
- New: `database/factories/RuleFactory.php`
- Modify: `app/Models/Transaction.php` — add `rule(): BelongsTo`

Generate the model with `vendor/bin/sail artisan make:model Rule --factory --no-interaction`; create the enums with `vendor/bin/sail artisan make:enum <Name> --string --no-interaction` (check `make:enum --help` for the exact option); write the migration by hand in the Issue 2 style.

**Enums** (TitleCase cases, string-backed):

- `App\Enums\RuleField`: `Merchant = 'merchant'`, `Counterparty = 'counterparty'`, `Purpose = 'purpose'`, `Type = 'type'`. Method `valueOf(ParsedEntry $entry): string` returning `$entry->merchant`, `$entry->counterparty ?? ''`, `$entry->purpose`, `$entry->type` respectively (`match`).
- `App\Enums\RuleDirection`: `In = 'in'`, `Out = 'out'`, `Any = 'any'`. Method `matches(string $entryDirection): bool` → `$this === self::Any || $this->value === $entryDirection`.
- `App\Enums\RuleSource`: `Seeded = 'seeded'`, `Manual = 'manual'`.

**Migration** `rules`: `$collection->index('source');` `down()`: `Schema::dropIfExists('rules')`.

**`App\Models\Rule`** (`extends MongoDB\Laravel\Eloquent\Model`, `use HasFactory`):

- `#[Fillable(['field', 'pattern', 'direction', 'priority', 'group_key', 'share_divisor', 'ignore', 'source'])]` (attribute style as in `User`).
- `casts()`: `field => RuleField::class`, `direction => RuleDirection::class`, `source => RuleSource::class`, `priority => 'integer'`, `share_divisor => 'integer'`, `ignore => 'boolean'`.
- `$attributes = ['priority' => 100, 'group_key' => null, 'share_divisor' => 1, 'ignore' => false];`
- `transactions(): HasMany` → `$this->hasMany(Transaction::class)` (foreign key `rule_id`).
- PHPDoc `@property` lines for all attributes with their types.

**`Transaction::rule(): BelongsTo`** → `$this->belongsTo(Rule::class)`.

**`RuleFactory`** definition: `field => RuleField::Merchant`, `pattern => 'TEGUT'`, `direction => RuleDirection::Out`, `priority => 100`, `group_key => 'groceries'`, `share_divisor => 1`, `ignore => false`, `source => RuleSource::Seeded`. States: `manual()` (`source => RuleSource::Manual`), `ignoring()` (`group_key => null, ignore => true`).

**Commands**

```sh
vendor/bin/sail artisan migrate --no-interaction
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** `vendor/bin/sail artisan migrate:status` lists `2026_09_28_100002_create_rules_collection` as ran; Boost `database-query-mongodb` `listIndexes` on `rules` shows `{source: 1}`.

### Step 2: Rules in `config/expenses.php`

**Files**
- Modify: `config/expenses.php` — add a `rules` key after `groups`; extend the PHPDoc shape.

PHPDoc becomes:
`@return array{groups: array<string, array{name: string, color: string, sort: int}>, rules: list<array{field: string, pattern: string, direction: string, group_key?: string, share_divisor?: int, priority?: int, ignore?: bool}>}`

Omitted optional keys mean: `group_key` null, `share_divisor` 1, `priority` 100, `ignore` false. Write the rows in exactly this order (the order only matters for readability; matching order comes from priority/length/_id):

| # | field | pattern | direction | group_key | share_divisor | priority | ignore |
|---|---|---|---|---|---|---|---|
| 1 | purpose | `Miete` | out | rent | 3 | | |
| 2 | counterparty | `RhoenEnergie Fulda` | out | utilities | 3 | | |
| 3 | merchant | `TEGUT` | out | groceries | | | |
| 4 | merchant | `REWE` | out | groceries | | | |
| 5 | merchant | `EDEKA` | out | groceries | | | |
| 6 | merchant | `ALDI SUED` | out | groceries | | | |
| 7 | merchant | `BAECKEREI HAPP` | out | groceries | | | |
| 8 | merchant | `TEO FULDA` | out | groceries | | | |
| 9 | merchant | `ROSSMANN` | out | groceries | | | |
| 10 | merchant | `MUELLER` | out | groceries | | | |
| 11 | purpose | `Netflix + Router` | out | subscriptions | | | |
| 12 | merchant | `Spotify` | out | subscriptions | | | |
| 13 | merchant | `Discovery` | out | subscriptions | | | |
| 14 | merchant | `AMAZON PRIM` | out | subscriptions | | 200 | |
| 15 | merchant | `E-Plus` | out | subscriptions | | | |
| 16 | purpose | `ALDI TALK` | out | subscriptions | | | |
| 17 | merchant | `STEAM` | out | hobbies | | | |
| 18 | merchant | `steampowered` | out | hobbies | | | |
| 19 | merchant | `CineStar` | out | hobbies | | | |
| 20 | merchant | `Baderbetrieb` | out | hobbies | | 200 | |
| 21 | merchant | `Google Payment Ireland` | out | hobbies | | | |
| 22 | merchant | `AMAZON` | out | online_orders | | | |
| 23 | merchant | `WWW.AMAZON` | out | online_orders | | | |
| 24 | merchant | `rebuy` | out | online_orders | | | |
| 25 | merchant | `Takeaway.com` | out | takeaway | | | |
| 26 | merchant | `Lieferando` | out | takeaway | | | |
| 27 | merchant | `McDonalds` | out | takeaway | | | |
| 28 | merchant | `UNI DONER` | out | takeaway | | | |
| 29 | merchant | `Selecta` | out | takeaway | | | |
| 30 | merchant | `Kiosk` | out | takeaway | | | |
| 31 | merchant | `VIVA HAVANNA` | out | restaurants | | | |
| 32 | merchant | `RESTAURANT PIZZERIA` | out | restaurants | | | |
| 33 | merchant | `RISTORANTE LA ROMA` | out | restaurants | | | |
| 34 | merchant | `LS CHUMBOS` | out | restaurants | | | |
| 35 | merchant | `DAK-Gesundheit` | out | health | | | |
| 36 | merchant | `Apotheke` | out | health | | | |
| 37 | purpose | `Rundfunkbeitrag` | out | other | 3 | | |
| 38 | merchant | `LOTTO` | out | other | | | |
| 39 | merchant | `Bargeldauszahlung` | out | other | | | |
| 40 | type | `Abschluss` | out | other | | | |
| 41 | purpose | `Miete` | in | | | | true |
| 42 | purpose | `Rundfunkbeitrag` | in | | | | true |

Sketch of the first and last rows:

```php
'rules' => [
    ['field' => 'purpose', 'pattern' => 'Miete', 'direction' => 'out', 'group_key' => 'rent', 'share_divisor' => 3],
    // … rows 2–40 exactly as in the table
    ['field' => 'purpose', 'pattern' => 'Rundfunkbeitrag', 'direction' => 'in', 'ignore' => true],
],
```

**Step check:** `vendor/bin/sail artisan tinker --execute 'echo count(config("expenses.rules"));'` prints `42`.

### Step 3: Text normalizer, match result, matcher

**Files**
- New: `app/Services/Rules/TextNormalizer.php`
- New: `app/Services/Rules/RuleMatch.php`
- New: `app/Services/Rules/RuleMatcher.php`

**`TextNormalizer`** — `final class` with `public static function normalize(?string $text): string`. No container/facades/helpers (unit-tested without Laravel):

```php
$text = mb_strtoupper($text ?? '');
$text = strtr($text, ['Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ẞ' => 'SS', 'ß' => 'SS']);

return preg_replace('/\s+/u', '', $text);
```

Punctuation is kept (`Takeaway .com` → `TAKEAWAY.COM`, `Netflix + Router` → `NETFLIX+ROUTER`).

**`RuleMatch`** — `final readonly class` with constructor properties `?string $groupKey`, `int $shareDivisor`, `bool $ignored`, `?string $ruleId`. Methods:

- `static none(): self` → `(null, 1, false, null)`.
- `static fromRule(Rule $rule): self` → ignore rule: `(null, 1, true, $rule->id)`; group rule: `($rule->group_key, $rule->share_divisor, false, $rule->id)`.
- `toArray(): array` → keys `group_key`, `share_divisor`, `ignored`, `rule_id`. `static fromArray(array $data): self`.
- `state(string $direction): string` → `'ignored'` if `ignored`; `'group'` if `groupKey !== null`; `'unassigned'` if `$direction === 'out'`; else `'income'`.

**`RuleMatcher`** — `final class`:

```php
/** @var list<array{rule: Rule, pattern: string}> */
private array $rules;

/** @param iterable<Rule> $rules */
public function __construct(iterable $rules) { /* normalize patterns, drop empty ones, sort */ }

public static function fromDatabase(): self
{
    return new self(Rule::query()->get());
}

public function match(ParsedEntry $entry): RuleMatch

/** @param list<ParsedEntry> $entries @return list<RuleMatch> */
public function matchAll(array $entries): array
```

- Constructor: for each rule, `pattern = TextNormalizer::normalize($rule->pattern)`; skip rules whose normalized pattern is `''`. Sort by `priority` descending, then `mb_strlen(pattern)` descending, then `(string) $rule->id` ascending (`usort` with `<=>` chains).
- `match()`: `$direction = $entry->direction()`; normalize each field value lazily (cache per call); return `RuleMatch::fromRule($rule)` for the first rule where `$rule->direction->matches($direction)` and `str_contains(TextNormalizer::normalize($rule->field->valueOf($entry)), $pattern)`. No hit → `RuleMatch::none()`.
- `matchAll()` → `array_map($this->match(...), $entries)` (keeps indexes 0..n-1).

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `TextNormalizerTest` and `RuleMatcherTest` in Step 7.

### Step 4: Rule seeder

**Files**
- New: `database/seeders/RuleSeeder.php` (`vendor/bin/sail artisan make:seeder RuleSeeder --no-interaction`)
- Modify: `database/seeders/DatabaseSeeder.php` — `run()` body: `$this->call(RuleSeeder::class);`

**`RuleSeeder::run(): void`**, in this order:

1. **Validate all rows** of `config('expenses.rules')` before writing anything. Throw `\InvalidArgumentException` with a message naming the row index and pattern when:
   - `field` is not a `RuleField` value, or `direction` not a `RuleDirection` value (`tryFrom()` returns null);
   - `pattern` is missing or normalizes to `''`;
   - `ignore` is true **and** `group_key` is set, or `ignore` is false **and** `group_key` is missing;
   - `group_key` is set but not a key of `config('expenses.groups')`;
   - `share_divisor` is not an int ≥ 1;
   - two rows share the same key (`field` + `pattern` + `direction`, pattern compared as written).
2. **Upsert** each row: `Rule::query()->updateOrCreate(['source' => RuleSource::Seeded->value, 'field' => $field, 'pattern' => $pattern, 'direction' => $direction], ['priority' => $row['priority'] ?? 100, 'group_key' => $row['group_key'] ?? null, 'share_divisor' => $row['share_divisor'] ?? 1, 'ignore' => $row['ignore'] ?? false])`. Pass strings (`->value`), not enum instances, in the search array.
3. **Delete stale seeded rules**: load `Rule::query()->where('source', RuleSource::Seeded->value)->get()`, delete each whose (`field->value`, `pattern`, `direction->value`) key is not in the config. Never query or delete `manual` rules.

Stored transactions keep their `rule_id` even if that rule is deleted later (no cascade).

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction
vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction
```

(On a production environment the same command needs `--force`; running it there is out of scope.)

**Step check:** after both seeder runs, Boost `database-query-mongodb` count on `rules` = `42`, all with `source: "seeded"`.

### Step 5: Match on upload, store on confirm

**Files**
- Modify: `app/Http/Controllers/StatementUploadController.php`

Changes (keep everything else from Issue 2 as it is):

- **`store()`**: after a successful parse:

  ```php
  $matches = RuleMatcher::fromDatabase()->matchAll($parsed->entries);

  session([DiscardPendingStatementImport::SESSION_KEY => $parsed->toArray() + [
      'assignments' => array_map(fn (RuleMatch $match) => $match->toArray(), $matches),
  ]]);
  ```

- Private helper `pendingAssignments(ParsedStatement $statement): array` (returns `list<RuleMatch>`): read `session(SESSION_KEY.'.assignments')`; if it is not an array with exactly `count($statement->entries)` items, return `RuleMatch::none()` for every entry; otherwise map `RuleMatch::fromArray(...)`.
- **`review()`**: build `$rows = array_map(fn (ParsedEntry $entry, RuleMatch $match) => ['entry' => $entry, 'match' => $match], $statement->entries, $this->pendingAssignments($statement))` and pass `rows` to the view in addition to `statement` and `existing` (the view no longer loops over `$statement->entries` directly).
- **`confirm()`**: inside the existing `DB::transaction()`, each transaction row gets `'group_key' => $match->groupKey`, `'share_divisor' => $match->shareDivisor`, `'ignored' => $match->ignored`, `'rule_id' => $match->ruleId` from the same-index `RuleMatch` (instead of the fixed null/1/false/null). The success message stays `"<Period> imported · <n> entries"`.

Rules are **not** re-matched on review or confirm: what was matched at upload time is what gets stored.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** Issue 2's `vendor/bin/sail artisan test --compact --filter=StatementUploadTest` still passes.

### Step 6: Review table: chips and badges

**Files**
- New: `resources/views/components/group-chip.blade.php` — `<x-group-chip group="rent" />`
- Modify: `resources/views/pages/upload-review.blade.php`

**`group-chip`** props: `group` (group key). Reads `$config = config('expenses.groups')[$group]`. Renders:

```blade
<span {{ $attributes->class('inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}>
    <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $config['color'] }}"></span>
    <span class="truncate">{{ $config['name'] }}</span>
</span>
```

**Review table** (loop `@foreach ($rows as ['entry' => $entry, 'match' => $match])`, `$state = $match->state($entry->direction())`):

- `<tr>` gets `data-assignment="{{ $state }}"`; when `$state === 'group'` also `data-group="{{ $match->groupKey }}"`. When `$state === 'ignored'` add class `opacity-50`.
- Merchant cell: keep `max-w-0 w-full px-2 py-2.5`; move `truncate font-medium` and `title` to an inner `<div>` holding the merchant name. Below it, only when `$state !== 'income'`, `<div class="mt-1 flex flex-wrap items-center gap-1.5">` with:
  - `group`: `<x-group-chip :group="$match->groupKey" />`, and when `$match->shareDivisor > 1` a badge `<span title="Your share: 1/{{ $match->shareDivisor }}" class="inline-flex items-center rounded-full border border-slate-200 px-1.5 py-0.5 text-xs font-medium tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300">÷{{ $match->shareDivisor }}</span>`.
  - `ignored`: `<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-heroicon-m-eye-slash class="size-3.5" />ignored</span>`.
  - `unassigned`: `<span class="inline-flex items-center rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-xs font-medium text-slate-500 dark:border-slate-600 dark:text-slate-400">No group</span>`.
- Amount cell: add `line-through` when `$state === 'ignored'`; colours stay as in Issue 2 (rose for out, emerald for in).
- Date cell: add `align-top` to the date and amount cells so they line up with the merchant name when a chip line is present.
- Summary card, banners, buttons, table header: unchanged. Exactly one `<tr>` per entry plus the header row (Issue 2's `<tr` count test must still see 70).

**Commands**

```sh
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `groups entries on the review page` in Step 7.

### Step 7: Tests

**Files**
- New/modify: the files in the Tests table (create new ones with `vendor/bin/sail artisan make:test --pest <Name> --no-interaction`, add `--unit` for Unit tests).
- Modify: `tests/Pest.php` — add the helpers below.

```php
/** The June fixture, parsed once per process. */
function fixtureStatement(): \App\Services\Statements\ParsedStatement
{
    static $statement = null;

    return $statement ??= (new \App\Services\Statements\IngStatementParser)->parse(fixturePath('ing-2026-06.pdf'));
}

/** A synthetic entry; the merchant is derived like the parser does it. */
function entry(int $amountCents, ?string $counterparty = null, string $purpose = '', string $type = 'Lastschrift'): \App\Services\Statements\ParsedEntry
{
    return new \App\Services\Statements\ParsedEntry(
        bookedOn: '2026-06-01',
        valueOn: '2026-06-01',
        type: $type,
        counterparty: $counterparty,
        purpose: $purpose,
        merchant: (new \App\Services\Statements\MerchantDeriver)->derive($type, $counterparty, $purpose),
        amountCents: $amountCents,
    );
}
```

**Commands**

```sh
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** all tests pass.

## Tests

Test runner and command: `vendor/bin/sail artisan test --compact`

`TextNormalizerTest` is a Unit test (no Laravel). Everything touching `Rule` is a Feature test (`DatabaseMigrations`); seed with `$this->seed(RuleSeeder::class)` in `beforeEach` where noted. Feature tests that upload use `User::factory()->create()`, `actingAs`, `statementUpload()` from Issue 2. Rule lookups in tests: `Rule::query()->where('pattern', 'Miete')->where('direction', 'out')->first()` (string values, not enums).

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `tests/Unit/TextNormalizerTest.php` (new) | `normalizes text for matching` (dataset input → output) | `Takeaway .com` → `TAKEAWAY.COM`; `LOTTO He ssen` → `LOTTOHESSEN`; `Discover y` → `DISCOVERY`; `Müller` → `MUELLER`; `RhönEnergie Bäderbetrieb` → `RHOENENERGIEBAEDERBETRIEB`; `UNI DÖNER` → `UNIDOENER`; `Straße` → `STRASSE`; `"Ihr Einkauf\nbei"` → `IHREINKAUFBEI`; `Netflix + Router` → `NETFLIX+ROUTER`; `''` → `''`; `null` → `''` |
| `tests/Feature/Rules/RuleSeederTest.php` (new) | `seeds every configured rule` | after seeding: `Rule::count()` = 42 = `count(config('expenses.rules'))`; all `source` = `RuleSource::Seeded`; `Miete`/out: `group_key` `rent`, `share_divisor` 3, `priority` 100, `ignore` false; `AMAZON PRIM`: `priority` 200; `Miete`/in: `ignore` true, `group_key` null; `Abschluss`: `field` `RuleField::Type` |
| same | `references only configured groups` | every seeded rule with `ignore` false has a `group_key` in `array_keys(config('expenses.groups'))`; every rule with `ignore` true has `group_key` null |
| same | `is idempotent` | seed twice → `Rule::count()` 42; ids of the first run equal ids of the second run |
| same | `syncs changed and removed config rules` | seed; `config()->set('expenses.rules', …)` with row `TEGUT` changed to `group_key` `other`, row `REWE` removed and a new row `['field' => 'merchant', 'pattern' => 'NORMA', 'direction' => 'out', 'group_key' => 'groceries']` added; seed again → `TEGUT` rule has `group_key` `other` and the same id as before; no `REWE` rule; `NORMA` exists; count 42 |
| same | `keeps manual rules` | `Rule::factory()->manual()->create(['pattern' => 'REWE'])`; seed with REWE removed from config → the manual REWE rule still exists |
| same | `rejects invalid rule config` (dataset of one bad row appended to the real config: unknown group `pets`; neither group nor ignore; group and `ignore => true`; `share_divisor` 0; field `iban`; direction `both`; empty pattern `'  '`; duplicate of `TEGUT`/merchant/out) | seeding throws `InvalidArgumentException`; `Rule::count()` 0 |
| `tests/Feature/Rules/RuleMatcherTest.php` (new, `beforeEach` seeds `RuleSeeder`) | `groups the June fixture` | `RuleMatcher::fromDatabase()->matchAll(fixtureStatement()->entries)`: count by `state()` = group 64, ignored 3, income 1, unassigned 1; group counts rent 1, utilities 2, groceries 27, subscriptions 6, hobbies 10, online_orders 7, takeaway 3, restaurants 4, health 1, other 3; entries with `shareDivisor` 3 are exactly #1, #2, #3 |
| same | `leaves only the unknown transfer unassigned` | the single `unassigned` entry is #65: type `Echtzeitüberweisung`, amount −20000; its match is `RuleMatch::none()` values (null, 1, false, null) |
| same | `does not group income` | #60 (`Gehalt/Rente`, 134819): `groupKey` null, `ignored` false, `ruleId` null |
| same | `matches seeded rules on fixture entries` (dataset: index → expected merchant, group or `ignored`, divisor) | #0 `Netflix + Router` subscriptions; #1 `Miete` rent ÷3; #2 `RhoenEnergie Fulda` utilities ÷3; #4 `TEGUT` groceries; #5 `AMAZON` online_orders; #8 `LOTTO He ssen` other; #9 `Spotify` subscriptions; #10 `rebuy recommerc e` online_orders; #11 `ROSSMANN` groceries; #12 `VIVA HAVANNA RESTAURAN` restaurants; #13 `RESTAURANT PIZZERIA` restaurants; #14 `STEAM GAMES` hobbies; #15 `TEO FULDA` groceries; #17 `EDEKA HELLWIG` groceries; #19 `Bargeldauszahlung VISA Card SPARKASSE FULDA` other; #21 `UNI DONER` takeaway; #23 `REWE KAI UWE GRASMUECK` groceries; #25 `BAECKEREI HAPP` groceries; #29 `Takeaway.com Payments` takeaway; #30 `WWW.AMAZON` online_orders; #32 `Rundfunkbeitrag (3 Monate)` ignored; #33 `E-Plus Service` subscriptions; #35 `DAK-Gesundheit` health; #43 `ALDI SUED` groceries; #47 `RISTORANTE LA ROMA` restaurants; #50 `Discovery Communication s Benelux` subscriptions; #54 `RhonEnergie Baderbetrieb` hobbies; #57 `www.s teampowered.com` hobbies; #59 `AMAZON PRIM` subscriptions; #61 `LS CHUMBOS FULDA` restaurants; #63 `Miete` ignored; #64 `Miete 313,34 und Nebenkosten 63,33, Strom: 65,67, Gas` ignored; #68 `Abschluss` other. Each row first asserts `entries[$i]->merchant` equals the expected merchant, then the match (divisor 1 unless ÷3) |
| same | `records the matching rule` | #30 (`WWW.AMAZON`) → `ruleId` = id of the `WWW.AMAZON` rule (longer pattern beats `AMAZON` at equal priority); #57 → `steampowered` rule; #33 → `ALDI TALK` rule; #1 → `Miete`/out rule; #63 → `Miete`/in rule |
| same | `matches seeded rules without a fixture entry` (dataset using `entry()`) | `entry(-899, 'VISA MUELLER 1234')` groceries; `entry(-1350, 'VISA CINESTAR FULDA')` hobbies; `entry(-499, 'Google Payment Ireland Limited')` hobbies; `entry(-2290, 'VISA LIEFERANDO.DE')` takeaway; `entry(-899, 'VISA MCDONALDS 1234')` takeaway; `entry(-150, 'VISA SELECTA DEUTSCHLAND')` takeaway; `entry(-320, 'VISA KIOSK AM BAHNHOF')` takeaway; `entry(-1295, 'VISA ROSEN-APOTHEKE')` health; `entry(-5508, 'Rundfunk ARD, ZDF, DRadio', "Rundfunkbeitrag 07.2026 - 09.2026\nBeitragsnr. 123456789")` other ÷3; `entry(-999, 'VISA MÜLLER 1234')` groceries (umlaut folding) |
| same | `prefers higher priority` | #59 (`AMAZON PRIM`, also contains `AMAZON`) → subscriptions; `entry(-470, 'RhoenEnergie Fulda Baderbetrieb GmbH')` (matches utilities by counterparty and hobbies by merchant) → hobbies, divisor 1 |
| same | `prefers the longer pattern at equal priority` | `new RuleMatcher([...])` with two created rules (`Rule::factory()`): `AMAZON` → online_orders, then `AMAZON MARKETPLACE` → other, both priority 100 → `entry(-1000, 'VISA AMAZON MARKETPLACE* X1')` gets other |
| same | `falls back to the older rule on a full tie` | two factory rules `TEGUT` → groceries (created first) and `TEGUT` → other (field `counterparty`, created second), both priority 100 → `entry(-399, 'VISA TEGUT FILIALE 5020')` gets groceries |
| same | `respects the rule direction` | `entry(-115820, null, 'Miete', 'Dauerauftrag/Terminueberw.')` → rent ÷3; `entry(51540, null, 'Miete', 'Gutschrift/Dauerauftrag')` → ignored; `entry(1999, 'VISA AMAZON* NQ0NU61X4')` (refund) → state `income`, `ruleId` null |
| same | `matches any direction` | factory rule `direction` `RuleDirection::Any`, pattern `FOO`, group other → both `entry(-100, 'FOO GmbH')` and `entry(100, 'FOO GmbH')` get other |
| `tests/Feature/StatementUploadTest.php` (modify: add tests; existing tests unchanged) | `groups entries on the review page` | seed `RuleSeeder`; upload; `get(route('upload.review'))` 200; `substr_count` of `data-assignment="group"` = 64, `data-assignment="ignored"` = 3, `data-assignment="unassigned"` = 1, `data-assignment="income"` = 1; `data-group="rent"` = 1; `÷3` = 3; `No group` = 1; `assertSee('Groceries & Personal Care')` (assertSee escapes it to match the rendered `&amp;`) and `assertSee('ignored')`; `<tr` count still 70 |
| same | `keeps the rule results in the pending import` | seed; upload → `session('statement_import.assignments')` has 69 items; item 1 = `['group_key' => 'rent', 'share_divisor' => 3, 'ignored' => false, 'rule_id' => <id of Miete/out rule>]` |
| same | `stores the rule results on confirm` | seed; upload; confirm → the −115820 transaction: `group_key` rent, `share_divisor` 3, `ignored` false, `rule_id` = Miete/out rule id, `$transaction->rule->pattern` = `Miete`; 3 transactions `ignored` true, all with `group_key` null; the −20000 transaction: `group_key` null, `rule_id` null, `ignored` false; the 134819 transaction: `group_key` null, `ignored` false; `Transaction::whereNotNull('group_key')->count()` = 64; every non-null `rule_id` exists in `rules` |
| same | `stores what was matched at upload time` | seed; upload; `Rule::query()->delete()`; confirm → the −115820 transaction still has `group_key` rent and `share_divisor` 3 |
| same | `imports without rules` | no seeding; upload; review shows `No group` 65 times (`substr_count`) and `data-assignment="income"` 4 times; confirm → every transaction `group_key` null, `ignored` false, `rule_id` null (Issue 2 behaviour kept) |

## Automated verification

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `vendor/bin/sail artisan test --compact` | All tests pass (Issues 1–3), 0 failures |
| 2 | Code style | `vendor/bin/sail bin pint --format agent` | No files changed |
| 3 | Frontend builds | `vendor/bin/sail npm run build` | Exit 0 |
| 4 | Migration ran | `vendor/bin/sail artisan migrate:status` | `2026_09_28_100002_create_rules_collection` → Ran |
| 5 | Seeder idempotent | `vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction` twice, then Boost `database-query-mongodb` count on `rules` (default DB) | `42`, all `source: "seeded"` |
| 6 | `DatabaseSeeder` calls it | `vendor/bin/sail artisan db:seed --no-interaction`, then count `rules` again | still `42` |
| 7 | Fixture grouping via the real DB rules | `vendor/bin/sail artisan tinker --execute '$e = (new App\Services\Statements\IngStatementParser)->parse(base_path("tests/Fixtures/ing-2026-06.pdf"))->entries; $m = App\Services\Rules\RuleMatcher::fromDatabase()->matchAll($e); echo json_encode(array_count_values(array_map(fn ($x, $y) => $x->state($y->direction()), $m, $e)));'` | `{"group":64,"ignored":3,"income":1,"unassigned":1}` (key order may differ) |
| 8 | Index | Boost `database-query-mongodb` `listIndexes` on `rules` | `{source: 1}` present |
| 9 | Dev DB untouched by tests | count `statements`, `transactions`, `rules` in the default DB before and after check 1 | Same counts |
| 10 | No errors logged | Boost `last-error` and `read-log-entries` (last 20) | No new errors or exceptions |

## Manual verification

- [ ] Start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run build`; seed the rules: `vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction`; log in at http://localhost.
- [ ] Upload `tests/Fixtures/ing-2026-06.pdf` → review page. Each row except income shows a second line under the merchant: TEGUT rows show a green-dot "Groceries & Personal Care" chip, AMAZON rows "Online Orders", "AMAZON PRIM" shows "Subscriptions & Internet", STEAM GAMES "Hobbies & Entertainment".
- [ ] `Miete` −1.158,20 € shows a "Rent" chip plus a `÷3` badge; hover the badge → tooltip "Your share: 1/3". Both RhoenEnergie rows show "Electricity & Gas" + `÷3`. The amount column still shows the full bank amounts.
- [ ] The three roommate reimbursements (Rundfunkbeitrag +18,36 €, two Miete +515,40 €) are faded, their amounts struck through, with a grey "ignored" badge with an eye-slash icon.
- [ ] The Echtzeitüberweisung −200,00 € shows a dashed "No group" chip; it is the only such row.
- [ ] The salary (+1.348,19 €) has no chip line.
- [ ] Summary card figures are unchanged (In +2.397,35 €, Out −2.717,07 €).
- [ ] Confirm import → green banner "June 2026 imported · 69 entries".
- [ ] Light mode: chip dots show the group colours, chips/badges readable, faded rows still legible.
- [ ] Dark mode (switch OS theme, reload the review after a new upload): chips use dark backgrounds, dashed border of "No group" visible, no white boxes.
- [ ] Narrow window (~375 px): chips wrap under the merchant, long group names truncate inside the chip, no horizontal scroll, amounts stay on one line.

## Requirement traceability

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| `rules` collection | Step 1 | `seeds every configured rule` | #4, #8 |
| Seeder reading a rules section in `config/expenses.php` | Steps 2, 4 | `seeds every configured rule`, `references only configured groups`, `is idempotent`, `syncs changed and removed config rules`, `keeps manual rules`, `rejects invalid rule config` | #5, #6 |
| Matching service: normalize text (uppercase, no whitespace, umlaut folding) | Step 3 | `normalizes text for matching`, `matches seeded rules without a fixture entry` (MÜLLER row), `matches seeded rules on fixture entries` (LOTTO He ssen, Discover y) | #7 |
| Evaluate by priority, first match wins (`AMAZON PRIM` over `AMAZON`, `Baderbetrieb` over `RhoenEnergie`) | Steps 2, 3 | `prefers higher priority`, `prefers the longer pattern at equal priority`, `falls back to the older rule on a full tie`, `records the matching rule` | #1 |
| Initial seeded rules table (groups 1–10 + ignore rules) | Step 2 | `matches seeded rules on fixture entries`, `matches seeded rules without a fixture entry`, `respects the rule direction` | #5, #7 |
| Review screen: group chip per entry, "÷3" badge, "ignored" badge | Step 6 | `groups entries on the review page`, `imports without rules` | #1, #3; manual: chips, ÷3, ignored, No group, light/dark, narrow |
| Stored with `group_key`, `share_divisor`, `ignored`, `rule_id` | Steps 1, 5 | `keeps the rule results in the pending import`, `stores the rule results on confirm`, `stores what was matched at upload time` | #1; manual: confirm |
| Tests: each seeded rule against fixture entries, priority conflicts, normalization, share/ignore flags persisted | Step 7 | all tests in `RuleMatcherTest`, `TextNormalizerTest`, `stores the rule results on confirm` | #1 |
| Done when: June fixture imports with everything grouped except genuinely unknown entries (the −200 Echtzeitüberweisung) | Steps 1–6 | `groups the June fixture`, `leaves only the unknown transfer unassigned`, `groups entries on the review page` | #7; manual: only one "No group" row |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] `rules` holds exactly the 42 configured rules after any number of `RuleSeeder` runs; manual rules are never touched by it.
- [ ] Uploading `tests/Fixtures/ing-2026-06.pdf` with seeded rules shows 64 grouped, 3 ignored, 1 income and exactly 1 "No group" entry (the −200,00 € Echtzeitüberweisung); confirming stores those results with `group_key`, `share_divisor`, `ignored` and `rule_id`.
