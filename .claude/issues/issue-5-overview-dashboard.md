# Issue 5: Overview dashboard

> Source: `.claude/project-plan.md` › "Issue 5 – Overview dashboard"
> Planned: 2026-09-29
> Depends on: Issue 1 (`.claude/issues/issue-1-foundation.md`: layouts, `overview` route, empty state, `config/expenses.php` groups, `--color-group-*` tokens, Alpine, heroicons, `DatabaseMigrations`), Issue 2 (`.claude/issues/issue-2-statement-upload-parsing.md`: `Statement`/`Transaction` models + factories, `Money`/`Period` helpers, upload flow, `statementUpload()` helper), Issue 3 (`.claude/issues/issue-3-automatic-grouping.md`: `RuleSeeder`, stored `group_key`/`share_divisor`/`ignored`, `app/Enums`), Issue 4 (`.claude/issues/issue-4-manual-review.md`: confirm blocked while outgoing entries are unassigned, `assignOpenEntries()` helper).

## Goal

See where the money went in a month or a year: the Overview page shows Spent / Income / Net tiles, a donut of spending by group, and a per-group comparison with the previous period, for one imported month or a whole year, with arrows to move between periods.

Original "Done when": **June 2026 shows correct totals per group and switching to 2026 aggregates all imported months.**

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run every PHP, Artisan, Composer, Node and test command through Sail: `vendor/bin/sail …`. Host PHP lacks `ext-mongodb`, so host `php artisan` fails. If containers are down, run `vendor/bin/sail up -d` first.
- Issues 1–4 must be fully implemented first. Check: `app/Http/Controllers/StatementUploadController.php` has `assign()` and `always()`, `app/Support/Money.php`, `app/Support/Period.php`, `database/seeders/RuleSeeder.php` and `app/Enums/` exist, `tests/Pest.php` defines `assignOpenEntries()`, and `vendor/bin/sail artisan test --compact` is green. If not, stop and report.
- Load these project skills before writing the matching code: `laravel-best-practices` (controller, services, enum, route), `tailwindcss-development` (Blade views and components), `testing-best-practices` (Pest tests).
- Use Boost `search-docs` for any framework or package API you are unsure about (Laravel 13, laravel-mongodb 5.11, Pest 5, Tailwind v4, Alpine 3). ECharts is not covered by `search-docs`: use the API exactly as sketched in Step 6 (checked against the `echarts@6.1.0` type definitions while planning).
- After editing PHP files run `vendor/bin/sail bin pint --dirty --format agent`.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| PHP (Sail runtime) | 8.4 | `compose.yaml` (`sail-8.4/app`) |
| Laravel | 13.33.0 | `composer.lock` |
| MongoDB driver | `mongodb/laravel-mongodb` 5.11.0; server `mongodb/mongodb-atlas-local:8.0`. `Query\Builder::raw()` without arguments returns the `MongoDB\Collection`; Eloquent `Builder::raw()` would hydrate models, so **do not** use `Transaction::raw()` for aggregations. The query builder does not convert `BackedEnum` values (pass `->value`). | `composer.lock`, `vendor/mongodb/laravel-mongodb/src/Query/Builder.php:1060`, `src/Eloquent/Builder.php:246` |
| Tests | Pest 5.2.1; Feature tests use `DatabaseMigrations`; Unit tests do not boot Laravel | `composer.lock`, Issue 1 plan |
| Frontend | Tailwind v4 (CSS-first `resources/css/app.css`), Alpine ^3.17 (`resources/js/app.js`), Vite ^8 + `laravel-vite-plugin` ^3.1, heroicons via `blade-ui-kit/blade-heroicons` | `package.json`, Issue 1 plan |
| Charts | **new:** `echarts` ^6.1 (latest 6.1.0, Apache-2.0, depends on `zrender` 6.1.0 and `tslib`). Modular entry points `echarts/core`, `echarts/charts` (`PieChart`, `BarChart`), `echarts/components` (`TitleComponent`, `TooltipComponent`, `LegendComponent`), `echarts/renderers` (`SVGRenderer`); `registerTheme()` on core and `chart.setTheme(name)` on instances (new in v6). | `npm view echarts`, package tarball inspected in scratchpad |
| Bundle size | Tree-shaken set above, minified: **~534 kB** (~184 kB gzip), measured with esbuild while planning. Above Vite's default 500 kB warning, so it is loaded as a separate lazy chunk and the warning limit is raised to 600 kB (Step 1). | measured |

### Current state of the codebase

Today the repository is still the MongoDB-switched Laravel skeleton; Issues 1–4 are planned but not yet implemented. This plan assumes their **finished** state as specified in their plan files:

- `routes/web.php`: guest group (`login`, `login.store`); auth group `Route::middleware(['auth', DiscardPendingStatementImport::class])` with `logout`, `home` (`/` → redirect to `overview`), `Route::view('/overview', 'pages.overview')->name('overview')`, `Route::view('/trends', 'pages.trends')->name('trends')`, and the seven `upload*` routes on `StatementUploadController`. **The `overview` line is replaced in this issue.**
- `resources/views/pages/overview.blade.php` (Issue 1): `<x-layouts.app title="Overview">` + `<x-empty-state icon="heroicon-o-chart-pie" title="No data yet" text="Upload your first bank statement to see where your money goes.">` with an "Upload statement" primary-button link to `route('upload')`. **Rewritten; the empty state stays for the no-data case.**
- `resources/views/components/layouts/app.blade.php` (Issue 1): top bar, `<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">`. Overview nav link active via `request()->routeIs('overview')` (still true with query strings). Unchanged.
- `resources/views/components/empty-state.blade.php` (Issue 1). Unchanged.
- Card style used everywhere: `rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900`. Muted text `text-slate-500 dark:text-slate-400`. Out/negative `text-rose-600 dark:text-rose-400`, in/positive `text-emerald-600 dark:text-emerald-400`.
- `config/expenses.php`: `groups` (10 keys in sort order `rent`, `utilities`, `groceries`, `subscriptions`, `hobbies`, `online_orders`, `takeaway`, `restaurants`, `health`, `other`, each `name`, `color` `#rrggbb`, `sort`) and `rules` (Issue 3). Unchanged.
- `resources/css/app.css`: `@theme static` with `--color-group-<key, _→->` tokens equal to the config colours (a test enforces it), `@layer base` with `color-scheme` and `[x-cloak]`. Unchanged.
- `resources/js/app.js` (Issue 4): imports Alpine and `./review-queue`, `Alpine.data('reviewQueue', reviewQueue)`, `Alpine.start()`. **Extended.**
- `vite.config.js`: `laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true, fonts: [bunny('Instrument Sans', …)] })`, `tailwindcss()`, `server.watch.ignored`. **Gets a `build` key.**
- `app/Models/Statement.php`: `number`, `period` (`YYYY-MM`), `statement_date`, balances, `confirmed_at`; `transactions()`. Statements only exist after confirm (`confirmed_at` set). `StatementFactory` defaults: `number 6`, `period '2026-06'`, `confirmed_at now()`.
- `app/Models/Transaction.php`: `statement_id, period, booked_on, value_on, type, counterparty, purpose, merchant, amount_cents (signed int), direction ('in'|'out'), group_key (nullable), share_divisor (int, default 1), ignored (bool, default false), rule_id`; `statement()` relation. `TransactionFactory` defaults: new statement, `period '2026-06'`, `merchant 'TEGUT'`, `amount_cents -399`, `direction 'out'`. The `transactions` collection has an index on `period` (Issue 2).
- `app/Support/Money.php`: `Money::format(int $cents, bool $signed = false)` → `1.158,20 €`, `−0,05 €` (U+2212), `+1.348,19 €` when signed and positive. `app/Support/Period.php`: `Period::label('2026-06')` → `June 2026`, `Period::short()` → `Jun 2026`. Both unchanged.
- `app/Enums/` (Issue 3): `RuleField`, `RuleDirection`, `RuleSource`. A new enum is added here.
- `tests/Pest.php`: `DatabaseMigrations` for Feature; helpers `fixturePath()`, `statementUpload()`, `blankPdf()`, `fixtureStatement()`, `entry()`, `assignOpenEntries()`. **Extended.**
- `tests/Feature/PagesTest.php` (Issue 1): `renders each app page for the user` expects "No data yet" on `overview` with an empty DB, and `links the empty states to upload` expects "Upload statement" there. Both must keep passing unchanged.
- No `app/Services/Reports`, no `app/Http/Controllers/OverviewController.php`, no chart JS, no `echarts` package.

### Fixture facts (from Issues 3/4, used by tests)

Importing `tests/Fixtures/ing-2026-06.pdf` with seeded rules and assigning the one open entry (−200,00 € Echtzeitüberweisung) to `other` stores 69 transactions for `2026-06`:

- Raw outgoing sums per group (cents): rent −115820 (1 entry, ÷3), utilities −38800 (2 entries −19700 and −19100, both ÷3), groceries −29349, subscriptions −7145, hobbies −7420, online_orders −15835, takeaway −6118, restaurants −9210, health −15065, other −6945 − 20000 = −26945.
- Ignored: 3 incoming entries (+18,36, +515,40, +515,40). Income (not ignored): only the salary +134819.

Counted amounts (per-entry rounding, Decision 1), positive cents:

| Group | Counted | Shown |
|---|---|---|
| rent | 38607 (115820 / 3 = 38606.67) | 386,07 € |
| utilities | 12934 (6567 + 6367) | 129,34 € |
| groceries | 29349 | 293,49 € |
| subscriptions | 7145 | 71,45 € |
| hobbies | 7420 | 74,20 € |
| online_orders | 15835 | 158,35 € |
| takeaway | 6118 | 61,18 € |
| restaurants | 9210 | 92,10 € |
| health | 15065 | 150,65 € |
| other | 26945 | 269,45 € |
| **Spent** | **168628** | **1.686,28 €** |
| **Income** | **134819** | **1.348,19 €** |
| **Net** | **−33809** | **−338,09 €** |

### Domain rules that apply

- Counted amount of an entry = `amount_cents / share_divisor`, **only for non-ignored entries**. Ignored entries are excluded from every total.
- Spent = counted outgoing entries (shown as a positive amount). Income = counted incoming, non-ignored entries (salary, refunds). Net = Income − Spent. Income is not grouped.
- Month attribution = statement month: use the stored `period` field, never `booked_on`.
- Groups are fixed in `config/expenses.php` (key, name, colour, sort). Group colours in charts come from that config (identical to the `--color-group-*` CSS tokens).
- Money is integer cents; display in German format via `Money::format`.
- Entries are final after confirm; this issue only reads data.
- Charts: Apache ECharts via npm, only the needed modules, shared theme for group colours and dark mode. Theme follows the OS (`prefers-color-scheme`). UI language English.

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | Rounding of ÷n shares | **Per entry.** Each counted amount is rounded to whole cents (MongoDB `$round`, place 0) before summing, so group amounts, the donut total and Spent always add up exactly. June utilities = 65,67 + 63,67 = 129,34 €. |
| 2 | What the ‹ › arrows step through | **Imported periods only.** Month mode: the nearest earlier/later month that has a confirmed statement (gaps are skipped). Year mode: the nearest earlier/later year with at least one imported month. Arrow disabled when there is none. |
| 3 | Comparison baseline | **Previous calendar period**: June 2026 → May 2026; 2026 → 2025. If that period has no imported statement: amounts are shown without deltas and one line **"No data for May 2026 to compare"** (year: "No data for 2025 to compare"). A group at 0 in the previous period and > 0 now shows **"new"**. |
| 4 | Layout | Toggle + arrows row; then a row of 3 KPI tiles; then one card with the donut left and the per-group list right (stacked below `lg`). The list acts as the legend; the donut has no ECharts legend. |
| 5 | Navigation mechanism | **Plain links, full page load.** URLs `/overview?month=YYYY-MM` and `/overview?year=YYYY`; server-rendered; bookmarkable; back/forward work. |
| 6 | KPI formatting | Spent and Income unsigned in normal text colour (`1.686,28 €`, `1.348,19 €`). Net signed via `Money::format($net, true)`: rose when negative (`−338,09 €`), emerald when positive (`+…`), neutral at `0,00 €`. |
| 7 | ECharts renderer | **SVG** (`SVGRenderer`). |
| 8 | Groups with 0 € in the current period | Hidden when 0 in both periods. A group at 0 now but > 0 in the previous period shows `0,00 €` with an emerald `↓ 100 %`. Without previous data only groups > 0 now are listed. |
| 9 | Invalid or unknown query (settled here) | `month` not matching `YYYY-MM` or not imported, or `year` not matching `YYYY` or without imported months → `302` to `/overview` (latest month). No flash message. If both are given, `month` wins. With no statements at all, query parameters are ignored and the empty state renders. |
| 10 | Month/Year toggle targets (settled here) | Month → Year: the year of the current month. Year → Month: the **latest imported month of that year**. The active mode is not a link. |
| 11 | Ordering (settled here) | List rows and donut slices: current amount descending, then previous amount descending, then config `sort`. |
| 12 | Percent formats (settled here) | Share of Spent in list and tooltip: one decimal, German (`22,9 %`). Delta: whole percent of the absolute change, `(int) round(abs(cur − prev) / prev × 100)`: up `↑ 17 %` rose, down `↓ 32 %` emerald, equal cents `±0 %` slate, previous 0 → `new` rose. |
| 13 | Outgoing transactions without a group (settled here) | Cannot be created since Issue 4, but if present they count into Spent and appear as a group **"Unassigned"**, colour `#cbd5e1` (slate-300), key `unassigned`, sorted after the config groups on ties. |
| 14 | Donut centre (settled here) | ECharts `title`: text = Spent (`1.686,28 €`), subtext `Spent`, centred. Tooltip: `<group name>` line, then `<amount> · <share %>`. |
| 15 | Period with data but no spending (settled here) | The donut is replaced by the text **"No spending in June 2026"** (period label); the list still shows drop rows (Decision 8), if any. |
| 16 | Chart JS loading (settled here) | ECharts is loaded with a dynamic `import()` from the Alpine component, so it is a separate chunk fetched only on the Overview page; `build.chunkSizeWarningLimit` = 600 (kB). |

## Scope

### In scope

- Install `echarts` ^6.1; configured core module (pie, bar, title, tooltip, legend, SVG renderer) with a shared light/dark theme that follows the OS; Alpine `donutChart` component that resizes with its container.
- `ReportMode` enum, `ReportPeriod` (period parsing, labels, ranges, navigation), `Totals` value object, `SpendingReport` service (MongoDB aggregation pipeline), `GroupComparison` rows, `Percent` formatter.
- `OverviewController` replacing the `Route::view` for `/overview`.
- Overview page: Month|Year toggle + arrows (`<x-period-switcher>`), KPI tiles (`<x-kpi-tile>`), donut, per-group comparison list, empty states.
- Unit and feature tests: aggregation correctness (shares, ignored, income, June fixture, year), period navigation bounds, empty state, comparison.

### Out of scope

- Trends page, stacked bar chart, clickable legend, series per period (Issue 6). The Trends page keeps its Issue 1 empty state. `BarChart` and `LegendComponent` are only **registered** (the issue lists them in the module set); nothing renders them yet.
- Deltas on the KPI tiles (only per-group comparison is asked for).
- Drill-down into entries of a group, entry lists, editing/deleting entries or statements, re-running rules, budgets, income grouping, export (project "Out of scope").
- A manual light/dark toggle (theme follows the OS).
- JSON endpoints / client-side period switching (Decision 5).
- Caching of aggregation results.

## Prerequisites

Containers running:

```sh
vendor/bin/sail up -d
```

| Package | Constraint | Manager |
|---|---|---|
| `echarts` | `^6.1` (runtime `dependencies`) | npm |

No Composer packages, no migrations, no new `.env` keys.

## Implementation steps

### Step 1: Install ECharts and raise the chunk warning limit

**Files**
- Modify: `package.json`, `package-lock.json` (via npm)
- Modify: `vite.config.js` — add a `build` key

**Details**

In `vite.config.js`, add next to `plugins` and `server`:

```js
build: {
    // The lazily loaded ECharts chunk (pie, bar, SVG renderer) is ~534 kB minified.
    chunkSizeWarningLimit: 600,
},
```

**Commands**

```sh
vendor/bin/sail npm install echarts@^6.1
vendor/bin/sail npm run build
```

**Step check:** `vendor/bin/sail npm ls echarts` shows `echarts@6.1.x`; `package.json` lists it under `dependencies`; build exits 0.

### Step 2: Period model and formatting helper

**Files**
- New: `app/Enums/ReportMode.php` (`vendor/bin/sail artisan make:enum ReportMode --string --no-interaction`; check `make:enum --help` for the exact option)
- New: `app/Services/Reports/ReportPeriod.php`
- New: `app/Support/Percent.php`

These classes must not use the container, facades or app helpers (`config()`, `route()`, `now()`): they are unit-tested without Laravel. `App\Support\Period` (Carbon only) may be used.

**`App\Enums\ReportMode`**: `Month = 'month'`, `Year = 'year'`.

**`App\Services\Reports\ReportPeriod`** — `final readonly class`:

```php
private function __construct(public ReportMode $mode, public string $value) {}

public static function month(string $period): self;   // '2026-06'
public static function year(string $year): self;      // '2026'

/**
 * @param  list<string>  $imported  imported months (YYYY-MM), ascending, unique, non-empty
 */
public static function resolve(mixed $month, mixed $year, array $imported): ?self;

public function from(): string;    // month: value; year: "{$value}-01"
public function to(): string;      // month: value; year: "{$value}-12"
public function label(): string;   // month: Period::label($value) ("June 2026"); year: $value ("2026")
public function previous(): self;  // calendar: 2026-01 → 2025-12; 2026 → 2025
public function hasData(array $imported): bool;       // month: in list; year: any "YYYY-" prefix
public function earlier(array $imported): ?self;      // Decision 2
public function later(array $imported): ?self;        // Decision 2
public function toggled(array $imported): self;       // Decision 10
/** @return array{month: string}|array{year: string} */
public function query(): array;                        // for route('overview', $period->query())
public function isMonth(): bool;
```

- `resolve()`: if `$month !== null`: return `self::month($month)` only when it is a string matching `/^\d{4}-(0[1-9]|1[0-2])$/` **and** is in `$imported`, else `null`. Else if `$year !== null`: return `self::year($year)` only when it is a string matching `/^\d{4}$/` and `hasData()`, else `null`. Else return `self::month(last($imported))` (use `$imported[array_key_last($imported)]`).
- `previous()` for months: compute with integers (`$y`, `$m`; `$m === 1` → `($y - 1)-12`), zero-padded `sprintf('%04d-%02d', …)`.
- `earlier()`: month mode → the largest imported month `< value` (string comparison); year mode → the largest year `< value` among `substr($month, 0, 4)` of imported months. `null` if none. `later()` mirrors it with the smallest `>`.
- `toggled()`: month → `self::year(substr($value, 0, 4))`; year → `self::month(<largest imported month starting with "{$value}-">)`.
- `query()`: `['month' => $value]` or `['year' => $value]`.

**`App\Support\Percent`** — `final class`, `public static function format(float $value, int $decimals = 1): string` → `number_format($value, $decimals, ',', '.').' %'`. Examples: `format(22.94)` → `22,9 %`; `format(17, 0)` → `17 %`; `format(100, 0)` → `100 %`.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `tests/Unit/ReportPeriodTest.php` and `tests/Unit/PercentTest.php` (Step 8).

### Step 3: Totals value object and aggregation service

**Files**
- New: `app/Services/Reports/Totals.php`
- New: `app/Services/Reports/SpendingReport.php`

**`Totals`** — `final readonly class` (no container use):

```php
/**
 * @param  array<string, int>  $groupCents  group key (or 'unassigned') => counted spending, positive cents, only non-zero
 */
public function __construct(public int $spentCents, public int $incomeCents, public array $groupCents) {}

public static function empty(): self;                 // (0, 0, [])
/** @param  iterable<Totals>  $totals */
public static function sum(iterable $totals): self;   // adds spent, income and each group key
public function netCents(): int;                       // incomeCents - spentCents
```

**`SpendingReport`** — `final class`, resolved from the container (no constructor arguments):

```php
/** @return array<string, Totals> period (YYYY-MM) => totals; ascending; only periods that have counted transactions */
public function totalsByPeriod(string $fromPeriod, string $toPeriod): array;

public function totals(string $fromPeriod, string $toPeriod): Totals;   // Totals::sum(totalsByPeriod(...)) or Totals::empty()
```

`totalsByPeriod()` runs one aggregation on the raw collection:

```php
$collection = Transaction::query()->toBase()->raw();   // MongoDB\Collection

$cursor = $collection->aggregate([
    ['$match' => [
        'period' => ['$gte' => $fromPeriod, '$lte' => $toPeriod],
        'ignored' => ['$ne' => true],
    ]],
    ['$group' => [
        '_id' => ['period' => '$period', 'direction' => '$direction', 'group_key' => '$group_key'],
        'cents' => ['$sum' => ['$round' => [
            ['$divide' => ['$amount_cents', ['$ifNull' => ['$share_divisor', 1]]]],
            0,
        ]]],
    ]],
    ['$sort' => ['_id.period' => 1]],
], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
```

Then fold the rows in PHP, per period: `$cents = (int) round($row['cents'])` (the pipeline returns doubles); `direction === 'out'` → `spent += -$cents` and `groupCents[$row['_id']['group_key'] ?? 'unassigned'] += -$cents`; `direction === 'in'` → `income += $cents` (group ignored). Drop group entries that end at 0. Periods come back as keys in ascending order.

Add a PHPDoc on the class explaining per-entry rounding (Decision 1). Do not filter by `statement_id`; every stored transaction belongs to a confirmed statement.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `tests/Feature/Reports/SpendingReportTest.php` (Step 8).

### Step 4: Group comparison rows

**Files**
- New: `app/Services/Reports/GroupComparison.php`

`final readonly class` (no container use; the groups config is passed in):

```php
public const UNASSIGNED = 'unassigned';

public function __construct(
    public string $key,
    public string $name,
    public string $color,
    public int $cents,
    public ?int $previousCents,   // null = no previous data
    public float $share,          // % of spent, 0 when spent is 0
    public ?string $trend,        // 'up' | 'down' | 'same' | 'new' | null
    public ?int $deltaPercent,    // absolute, rounded; null for 'new' / no previous data
) {}

/**
 * @param  array<string, array{name: string, color: string, sort: int}>  $groups  config('expenses.groups')
 * @return list<self>
 */
public static function rows(Totals $current, ?Totals $previous, array $groups): array;
```

`rows()`:

- Known groups: every key of `$groups` plus `self::UNASSIGNED` (name `Unassigned`, color `#cbd5e1`, sort `PHP_INT_MAX`).
- `cents = $current->groupCents[$key] ?? 0`; `previousCents = $previous === null ? null : ($previous->groupCents[$key] ?? 0)`.
- Include a row when `cents > 0`, or when `previousCents > 0` (Decision 8).
- `share = $current->spentCents > 0 ? $cents / $current->spentCents * 100 : 0.0`.
- Trend: `previousCents === null` → `null`/`null`; `previousCents === 0` → `'new'`/`null`; `cents > previousCents` → `'up'`; `<` → `'down'`; equal → `'same'`; `deltaPercent = (int) round(abs($cents - $previousCents) / $previousCents * 100)` for up/down/same.
- Sort: `cents` desc, then `previousCents ?? 0` desc, then `sort` asc (Decision 11).

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `tests/Unit/GroupComparisonTest.php` (Step 8).

### Step 5: Controller and route

**Files**
- New: `app/Http/Controllers/OverviewController.php` (`vendor/bin/sail artisan make:controller OverviewController --invokable --no-interaction`)
- Modify: `routes/web.php` — replace `Route::view('/overview', 'pages.overview')->name('overview');` with `Route::get('/overview', OverviewController::class)->name('overview');` (same place in the auth group)

**`OverviewController::__invoke(Request $request, SpendingReport $report): View|RedirectResponse`**:

1. `$imported = Statement::query()->whereNotNull('confirmed_at')->pluck('period')->unique()->sort()->values()->all();`
2. `$imported === []` → `return view('pages.overview', ['period' => null]);` (empty state; query parameters ignored).
3. `$period = ReportPeriod::resolve($request->query('month'), $request->query('year'), $imported);` `null` → `return redirect()->route('overview');`
4. `$previous = $period->previous();` `$hasPrevious = $previous->hasData($imported);`
5. `$totals = $report->totals($period->from(), $period->to());` `$previousTotals = $hasPrevious ? $report->totals($previous->from(), $previous->to()) : null;`
6. `$rows = GroupComparison::rows($totals, $previousTotals, config('expenses.groups'));`
7. `$chart = ['total' => Money::format($totals->spentCents), 'label' => 'Spent', 'slices' => …]` where `slices` = rows with `cents > 0`, in row order, each `['name' => $row->name, 'value' => $row->cents, 'color' => $row->color, 'amount' => Money::format($row->cents)]`. (Key `amount`, not `label`: `label` is a reserved ECharts data-item option.)
8. Return `view('pages.overview', compact('period', 'previous', 'hasPrevious', 'totals', 'rows', 'chart') + ['earlier' => $period->earlier($imported), 'later' => $period->later($imported), 'toggled' => $period->toggled($imported)])`.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan route:list --name=overview
```

**Step check:** `route:list --name=overview` shows `GET|HEAD overview … OverviewController` with `auth` and `DiscardPendingStatementImport`. (The page renders after Step 7.)

### Step 6: ECharts setup, theme and Alpine donut component

**Files**
- New: `resources/js/charts/theme.js` — theme objects and OS theme helpers (small; part of the main bundle)
- New: `resources/js/charts/echarts.js` — configured ECharts core (lazy chunk)
- New: `resources/js/donut-chart.js` — Alpine component
- Modify: `resources/js/app.js` — register the component

**`resources/js/charts/theme.js`**

```js
const font = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

const theme = ({ text, muted, surface, border, legend }) => ({
    backgroundColor: 'transparent',
    textStyle: { fontFamily: font, color: text },
    title: { textStyle: { color: text }, subtextStyle: { color: muted } },
    tooltip: { backgroundColor: surface, borderColor: border, textStyle: { color: text, fontFamily: font } },
    legend: { textStyle: { color: legend } },
    pie: { itemStyle: { borderColor: surface } },
});

export const themes = {
    'expenses-light': theme({ text: '#0f172a', muted: '#64748b', surface: '#ffffff', border: '#e2e8f0', legend: '#475569' }),
    'expenses-dark': theme({ text: '#f1f5f9', muted: '#94a3b8', surface: '#0f172a', border: '#1e293b', legend: '#cbd5e1' }),
};

const query = () => window.matchMedia('(prefers-color-scheme: dark)');

export const currentTheme = () => (query().matches ? 'expenses-dark' : 'expenses-light');

/** Calls callback(themeName) whenever the OS theme changes; returns an unsubscribe function. */
export function watchTheme(callback) {
    const media = query();
    const listener = () => callback(currentTheme());
    media.addEventListener('change', listener);
    return () => media.removeEventListener('change', listener);
}
```

(Colours are the Issue 1 slate palette: slate-900/100 text, slate-500/400 muted, white / slate-900 card surface, slate-200/800 borders. Group colours come per slice from `config/expenses.php`.)

**`resources/js/charts/echarts.js`**

```js
import * as echarts from 'echarts/core';
import { BarChart, PieChart } from 'echarts/charts';
import { LegendComponent, TitleComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';
import { themes } from './theme';

echarts.use([PieChart, BarChart, TitleComponent, TooltipComponent, LegendComponent, SVGRenderer]);

Object.entries(themes).forEach(([name, theme]) => echarts.registerTheme(name, theme));

export { echarts };
```

Never import from `'echarts'` (the full bundle).

**`resources/js/donut-chart.js`**

```js
import { currentTheme, watchTheme } from './charts/theme';

const percent = (value) =>
    `${value.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

export default () => ({
    chart: null,
    cleanup: [],

    async init() {
        const { echarts } = await import('./charts/echarts');
        const data = JSON.parse(this.$el.dataset.chart);

        this.chart = echarts.init(this.$refs.chart, currentTheme(), { renderer: 'svg' });
        this.chart.setOption({
            title: {
                text: data.total,
                subtext: data.label,
                left: 'center',
                top: 'center',
                itemGap: 4,
                textStyle: { fontSize: 20, fontWeight: 600 },
                subtextStyle: { fontSize: 12 },
            },
            tooltip: {
                trigger: 'item',
                formatter: (p) => `${p.marker}${p.name}<br>${p.data.amount} · ${percent(p.percent)}`,
            },
            series: [{
                type: 'pie',
                radius: ['62%', '85%'],
                label: { show: false },
                labelLine: { show: false },
                itemStyle: { borderWidth: 2, borderRadius: 4 },
                emphasis: { scale: true, scaleSize: 4, label: { show: false } },
                data: data.slices.map((s) => ({ name: s.name, value: s.value, amount: s.amount, itemStyle: { color: s.color } })),
            }],
        });

        const observer = new ResizeObserver(() => this.chart?.resize());
        observer.observe(this.$refs.chart);
        this.cleanup = [() => observer.disconnect(), watchTheme((name) => this.chart?.setTheme(name))];
    },

    destroy() {
        this.cleanup.forEach((fn) => fn());
        this.chart?.dispose();
        this.chart = null;
    },
});
```

`p.name` comes from config group names; ECharts' default tooltip renders HTML, and names contain `&` — acceptable because the names are fixed in code, not user input.

**`resources/js/app.js`** becomes:

```js
import Alpine from 'alpinejs';
import donutChart from './donut-chart';
import reviewQueue from './review-queue';

window.Alpine = Alpine;
Alpine.data('reviewQueue', reviewQueue);
Alpine.data('donutChart', donutChart);
Alpine.start();
```

**Commands**

```sh
vendor/bin/sail npm run build
```

**Step check:** build exits 0 with no "chunks are larger than" warning; `ls public/build/assets/ | grep -E '^echarts-.*\.js$'` lists exactly one file; `wc -c public/build/assets/app-*.js` is below 150000 bytes (ECharts is not in the main bundle).

### Step 7: Blade components and Overview page

**Files**
- New: `resources/views/components/period-switcher.blade.php`
- New: `resources/views/components/kpi-tile.blade.php`
- Modify (rewrite): `resources/views/pages/overview.blade.php`

**`<x-period-switcher>`** props: `period` (`ReportPeriod`), `earlier` (`?ReportPeriod`), `later` (`?ReportPeriod`), `toggled` (`ReportPeriod`), `route` (route name, default `'overview'`). Markup:

```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900" role="group" aria-label="Period type">
        {{-- one segment per mode: Month, Year --}}
    </div>
    <div class="flex items-center gap-1">
        {{-- prev arrow, <h1>, next arrow --}}
    </div>
</div>
```

- Segments, in this order: "Month" (`data-mode="month"`), "Year" (`data-mode="year"`). Base classes `rounded-md px-3 py-1.5 text-sm font-medium`. The active segment (`$period->mode`) is a `<span aria-current="true" class="… bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400">`. The other is `<a href="{{ route($route, $toggled->query()) }}" class="… text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400">`.
- Arrows: `data-nav="prev"` with `<x-heroicon-o-chevron-left class="size-5" />`, `data-nav="next"` with `<x-heroicon-o-chevron-right class="size-5" />`. Enabled: `<a href="{{ route($route, $earlier->query()) }}" aria-label="Previous: {{ $earlier->label() }}" title="{{ $earlier->label() }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400">` (next: "Next: …", `$later`). Disabled: `<span data-nav="prev" aria-disabled="true" class="rounded-lg p-2 text-slate-300 dark:text-slate-700">` with the same icon.
- Title: `<h1 class="min-w-36 text-center text-lg font-semibold tabular-nums">{{ $period->label() }}</h1>`.

**`<x-kpi-tile>`** props: `label`, `icon` (component name), `value` (string), `tone` (`'neutral'|'positive'|'negative'`, default `'neutral'`). Renders `<div data-kpi="{{ Str::lower($label) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">` with a row `flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400` (`<x-dynamic-component :component="$icon" class="size-5" />` + label) and `<p class="mt-2 text-2xl font-semibold tabular-nums">` holding `$value`, coloured by tone: neutral none, positive `text-emerald-600 dark:text-emerald-400`, negative `text-rose-600 dark:text-rose-400`.

**`pages/overview.blade.php`** → `<x-layouts.app title="Overview">`, `@use('App\Support\Money')`, `@use('App\Support\Percent')`:

- `@if ($period === null)`: exactly the Issue 1 empty state (same icon, title "No data yet", text, "Upload statement" action). Nothing else.
- `@else` `<div class="space-y-6">`:
  1. `<x-period-switcher :period="$period" :earlier="$earlier" :later="$later" :toggled="$toggled" />`.
  2. `<div class="grid gap-4 sm:grid-cols-3">` with three tiles:
     - `label="Spent"`, `icon="heroicon-o-arrow-up-right"`, `value` = `Money::format($totals->spentCents)`.
     - `label="Income"`, `icon="heroicon-o-arrow-down-left"`, `value` = `Money::format($totals->incomeCents)`.
     - `label="Net"`, `icon="heroicon-o-scale"`, `value` = `Money::format($totals->netCents(), true)`, `tone` = `positive` if net > 0, `negative` if < 0, else `neutral`.
  3. Card `<section aria-labelledby="groups-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">`:
     - Header `flex items-baseline justify-between gap-3`: `<h2 id="groups-heading" class="text-sm font-semibold">By group</h2>`; when `$hasPrevious`: `<p class="text-xs text-slate-500 dark:text-slate-400">vs {{ $previous->label() }}</p>`.
     - Body `mt-4 grid items-center gap-6 lg:grid-cols-[18rem_1fr]`:
       - Left: if `$totals->spentCents > 0`: `<div x-data="donutChart" data-chart="@json($chart)" class="mx-auto w-full max-w-72"><div x-ref="chart" role="img" aria-label="Spending by group, total {{ $chart['total'] }}" class="aspect-square w-full"></div></div>`. Otherwise `<p data-no-spending class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No spending in {{ $period->label() }}</p>`.
       - Right: `<ul class="divide-y divide-slate-100 dark:divide-slate-800">`, one `<li data-group-row="{{ $row->key }}" @if ($row->trend) data-trend="{{ $row->trend }}" @endif class="flex items-center gap-3 py-2.5">` per row: dot `<span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $row->color }}"></span>`; name `<span class="min-w-0 flex-1 truncate text-sm font-medium" title="{{ $row->name }}">`; share `<span class="hidden text-xs tabular-nums text-slate-500 sm:inline dark:text-slate-400">{{ Percent::format($row->share) }}</span>`; amount `<span class="whitespace-nowrap text-sm font-semibold tabular-nums">{{ Money::format($row->cents) }}</span>`; delta `<span data-delta class="w-16 shrink-0 text-right text-xs font-semibold tabular-nums …">` only when `$row->trend !== null`: `up` → `↑ {{ Percent::format($row->deltaPercent, 0) }}` rose; `down` → `↓ …` emerald; `same` → `±0 %` slate-500/400; `new` → `new` rose.
       - When `! $hasPrevious`, below the list: `<p data-no-comparison class="mt-3 text-xs text-slate-500 dark:text-slate-400">No data for {{ $previous->label() }} to compare</p>`.
       - If `$rows` is empty (possible only when nothing was spent in either period), render no `<ul>`.

`@json` escapes quotes as `"`, so the attribute value stays valid inside `data-chart="…"`; `JSON.parse` in the component decodes it.

**Commands**

```sh
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** `vendor/bin/sail artisan test --compact --filter=PagesTest` passes (empty state unchanged).

### Step 8: Tests

**Files**
- New: `tests/Unit/ReportPeriodTest.php`, `tests/Unit/PercentTest.php`, `tests/Unit/GroupComparisonTest.php` (`vendor/bin/sail artisan make:test --pest --unit <Name> --no-interaction`)
- New: `tests/Feature/Reports/SpendingReportTest.php`, `tests/Feature/OverviewTest.php` (`vendor/bin/sail artisan make:test --pest <Name> --no-interaction`)
- Modify: `tests/Pest.php` — add the helper below

```php
/** Imports the June fixture through the real upload flow (seeded rules, open entry → other). Call after actingAs(). */
function importFixtureStatement(): void
{
    test()->seed(\Database\Seeders\RuleSeeder::class);
    test()->post(route('upload.store'), ['statement' => statementUpload()])->assertRedirect(route('upload.review'));
    assignOpenEntries('other');
    test()->post(route('upload.confirm'))->assertRedirect(route('upload'));
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

Unit tests do not boot Laravel. Feature tests use `DatabaseMigrations`, `User::factory()->create()` + `actingAs`, `Statement::factory()` / `Transaction::factory()` (pass `period`, `amount_cents`, `direction`, `group_key`, `share_divisor`, `ignored` explicitly; create transactions `->for($statement)` so `statement_id` matches). "June fixture" = `importFixtureStatement()`. Money strings contain U+2212 for minus (`−`).

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `tests/Unit/ReportPeriodTest.php` (new) | `resolves the latest month by default` | `resolve(null, null, ['2026-03', '2026-06'])` → mode `Month`, value `2026-06` |
| same | `resolves a requested month or year` | `resolve('2026-03', null, …)` → month `2026-03`; `resolve(null, '2026', …)` → year `2026`; `resolve('2026-03', '2025', …)` → month `2026-03` (month wins) |
| same | `rejects invalid or unknown periods` (dataset) | returns `null` for month `2026-13`, `2026-6`, `2026-06-01`, `2025-06` (not imported), an array `['x']`; year `26`, `abcd`, `2024` (no data) |
| same | `computes ranges and labels` | month `2026-06`: from/to `2026-06`, label `June 2026`; year `2026`: from `2026-01`, to `2026-12`, label `2026` |
| same | `finds the previous calendar period` | `2026-06` → `2026-05`; `2026-01` → `2025-12`; year `2026` → `2025` |
| same | `steps through imported months only` | imported `['2025-11', '2026-03', '2026-06']`: `2026-06` earlier `2026-03`, later `null`; `2026-03` earlier `2025-11`, later `2026-06`; `2025-11` earlier `null` |
| same | `steps through imported years only` | same list: year `2026` earlier `2025`, later `null`; year `2025` earlier `null`, later `2026` |
| same | `toggles between month and year` | month `2026-03` → year `2026`; year `2026` → month `2026-06` (latest of that year); year `2025` → month `2025-11` |
| same | `builds the query` | month → `['month' => '2026-06']`; year → `['year' => '2026']` |
| same | `knows whether a period has data` | month `2026-05` false, `2026-06` true; year `2024` false, `2025` true |
| `tests/Unit/PercentTest.php` (new) | `formats percentages the German way` (dataset) | `format(22.94)` → `22,9 %`; `format(17, 0)` → `17 %`; `format(100, 0)` → `100 %`; `format(0)` → `0,0 %` |
| `tests/Unit/GroupComparisonTest.php` (new) | `compares groups with the previous period` | groups = real config shape (inline array of 4 groups); current `groceries 12000, takeaway 8000, health 3000` (spent 23000); previous `groceries 10000, takeaway 10000, hobbies 5000` → keys in order `groceries, takeaway, health, hobbies`; groceries `up` 20; takeaway `down` 20; health `new` delta null; hobbies cents 0, `down` 100 |
| same | `marks unchanged groups` | current and previous `rent 38607` → trend `same`, delta 0 |
| same | `omits deltas without previous data` | previous `null` → only groups > 0 now, all `previousCents` and `trend` null |
| same | `hides groups without spending in both periods` | a config group absent from both totals is not in the rows |
| same | `computes the share of spent` | current `groceries 7500, takeaway 2500` → shares 75.0 and 25.0 |
| same | `orders by amount then previous amount then config order` | current `a 100, b 100, c 0`; previous `a 50, b 80, c 90` → order `b, a, c`; with equal previous → config order |
| same | `lists unassigned spending` | current `groupCents ['unassigned' => 500]` → row key `unassigned`, name `Unassigned`, color `#cbd5e1` |
| `tests/Feature/Reports/SpendingReportTest.php` (new) | `aggregates the June fixture` | June fixture; `totals('2026-06', '2026-06')`: `spentCents` 168628, `incomeCents` 134819, `netCents()` −33809; `groupCents` equals exactly `['rent' => 38607, 'utilities' => 12934, 'groceries' => 29349, 'subscriptions' => 7145, 'hobbies' => 7420, 'online_orders' => 15835, 'takeaway' => 6118, 'restaurants' => 9210, 'health' => 15065, 'other' => 26945]` (compare with `toEqualCanonicalizing` or sort keys) |
| same | `divides shared costs by their share` | one out transaction −115820 `rent` ÷3 → `groupCents['rent']` 38607, spent 38607 |
| same | `rounds each shared entry before summing` | two out transactions −100 `utilities` ÷3 each → `groupCents['utilities']` 66 (not 67) |
| same | `excludes ignored entries` | out −5000 `other` `ignored` true and in +51540 `ignored` true → `Totals` equals empty (spent 0, income 0, no groups) |
| same | `counts income without grouping it` | in +134819 (group null) and in +1999 (refund) → income 136818, spent 0, `groupCents` `[]` |
| same | `aggregates a year across months` | June fixture + July statement (`number` 7, `period` `2026-07`) with out −1000 `groceries`, out −30000 `rent` ÷3, in +5000, plus a statement `number` 12 / `period` `2025-12` with out −9999 `groceries` → `totals('2026-01', '2026-12')`: spent 179628, income 139819, groceries 30349, rent 48607; `totals('2026-07', '2026-07')` spent 11000 |
| same | `returns totals per period` | June fixture + July as above → `totalsByPeriod('2026-01', '2026-12')` has keys `['2026-06', '2026-07']` in that order; June spent 168628, July spent 11000 |
| same | `counts outgoing entries without group as unassigned` | out −700 with `group_key` null → `groupCents` `['unassigned' => 700]`, spent 700 |
| same | `returns empty totals when nothing matches` | no transactions → `totals('2026-01', '2026-12')` spent 0, income 0, groups `[]`; `totalsByPeriod` `[]` |
| `tests/Feature/OverviewTest.php` (new) | `redirects guests to login` | guest `get('/overview?month=2026-06')` → redirect `route('login')` |
| same | `shows the empty state without imports` | `get(route('overview', ['month' => '2026-06']))` 200: sees `No data yet`, `Upload statement`; no `data-kpi` |
| same | `defaults to the latest imported month` | statements `2026-05` (number 5) and `2026-06`; `get(route('overview'))` 200: `<h1` contains `June 2026`; the `data-mode="month"` segment has `aria-current="true"` |
| same | `shows the June fixture totals` | June fixture; `get(route('overview', ['month' => '2026-06']))`: `data-kpi="spent"` block contains `1.686,28 €`, `income` `1.348,19 €`, `net` `−338,09 €` with class `text-rose-600`; the `data-group-row` keys in order `rent, groceries, other, online_orders, health, utilities, restaurants, hobbies, subscriptions, takeaway`; the rent row contains `386,07 €` and `22,9 %`; `data-no-comparison` present with `No data for May 2026 to compare`; no `data-trend` |
| same | `passes the donut data to the chart` | June fixture; extract `data-chart="…"` via regex, `json_decode(html_entity_decode(…), true)`: `total` `1.686,28 €`, `label` `Spent`, 10 slices, first slice `['name' => 'Rent', 'value' => 38607, 'color' => '#6366f1', 'amount' => '386,07 €']`; response contains `x-data="donutChart"` |
| same | `aggregates the whole year` | June fixture + July statement (`number` 7, `2026-07`: out −1000 `groceries`, out −30000 `rent` ÷3, in +5000; no 2025 data); `get(route('overview', ['year' => '2026']))`: `<h1>` `2026`, spent tile `1.796,28 €`, income `1.398,19 €`, net `−398,09 €`; `data-mode="year"` segment `aria-current="true"`; `No data for 2025 to compare` |
| same | `compares groups with the previous month` | factory statements `2026-05` (groceries −10000, takeaway −10000, hobbies −5000) and `2026-06` (groceries −12000, takeaway −8000, health −3000); June page: `vs May 2026`; rows in order `groceries, takeaway, health, hobbies`; `data-trend` `up`/`down`/`new`/`down`; texts `↑ 20 %`, `↓ 20 %`, `new`, `↓ 100 %`; hobbies row shows `0,00 €`; no `data-no-comparison` |
| same | `compares the year with the previous year` | factory months `2025-06` (groceries −10000) and `2026-06` (groceries −15000) → `?year=2026`: `vs 2025`, groceries `data-trend="up"`, `↑ 50 %` |
| same | `links the arrows to imported months only` | statements `2025-11`, `2026-03`, `2026-06`; `?month=2026-03`: `data-nav="prev"` is an `<a` with `href` = `route('overview', ['month' => '2025-11'])`, `data-nav="next"` → `month=2026-06`; `?month=2026-06`: next is `<span … aria-disabled="true"`; `?month=2025-11`: prev disabled |
| same | `links the arrows to imported years` | same statements; `?year=2026`: prev → `year=2025`, next disabled; `?year=2025`: prev disabled, next → `year=2026` |
| same | `links the mode toggle` | same statements; `?month=2026-03`: Year link `href` = `route('overview', ['year' => '2026'])`; `?year=2026`: Month link → `month=2026-06`; `?year=2025`: Month link → `month=2025-11` |
| same | `redirects invalid periods to the default` (dataset `['month' => '2026-13']`, `['month' => '2025-01']`, `['year' => 'abcd']`, `['year' => '2024']`, `['month' => ['x']]`) | with a `2026-06` statement → `assertRedirect(route('overview'))` |
| same | `shows no donut without spending` | `2026-06` statement with only an in +5000 transaction → `data-no-spending` with `No spending in June 2026`; no `data-chart`; income tile `50,00 €`; net `+50,00 €` with `text-emerald-600` |
| same | `excludes ignored entries on the page` | `2026-06` with out −1000 groceries and in +51540 `ignored` true → income tile `0,00 €`, spent `10,00 €` |
| `tests/Feature/PagesTest.php` (unchanged) | `renders each app page for the user`, `links the empty states to upload` | still pass: empty DB → Overview shows `No data yet` + `Upload statement` |

Extracting from the response HTML: tile values with `preg_match('/data-kpi="spent".*?<\/p>/s', $html, $m)` (the value is the tile's only `<p>`), row order with `preg_match_all('/data-group-row="([a-z_]+)"/', $html, $m)`, a single row with `preg_match('/data-group-row="rent".*?<\/li>/s', $html, $m)`.

Factory data: always create the statement first with an explicit `number` and `period` (`Statement::factory()->create(['number' => 7, 'period' => '2026-07'])`) and its transactions with `Transaction::factory()->for($statement)->create([...])`. A bare `Transaction::factory()` creates a statement with number 6 / `2026-06`, which collides with the unique `{number, period}` index when the June fixture is imported.

Note on the year totals: 2026 = June (168628) + July (1000 + 10000) = 179628 spent; income 134819 + 5000 = 139819; net −39809 → `−398,09 €`.

## Automated verification

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `vendor/bin/sail artisan test --compact` | All tests pass (Issues 1–5), 0 failures |
| 2 | Code style | `vendor/bin/sail bin pint --format agent` | No files changed |
| 3 | Package installed | `vendor/bin/sail npm ls echarts` | `echarts@6.1.x` |
| 4 | Frontend builds without size warning | `vendor/bin/sail npm run build 2>&1 \| grep -ci 'larger than'` | Prints `0` (then run `vendor/bin/sail npm run build` once more and confirm exit code 0) |
| 5 | ECharts in its own lazy chunk | `ls public/build/assets/ \| grep -E '^echarts-.*\.js$' \| wc -l` and `wc -c public/build/assets/app-*.js` | `1`; main `app-*.js` < 150000 bytes |
| 6 | Only modular imports | `grep -rn "from 'echarts'" resources/js` | No output (only `echarts/core`, `echarts/charts`, `echarts/components`, `echarts/renderers`) |
| 7 | Route | `vendor/bin/sail artisan route:list --name=overview` | `GET|HEAD overview` → `App\Http\Controllers\OverviewController`, middleware `auth` + `DiscardPendingStatementImport` |
| 8 | Guest redirect over HTTP | `curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "http://localhost/overview?year=2026"` | `302 http://localhost/login` |
| 9 | Dev DB untouched by tests | Boost `database-query-mongodb`: count `statements` and `transactions` in the default DB before and after check 1 | Same counts |
| 10 | No errors logged | Boost `last-error` and `read-log-entries` (last 20) | No new errors or exceptions |

## Manual verification

- [ ] Start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run build`; make sure rules are seeded (`vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction`); log in at http://localhost.
- [ ] With nothing imported, Overview still shows the "No data yet" card with "Upload statement".
- [ ] Import `tests/Fixtures/ing-2026-06.pdf` (assign the −200,00 € transfer to "Other"), confirm, then open Overview → URL `/overview`, title "June 2026", "Month" active, both arrows greyed out.
- [ ] Tiles: Spent **1.686,28 €**, Income **1.348,19 €**, Net **−338,09 €** in red.
- [ ] Donut: 10 coloured segments in the group colours (Rent indigo largest, then Groceries green, Other grey …), "1.686,28 €" with "Spent" in the centre. Hover a segment → it grows slightly; tooltip "Rent" / "386,07 € · 22,9 %".
- [ ] List next to the donut: same order and colours as the donut, amounts and shares (Rent 386,07 € 22,9 %, Electricity & Gas 129,34 €); under it "No data for May 2026 to compare"; no arrows/percent pills.
- [ ] Click "Year" → URL `/overview?year=2026`, title "2026", same figures (only June imported). Click "Month" → back to June 2026.
- [ ] Second month (demo data): `vendor/bin/sail artisan tinker --execute '$s = App\Models\Statement::factory()->create(["number" => 5, "period" => "2026-05"]); App\Models\Transaction::factory()->for($s)->createMany([["period" => "2026-05", "group_key" => "groceries", "amount_cents" => -25000], ["period" => "2026-05", "group_key" => "takeaway", "amount_cents" => -9000], ["period" => "2026-05", "group_key" => "rent", "share_divisor" => 3, "merchant" => "Miete", "amount_cents" => -115820]]);'`. Reload June 2026 → "vs May 2026"; Groceries `↑ 17 %` red, Takeaway & Fast Food `↓ 32 %` green, Rent `±0 %` grey, the other groups "new" in red. The ‹ arrow is active and leads to May 2026 (Spent 726,07 €); from May, › leads back to June.
- [ ] Year 2026 with the demo month: Spent **2.412,35 €**, Income 1.348,19 €, Net −1.064,16 €; "No data for 2025 to compare".
- [ ] Remove the demo data afterwards: `vendor/bin/sail artisan tinker --execute 'App\Models\Statement::where("period", "2026-05")->get()->each(function ($s) { $s->transactions()->delete(); $s->delete(); });'`.
- [ ] Typing `/overview?month=2019-01` in the address bar lands on `/overview` (June 2026).
- [ ] Resize the browser window (wide ↔ narrow): the donut resizes smoothly with its card and never overflows; at ≥ 1024 px donut and list sit side by side, below that the list is under the donut.
- [ ] Light mode: white cards, dark text in the donut centre and tooltip, white gaps between segments.
- [ ] Dark mode: switch the OS theme **while the page is open** → the donut centre text, tooltip and segment gaps switch to the dark palette without reload; tiles and list use dark cards; no white boxes.
- [ ] Narrow window (~375 px): toggle and arrows wrap cleanly, tiles stack in one column, list shows name/amount/delta without horizontal scroll (share % hidden), long group names truncate.
- [ ] Keyboard: Tab reaches the Month/Year link and the arrows with a visible emerald focus outline; Enter follows them.
- [ ] Network tab: the `echarts-*.js` chunk is only requested on Overview, not on Upload or Trends; all requests go to localhost.

## Requirement traceability

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| Month \| Year toggle | Steps 2, 5, 7 | `toggles between month and year`, `links the mode toggle`, `defaults to the latest imported month`, `aggregates the whole year` | #1; manual: Year/Month click |
| `‹ June 2026 ›` arrows, disabled beyond data range | Steps 2, 5, 7 | `steps through imported months only`, `steps through imported years only`, `links the arrows to imported months only`, `links the arrows to imported years` | #1; manual: arrows, demo month |
| Defaults to the latest imported month | Steps 2, 5 | `resolves the latest month by default`, `defaults to the latest imported month`, `redirects invalid periods to the default` | #1; manual: open Overview, invalid URL |
| KPI tiles Spent, Income, Net using counted amounts (÷ share, ignored excluded) | Steps 3, 5, 7 | `aggregates the June fixture`, `divides shared costs by their share`, `rounds each shared entry before summing`, `excludes ignored entries`, `counts income without grouping it`, `shows the June fixture totals`, `excludes ignored entries on the page`, `shows no donut without spending` | #1; manual: tiles |
| Donut by group (group colours, total in centre, hover amount + %) | Steps 1, 5, 6, 7 | `passes the donut data to the chart`, `shows no donut without spending` | #3–#6; manual: donut, hover, light/dark |
| Comparison vs previous period per group: amount + ↑/↓ %, up red / down green | Steps 4, 5, 7 | `compares groups with the previous period`, `marks unchanged groups`, `omits deltas without previous data`, `hides groups without spending in both periods`, `orders by amount then previous amount then config order`, `compares groups with the previous month`, `compares the year with the previous year` | #1; manual: demo month comparison |
| Install ECharts with modular imports (pie, bar, legend, tooltip, SVG renderer) | Steps 1, 6 | none (build-level) | #3, #5, #6 |
| Alpine component wrapping ECharts, shared light/dark theme following the OS, resizes with its container | Step 6 | none (browser behaviour) | #4, #5; manual: resize, dark mode switch while open |
| Donut total in the centre via a title element | Step 6 | `passes the donut data to the chart` (`total`, `label`) | manual: donut centre |
| Aggregation in a dedicated service (Mongo aggregation pipeline), tested with known sums (June rent 386.07) | Step 3 | `aggregates the June fixture`, `divides shared costs by their share`, `returns totals per period`, `counts outgoing entries without group as unassigned`, `returns empty totals when nothing matches` | #1 |
| Tests: aggregation correctness (shares, ignored, income) | Step 8 | `SpendingReportTest` | #1 |
| Tests: period navigation bounds | Step 8 | `ReportPeriodTest`, `links the arrows to …`, `redirects invalid periods to the default` | #1 |
| Tests: empty state | Step 8 | `shows the empty state without imports`, `shows no donut without spending`, `PagesTest` (unchanged) | #1 |
| Done when: June 2026 shows correct totals per group and switching to 2026 aggregates all imported months | Steps 1–7 | `aggregates the June fixture`, `shows the June fixture totals`, `aggregates a year across months`, `aggregates the whole year` | #1; manual: June tiles/list, Year 2026 with demo month |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] After importing the June fixture, `/overview` shows June 2026 with Spent 1.686,28 €, Income 1.348,19 €, Net −338,09 €, Rent 386,07 € and Electricity & Gas 129,34 €, and a donut in the group colours with the total in the centre.
- [ ] `/overview?year=2026` sums every imported month of 2026; arrows and toggle only lead to imported periods.
- [ ] ECharts loads as a separate modular chunk on Overview only, follows the OS theme live and resizes with its card.
