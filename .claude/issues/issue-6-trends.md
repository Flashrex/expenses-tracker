# Issue 6: Trends (groups over time)

> Source: `.claude/project-plan.md` › "Issue 6 – Trends (groups over time)"
> Planned: 2026-09-29
> Depends on: Issue 1 (`.claude/issues/issue-1-foundation.md`: layouts, `trends` route, Trends empty state, `config/expenses.php` groups, `DatabaseMigrations`), Issue 2 (`.claude/issues/issue-2-statement-upload-parsing.md`: `Statement`/`Transaction` models + factories, `Period` helper, `DiscardPendingStatementImport`), Issue 3 (`.claude/issues/issue-3-automatic-grouping.md`: stored `group_key`/`share_divisor`/`ignored`, `RuleSeeder`), Issue 4 (`.claude/issues/issue-4-manual-review.md`: `assignOpenEntries()`), Issue 5 (`.claude/issues/issue-5-overview-dashboard.md`: `ReportMode`, `ReportPeriod`, `Totals`, `SpendingReport`, `GroupComparison::UNASSIGNED`, `OverviewController`, `<x-period-switcher>`, ECharts setup `resources/js/charts/*`, `importFixtureStatement()`).

## Goal

See how spending per group evolves: the Trends page shows a stacked bar chart with one bar per month (or per year) across the whole imported range, one segment per group, and a clickable legend that hides/shows groups with the bar totals updating.

Original "Done when": **with ≥2 imported months you can compare groups month over month and toggle groups on and off.**

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run every PHP, Artisan, Composer, Node and test command through Sail: `vendor/bin/sail …`. Host PHP lacks `ext-mongodb`, so host `php artisan` fails. If containers are down, run `vendor/bin/sail up -d` first.
- Issues 1–5 must be fully implemented first. Check: `app/Http/Controllers/OverviewController.php`, `app/Services/Reports/SpendingReport.php`, `app/Services/Reports/Totals.php`, `app/Services/Reports/ReportPeriod.php`, `app/Services/Reports/GroupComparison.php`, `app/Enums/ReportMode.php`, `resources/views/components/period-switcher.blade.php`, `resources/js/charts/echarts.js`, `resources/js/charts/theme.js` exist; `tests/Pest.php` defines `importFixtureStatement()`; `vendor/bin/sail npm ls echarts` shows `echarts@6.1.x`; `vendor/bin/sail artisan test --compact` is green. If not, stop and report.
- Load these project skills before writing the matching code: `laravel-best-practices` (controller, service, model method, route), `tailwindcss-development` (Blade views and components), `testing-best-practices` (Pest tests).
- Use Boost `search-docs` for any Laravel/Pest/Tailwind API you are unsure about (Laravel 13, laravel-mongodb 5.11, Pest 5, Tailwind v4, Alpine 3). ECharts is not covered by `search-docs`: use the options as sketched in Step 5 and check the two flagged options against `node_modules/echarts/types/dist/shared.d.ts` (and `node_modules/echarts/lib/component/legend/install.js`).
- After editing PHP files run `vendor/bin/sail bin pint --dirty --format agent`.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| PHP (Sail runtime) | 8.4 | `compose.yaml`, `AGENTS.md` |
| Laravel | ^13.17 (13.33.0 locked) | `composer.json`, `composer.lock` |
| MongoDB driver | `mongodb/laravel-mongodb` ^5.11; server `mongodb/mongodb-atlas-local:8.0` | `composer.json`, auto-memory |
| Tests | Pest ^5.2; Feature tests use `DatabaseMigrations`; Unit tests do not boot Laravel | `composer.json`, Issue 1 plan |
| Frontend | Tailwind v4 (CSS-first `resources/css/app.css`), Alpine ^3.17, Vite ^8 + `laravel-vite-plugin` ^3.1, heroicons via `blade-ui-kit/blade-heroicons` | `package.json`, Issue 1 plan |
| Charts | `echarts` ^6.1 installed by Issue 5; lazy chunk `resources/js/charts/echarts.js` already registers `PieChart`, `BarChart`, `TitleComponent`, `TooltipComponent`, `LegendComponent`, `SVGRenderer` and the themes `expenses-light` / `expenses-dark`; `build.chunkSizeWarningLimit` 600 | Issue 5 plan |

No new packages. `BarChart` and `LegendComponent` are already registered, so the chunk size does not change.

### Current state of the codebase

Today the repository is still the MongoDB-switched Laravel skeleton; Issues 1–5 are planned but not yet implemented. This plan assumes their **finished** state as specified in their plan files:

- `routes/web.php`: auth group `Route::middleware(['auth', DiscardPendingStatementImport::class])` containing `Route::get('/overview', OverviewController::class)->name('overview')` and `Route::view('/trends', 'pages.trends')->name('trends')`. **The `trends` line is replaced in this issue.**
- `resources/views/pages/trends.blade.php` (Issue 1): `<x-layouts.app title="Trends">` + `<x-empty-state icon="heroicon-o-chart-bar" title="No trends yet" text="Trends appear once you have imported a statement.">` with an "Upload statement" primary-button link to `route('upload')`. **Rewritten; the empty state stays for the no-data case, byte-for-byte as today.**
- `resources/views/components/layouts/app.blade.php`: top bar; Trends nav link active via `request()->routeIs('trends')` (still true with `?by=year`); `<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">`. Unchanged.
- `resources/views/components/period-switcher.blade.php` (Issue 5): props `period`, `earlier`, `later`, `toggled`, `route` (default `'overview'`). Contains the Month|Year segmented toggle inline, then arrows and `<h1>`. **The toggle part is extracted into a new `<x-mode-toggle>` component; rendered HTML stays identical.**
- `app/Http/Controllers/OverviewController.php` (Issue 5): step 1 builds `$imported` with `Statement::query()->whereNotNull('confirmed_at')->pluck('period')->unique()->sort()->values()->all()`. **That expression moves into `Statement::importedPeriods()`.**
- `app/Services/Reports/SpendingReport.php` (Issue 5): `totalsByPeriod(string $fromPeriod, string $toPeriod): array<string, Totals>` (keys `YYYY-MM` ascending, only periods with counted transactions; per-entry rounding of ÷n shares; ignored excluded; outgoing without group → key `unassigned`). **Reused unchanged.**
- `app/Services/Reports/Totals.php` (Issue 5): `spentCents`, `incomeCents`, `groupCents` (positive cents, only non-zero), `Totals::sum(iterable)`, `Totals::empty()`. Reused unchanged.
- `app/Services/Reports/GroupComparison.php` (Issue 5): `public const UNASSIGNED = 'unassigned'`; the Unassigned pseudo-group has name `Unassigned`, colour `#cbd5e1`. Reused (constant only).
- `app/Enums/ReportMode.php` (Issue 5): `Month = 'month'`, `Year = 'year'`. Reused.
- `app/Support/Period.php` (Issue 2): `Period::label('2026-06')` → `June 2026`, `Period::short('2026-06')` → `Jun 2026` (Carbon only; usable in unit tests). Reused.
- `resources/js/charts/theme.js` (Issue 5): `themes` built by a `theme({ text, muted, surface, border, legend })` function, `currentTheme()`, `watchTheme(callback)`. **Extended with axis, legend and bar-label styling.**
- `resources/js/charts/echarts.js` (Issue 5): unchanged.
- `resources/js/donut-chart.js` (Issue 5): reference for the component pattern. Unchanged.
- `resources/js/app.js` (Issue 5): imports Alpine, `./donut-chart`, `./review-queue`; registers `donutChart`, `reviewQueue`. **Extended.**
- `tests/Pest.php`: helpers incl. `importFixtureStatement()` (Issue 5). Unchanged.
- `tests/Feature/PagesTest.php` (Issue 1): with an empty DB, `trends` shows "No trends yet" and "Upload statement". Must keep passing unchanged. `tests/Feature/OverviewTest.php` (Issue 5) must keep passing unchanged (toggle refactor).
- `config/expenses.php` groups, in sort order: `rent` Rent `#6366f1`, `utilities` Electricity & Gas `#f59e0b`, `groceries` Groceries & Personal Care `#10b981`, `subscriptions` Subscriptions & Internet `#8b5cf6`, `hobbies` Hobbies & Entertainment `#ec4899`, `online_orders` Online Orders `#0ea5e9`, `takeaway` Takeaway & Fast Food `#f97316`, `restaurants` Restaurants & Bars `#f43f5e`, `health` Health `#14b8a6`, `other` Other `#94a3b8`. Unchanged.
- No `TrendsController`, no `TrendSeries`, no trend chart JS.

### Fixture facts (from Issue 5, used by tests)

June fixture (`importFixtureStatement()`), period `2026-06`, counted spending per group in cents: rent 38607, utilities 12934, groceries 29349, subscriptions 7145, hobbies 7420, online_orders 15835, takeaway 6118, restaurants 9210, health 15065, other 26945; Spent 168628; Income 134819.

### Domain rules that apply

- Counted amount of an entry = `amount_cents / share_divisor`, only for non-ignored entries (already implemented in `SpendingReport`, per-entry rounding).
- Only spending is grouped and charted. Income is not grouped and does not appear on Trends.
- Month attribution = statement month: the stored `period` field.
- Groups are fixed in `config/expenses.php` (key, name, colour, sort); chart colours come from there.
- Money is integer cents; German formatting (`1.686,28 €`).
- Entries are final after confirm; this issue only reads data.
- Charts: Apache ECharts via npm, modular imports only, shared theme following the OS (`prefers-color-scheme`). UI language English.

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | Which totals update when groups are toggled | **Bar-top label + tooltip.** Each bar shows the total of the *visible* groups above it; the axis tooltip lists the visible groups of that period plus a "Total" line. No extra page elements. |
| 2 | Legend style | **ECharts legend below the chart** (built-in toggle; hidden groups greyed out). |
| 3 | Many periods | **All bars fit the width.** No DataZoom, no horizontal scroll; ECharts thins out axis labels automatically. |
| 4 | Header row | **Month\|Year toggle (same look as Overview) + range title**, no arrows. Title: `Jun 2025 – Jun 2026` (month), `2025 – 2026` (year), single period `Jun 2026` / `2026`. Separator: space, en dash U+2013, space. |
| 5 | URLs | **`/trends`** = month mode, **`/trends?by=year`** = year mode. Plain links, full page load, like Overview. |
| 6 | Hidden groups across loads | **Reset on each page load**: all groups visible; nothing stored. |
| 7 | Income on Trends | **No.** Spending by group only. |
| 8 | Range and gaps | **Continuous from the first to the last imported period.** Month mode: every calendar month from the earliest to the latest imported month; months without a statement get an empty bar (all groups 0, total label `0 €`). Year mode: every year from the first to the last imported year, missing years 0. |
| 9 | Number precision | **Whole euros on the chart** (bar-top labels and y-axis: `1.686 €`, `500 €`, rounded half up via `Math.round`), **exact cents in the tooltip** (`386,07 €`, total `1.686,28 €`). |
| 10 | Groups in legend/tooltip | **Only groups with spending** somewhere in the range appear as series/legend items, in config `sort` order, stacked bottom→top in that order (Rent at the bottom). "Unassigned" (`#cbd5e1`) appears last, only if such entries exist. Tooltip lists only groups > 0 in that period (same order), then Total. |
| 11 | Exactly one imported month | **One bar + hint** below the chart: "Import another month to compare trends." Shown only when exactly one month is imported in total (not for year mode with several months in one year). |
| 12 | Invalid query (settled here, mirrors Overview Decision 9) | `by` absent → month mode (`?by=` with an empty value counts as absent, because Laravel's `ConvertEmptyStringsToNull` middleware turns it into `null`). `by=year` → year mode. Any other `by` value (including `month` or an array) → `302` to `/trends`. With no statements at all, query parameters are ignored and the empty state renders. |
| 13 | Toggle targets (settled here) | Month → `route('trends', ['by' => 'year'])`; Year → `route('trends')`. The active mode is a `<span aria-current="true">`, not a link. |
| 14 | Axis labels (settled here) | Month mode: `Period::short()` (`Jun 2026`); year mode: `2026`. |
| 15 | Range with imports but no spending (settled here) | The chart is replaced by "No spending in ‹range title›"; with only June 2026 imported: "No spending in Jun 2026". The single-month hint (Decision 11) still shows when it applies. |
| 16 | ECharts instance in Alpine (settled here) | The chart instance and the parsed data are kept in closure variables inside `init()`, **not** as Alpine component properties, so Alpine's reactive proxies never wrap them. |
| 17 | Section heading (settled here) | Card heading: "Spending by group". Chart `aria-label`: "Spending by group per month, ‹range title›" (year mode: "per year"). |

## Scope

### In scope

- `Statement::importedPeriods()` (shared by Overview and Trends).
- `TrendSeries` value object: continuous period axis, year folding, zero-filling, group series in config order.
- `TrendsController` replacing the `Route::view` for `/trends`, with `?by=year`.
- `<x-mode-toggle>` component extracted from `<x-period-switcher>` and used by both pages.
- Trends page: toggle + range title, card with the stacked bar chart, empty states, single-month hint.
- Alpine `trendChart` component: stacked bars, legend below (toggle), bar-top totals and tooltip that follow the visible groups, shared light/dark theme following the OS live, resizes with its container.
- Theme extended with axis, legend-inactive and bar-label colours.
- Unit and feature tests: series shape per granularity, months/years without data as zero, controller behaviour.

### Out of scope

- Income on the chart, KPI tiles or range summaries (average per month, range total) on Trends (Decisions 1, 7).
- Remembering hidden groups (Decision 6), DataZoom / scrolling (Decision 3), period arrows on Trends (Decision 4).
- Client-side mode switching or JSON endpoints (Decision 5).
- Drill-down from a bar/segment into entries, per-group comparison lists (Overview has those).
- Changes to the Overview page beyond the invisible toggle extraction and `Statement::importedPeriods()`.
- Editing/deleting entries or statements, re-running rules, rule management UI, editable groups, budgets, income grouping, export, caching (project "Out of scope").
- A manual light/dark toggle.

## Prerequisites

Containers running and Issue 5 assets built:

```sh
vendor/bin/sail up -d
```

No packages to install, no migrations, no new `.env` keys.

## Implementation steps

### Step 1: `Statement::importedPeriods()`

**Files**
- Modify: `app/Models/Statement.php` — add a static method
- Modify: `app/Http/Controllers/OverviewController.php` — use it

**Details**

```php
/**
 * Periods (YYYY-MM) that have a confirmed statement, ascending and unique.
 *
 * @return list<string>
 */
public static function importedPeriods(): array
{
    return static::query()->whereNotNull('confirmed_at')->pluck('period')->unique()->sort()->values()->all();
}
```

In `OverviewController::__invoke`, replace the step-1 expression with `$imported = Statement::importedPeriods();`. Nothing else changes.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan test --compact --filter=OverviewTest
```

**Step check:** `OverviewTest` passes unchanged.

### Step 2: Extract `<x-mode-toggle>`

**Files**
- New: `resources/views/components/mode-toggle.blade.php`
- Modify: `resources/views/components/period-switcher.blade.php` — replace the inline toggle `<div … role="group" aria-label="Period type">…</div>` with the component

**Details**

`<x-mode-toggle>` props: `mode` (`App\Enums\ReportMode`), `monthUrl` (string), `yearUrl` (string). It renders **exactly** the markup the period switcher renders today (move it, do not restyle it):

- Wrapper `<div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900" role="group" aria-label="Period type">`.
- Segments in order "Month" (`data-mode="month"`), "Year" (`data-mode="year"`), base classes `rounded-md px-3 py-1.5 text-sm font-medium`. The segment matching `$mode` is `<span aria-current="true" class="… bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400">`; the other is `<a href="{{ $monthUrl }}">` / `<a href="{{ $yearUrl }}">` with the existing link classes (`text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400`).

In `<x-period-switcher>`: `<x-mode-toggle :mode="$period->mode" :month-url="route($route, $toggled->query())" :year-url="route($route, $toggled->query())" />`. (Only the inactive segment uses its URL, and `$toggled` always points to the other mode, so passing the same URL for both is correct.)

**Commands**

```sh
vendor/bin/sail artisan test --compact --filter=OverviewTest
```

**Step check:** `OverviewTest` passes unchanged (`links the mode toggle`, `defaults to the latest imported month`, `aggregates the whole year` cover the toggle).

### Step 3: `TrendSeries` value object

**Files**
- New: `app/Services/Reports/TrendSeries.php` (`vendor/bin/sail artisan make:class Services/Reports/TrendSeries --no-interaction`)

`final readonly class`. No container, facades or app helpers (unit-tested without Laravel); `App\Support\Period` is allowed.

```php
/**
 * @param  list<string>  $periods  'YYYY-MM' (month) or 'YYYY' (year), ascending, continuous
 * @param  list<string>  $labels   axis labels aligned with $periods
 * @param  list<array{key: string, name: string, color: string, values: list<int>}>  $groups  values = positive cents aligned with $periods
 */
private function __construct(
    public ReportMode $mode,
    public array $periods,
    public array $labels,
    public array $groups,
) {}

/**
 * @param  array<string, Totals>  $totalsByPeriod  SpendingReport::totalsByPeriod() output
 * @param  array<string, array{name: string, color: string, sort: int}>  $groups  config('expenses.groups')
 */
public static function build(ReportMode $mode, string $firstMonth, string $lastMonth, array $totalsByPeriod, array $groups): self;

/** @return list<int> sum of all group values per period */
public function totals(): array;

/** Decision 4: "Jun 2025 – Jun 2026", "2025 – 2026", or a single label. */
public function rangeLabel(): string;

public function hasSpending(): bool;   // $groups !== []
```

`build()`:

1. **Periods.** Month mode: every month from `$firstMonth` to `$lastMonth` inclusive, stepping with integers (`$y`, `$m`; after `12` comes `($y + 1)-01`), formatted `sprintf('%04d-%02d', …)`. Year mode: every year from `(int) substr($firstMonth, 0, 4)` to `(int) substr($lastMonth, 0, 4)`, as strings.
2. **Buckets.** Month mode: `$bucket[$period] = $totalsByPeriod[$period] ?? Totals::empty()`. Year mode: `$bucket[$year] = Totals::sum(<all $totalsByPeriod entries whose key starts with "$year-">)`, or `Totals::empty()` if none.
3. **Labels.** Month: `Period::short($period)`; year: the year string.
4. **Groups.** Candidate keys: config keys in `sort` order (sort the config by `sort` ascending), then `GroupComparison::UNASSIGNED` (name `Unassigned`, colour `#cbd5e1`). For each: `values` = `$bucket[$p]->groupCents[$key] ?? 0` for every period. Keep the group only if `array_sum(values) > 0` (Decision 10).

`totals()`: for each period index, the sum of `values[$i]` over all groups.

`rangeLabel()`: `$first = $labels[0]`, `$last = $labels[array_key_last($labels)]`; equal → `$first`, else `"$first – $last"` (U+2013 between single spaces).

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by `tests/Unit/TrendSeriesTest.php` (Step 7).

### Step 4: Controller and route

**Files**
- New: `app/Http/Controllers/TrendsController.php` (`vendor/bin/sail artisan make:controller TrendsController --invokable --no-interaction`)
- Modify: `routes/web.php` — replace `Route::view('/trends', 'pages.trends')->name('trends');` with `Route::get('/trends', TrendsController::class)->name('trends');` (same place in the auth group; add the `use`)

**`TrendsController::__invoke(Request $request, SpendingReport $report): View|RedirectResponse`**:

1. `$imported = Statement::importedPeriods();`
2. `$imported === []` → `return view('pages.trends', ['series' => null]);` (empty state; query ignored).
3. `$by = $request->query('by');` If `$by !== null && $by !== 'year'` → `return redirect()->route('trends');` (Decision 12). `$mode = $by === 'year' ? ReportMode::Year : ReportMode::Month;`
4. `$first = $imported[0]; $last = $imported[array_key_last($imported)];`
5. `$series = TrendSeries::build($mode, $first, $last, $report->totalsByPeriod($first, $last), config('expenses.groups'));`
6. `$chart = ['labels' => $series->labels, 'series' => $series->groups];` (each group: `key`, `name`, `color`, `values` in cents).
7. Return `view('pages.trends', ['series' => $series, 'chart' => $chart, 'mode' => $mode, 'singleMonth' => count($imported) === 1])`.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan route:list --name=trends
```

**Step check:** `route:list --name=trends` shows `GET|HEAD trends … TrendsController`; middleware `auth` and `DiscardPendingStatementImport`.

### Step 5: Theme extension and Alpine trend chart

**Files**
- Modify: `resources/js/charts/theme.js` — axis, legend and bar-label colours
- New: `resources/js/charts/format.js` — money formatters
- New: `resources/js/trend-chart.js` — Alpine component
- Modify: `resources/js/app.js` — register it

**`theme.js`**: extend the `theme()` parameters with `grid` (split line colour) and `inactive` (hidden legend items) and add these keys to the returned object (keep all existing keys):

```js
const theme = ({ text, muted, surface, border, legend, grid, inactive }) => ({
    // …existing keys…
    legend: { textStyle: { color: legend }, inactiveColor: inactive },
    categoryAxis: { axisLine: { lineStyle: { color: border } }, axisTick: { show: false }, axisLabel: { color: muted }, splitLine: { show: false } },
    valueAxis: { axisLine: { show: false }, axisLabel: { color: muted }, splitLine: { lineStyle: { color: grid } } },
    bar: { label: { color: muted } },
});

export const themes = {
    'expenses-light': theme({ text: '#0f172a', muted: '#64748b', surface: '#ffffff', border: '#e2e8f0', legend: '#475569', grid: '#f1f5f9', inactive: '#cbd5e1' }),
    'expenses-dark': theme({ text: '#f1f5f9', muted: '#94a3b8', surface: '#0f172a', border: '#1e293b', legend: '#cbd5e1', grid: '#1e293b', inactive: '#475569' }),
};
```

(The `legend` key replaces the existing one; slate-100/800 grid lines, slate-300/600 inactive legend items.)

**`format.js`**:

```js
const de = (value, digits) =>
    value.toLocaleString('de-DE', { minimumFractionDigits: digits, maximumFractionDigits: digits, useGrouping: true });

/** 168628 → "1.686,28 €" */
export const euros = (cents) => `${de(cents / 100, 2)} €`;

/** 168628 → "1.686 €" */
export const wholeEuros = (cents) => `${de(Math.round(cents / 100), 0)} €`;
```

**`trend-chart.js`** (instance and data in closure variables, Decision 16):

```js
import { currentTheme, watchTheme } from './charts/theme';
import { euros, wholeEuros } from './charts/format';

export default () => {
    let chart = null;
    let cleanup = [];

    return {
        async init() {
            const { echarts } = await import('./charts/echarts');
            const data = JSON.parse(this.$el.dataset.chart);

            /** Per-period sums of the groups the legend currently shows. */
            const visibleTotals = (selected) =>
                data.labels.map((_, i) =>
                    data.series.reduce((sum, s) => sum + (selected[s.name] === false ? 0 : s.values[i]), 0));

            const totalData = (selected) => visibleTotals(selected).map((total) => ({ value: 0, total }));

            const refreshTotals = () => {
                const selected = chart.getOption().legend?.[0]?.selected ?? {};
                chart.setOption({ series: [{ id: 'total', data: totalData(selected) }] });
            };

            chart = echarts.init(this.$refs.chart, currentTheme(), { renderer: 'svg' });
            chart.setOption({
                grid: { top: 32, left: 8, right: 8, bottom: 48, containLabel: true },
                legend: { type: 'scroll', bottom: 0, icon: 'circle', itemWidth: 10, itemHeight: 10, data: data.series.map((s) => s.name) },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' },
                    formatter: (params) => {
                        const rows = params.filter((p) => p.seriesId !== 'total' && p.value > 0);
                        const total = rows.reduce((sum, p) => sum + p.value, 0);
                        const line = (left, right, bold = false) =>
                            `<div style="display:flex;justify-content:space-between;gap:16px${bold ? ';font-weight:600' : ''}"><span>${left}</span><span>${right}</span></div>`;
                        return [
                            `<div style="font-weight:600;margin-bottom:4px">${params[0].axisValueLabel}</div>`,
                            ...rows.map((p) => line(`${p.marker}${p.seriesName}`, euros(p.value))),
                            line('Total', euros(total), true),
                        ].join('');
                    },
                },
                xAxis: { type: 'category', data: data.labels },
                yAxis: { type: 'value', axisLabel: { formatter: (v) => wholeEuros(v) } },
                series: [
                    ...data.series.map((s) => ({
                        id: s.key,
                        name: s.name,
                        type: 'bar',
                        stack: 'spending',
                        barMaxWidth: 48,
                        itemStyle: { color: s.color },
                        emphasis: { focus: 'series' },
                        data: s.values,
                    })),
                    {
                        id: 'total',
                        name: 'Total',
                        type: 'bar',
                        stack: 'spending',
                        barMaxWidth: 48,
                        silent: true,
                        tooltip: { show: false },
                        label: { show: true, position: 'top', fontWeight: 600, formatter: (p) => wholeEuros(p.data.total) },
                        data: totalData({}),
                    },
                ],
            });

            chart.on('legendselectchanged', refreshTotals);

            const observer = new ResizeObserver(() => chart?.resize());
            observer.observe(this.$refs.chart);
            cleanup = [
                () => observer.disconnect(),
                watchTheme((name) => {
                    chart?.setTheme(name);
                    refreshTotals();
                }),
            ];
        },

        destroy() {
            cleanup.forEach((fn) => fn());
            chart?.dispose();
            chart = null;
        },
    };
};
```

Notes for this step:

- The `total` series is a zero-height bar stacked last, so its `top` label sits on top of the visible stack and carries the visible total (Decision 1). It is not in `legend.data`, so it has no legend item and is never hidden. It is filtered out of the tooltip rows.
- `refreshTotals()` reads the legend selection from the chart (not from the event payload) so it also re-syncs after `setTheme()`.
- **Check against the installed types:** (a) if `GridOption.containLabel` is marked `@deprecated` in `node_modules/echarts/types/dist/shared.d.ts`, use the replacement option documented there so y-axis labels are not clipped; (b) confirm `node_modules/echarts/lib/component/legend/install.js` installs the scroll legend (`installLegendScroll`); if it does not, add `LegendScrollComponent` from `echarts/components` to the `echarts.use([...])` list in `resources/js/charts/echarts.js`.
- Series names come from config group names (fixed in code, contain `&`); ECharts renders tooltip HTML — acceptable, as in Issue 5.

**`resources/js/app.js`** becomes:

```js
import Alpine from 'alpinejs';
import donutChart from './donut-chart';
import reviewQueue from './review-queue';
import trendChart from './trend-chart';

window.Alpine = Alpine;
Alpine.data('reviewQueue', reviewQueue);
Alpine.data('donutChart', donutChart);
Alpine.data('trendChart', trendChart);
Alpine.start();
```

**Commands**

```sh
vendor/bin/sail npm run build
```

**Step check:** build exits 0 with no "larger than" warning; `ls public/build/assets/ | grep -E '^echarts-.*\.js$'` lists exactly one file; `wc -c public/build/assets/app-*.js` is below 150000 bytes.

### Step 6: Trends page

**Files**
- Modify (rewrite): `resources/views/pages/trends.blade.php`

`<x-layouts.app title="Trends">`:

- `@if ($series === null)`: exactly the Issue 1 empty state (icon `heroicon-o-chart-bar`, title "No trends yet", text "Trends appear once you have imported a statement.", "Upload statement" action to `route('upload')`). Nothing else.
- `@else` `<div class="space-y-6">`:
  1. Header `<div class="flex flex-wrap items-center justify-between gap-3">`: `<x-mode-toggle :mode="$mode" :month-url="route('trends')" :year-url="route('trends', ['by' => 'year'])" />` and `<h1 class="text-lg font-semibold tabular-nums">{{ $series->rangeLabel() }}</h1>`.
  2. Card `<section aria-labelledby="trend-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">`:
     - `<h2 id="trend-heading" class="text-sm font-semibold">Spending by group</h2>`
     - If `$series->hasSpending()`: `<div x-data="trendChart" data-chart="@json($chart)" class="mt-4"><div x-ref="chart" role="img" aria-label="Spending by group per {{ $mode->value }}, {{ $series->rangeLabel() }}" class="h-80 w-full sm:h-96"></div></div>`.
     - Else: `<p data-no-spending class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No spending in {{ $series->rangeLabel() }}</p>`.
     - If `$singleMonth`: `<p data-single-period class="mt-3 text-xs text-slate-500 dark:text-slate-400">Import another month to compare trends.</p>`.

**Commands**

```sh
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact --filter=PagesTest
```

**Step check:** `PagesTest` passes (empty state unchanged).

### Step 7: Tests

**Files**
- New: `tests/Unit/TrendSeriesTest.php` (`vendor/bin/sail artisan make:test --pest --unit TrendSeriesTest --no-interaction`)
- New: `tests/Feature/TrendsTest.php` (`vendor/bin/sail artisan make:test --pest TrendsTest --no-interaction`)

**Commands**

```sh
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** all tests pass.

## Tests

Test runner and command: `vendor/bin/sail artisan test --compact`

Unit tests build `Totals` objects directly and pass an inline groups array in the config shape (`['rent' => ['name' => 'Rent', 'color' => '#6366f1', 'sort' => 1], …]`). Feature tests use `DatabaseMigrations`, `User::factory()->create()` + `actingAs`. Always create statements first with explicit `number` and `period` (`Statement::factory()->create(['number' => 7, 'period' => '2026-07'])`) and transactions with `Transaction::factory()->for($statement)->create([...])` passing `period`, `amount_cents`, `direction`, `group_key`, `share_divisor`, `ignored` explicitly (a bare `Transaction::factory()` creates statement 6 / `2026-06`, colliding with the June fixture). "June fixture" = `importFixtureStatement()`. "July" = statement `number` 7 / `2026-07` with out −1000 `groceries`, out −30000 `rent` ÷3, in +5000. Chart data is read by extracting `data-chart="…"` with a regex and `json_decode(html_entity_decode($m[1]), true)`.

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `tests/Unit/TrendSeriesTest.php` (new) | `builds a continuous month axis with zeros for missing months` | totals `2026-03` groceries 1000, `2026-06` groceries 2000; month mode `2026-03`…`2026-06` → periods `['2026-03','2026-04','2026-05','2026-06']`, labels `['Mar 2026','Apr 2026','May 2026','Jun 2026']`, groceries values `[1000, 0, 0, 2000]` |
| same | `steps months across a year boundary` | `2025-11`…`2026-02` → periods `['2025-11','2025-12','2026-01','2026-02']` |
| same | `folds months into years` | totals `2025-12` groceries 9999, `2026-06` groceries 29349 + rent 38607, `2026-07` groceries 1000 + rent 10000; year mode `2025-12`…`2026-07` → periods `['2025','2026']`, labels same; groceries `[9999, 30349]`, rent `[0, 48607]` |
| same | `fills missing years with zeros` | totals `2024-05` other 500, `2026-01` other 700; year mode → periods `['2024','2025','2026']`, other `[500, 0, 700]` |
| same | `lists only groups with spending in config order` | config of 4 groups (rent 1, groceries 2, takeaway 3, health 4); totals: takeaway 9000, rent 100 (health 0 everywhere) → group keys `['rent','takeaway']` (config order, not amount order); each group carries config `name` and `color` |
| same | `adds unassigned after the config groups` | groupCents `['unassigned' => 500, 'rent' => 100]` → keys `['rent','unassigned']`; unassigned name `Unassigned`, color `#cbd5e1` |
| same | `sums the groups per period` | two groups over 3 months → `totals()` equals per-index sums, `0` for an empty month |
| same | `labels the range` | month `2025-06`…`2026-06` → `rangeLabel()` `Jun 2025 – Jun 2026`; single month `2026-06` → `Jun 2026`; year `2025-12`…`2026-07` → `2025 – 2026`; year `2026-03`…`2026-06` → `2026` |
| same | `has no spending without group amounts` | only empty totals → `groups` `[]`, `hasSpending()` false, periods still continuous |
| `tests/Feature/TrendsTest.php` (new) | `redirects guests to login` | guest `get('/trends?by=year')` → redirect `route('login')` |
| same | `shows the empty state without imports` | `get(route('trends', ['by' => 'year']))` 200: sees `No trends yet`, `Upload statement`; no `data-chart` |
| same | `defaults to month mode over the imported range` | statements `2026-05` (number 5, groceries −1000) and `2026-06` (groceries −2000); `get(route('trends'))` 200: `<h1` contains `May 2026 – Jun 2026`; `data-mode="month"` segment has `aria-current="true"`; response contains `x-data="trendChart"` |
| same | `passes the monthly series to the chart` | June fixture + July; chart data: `labels` `['Jun 2026','Jul 2026']`; series keys in order `rent, utilities, groceries, subscriptions, hobbies, online_orders, takeaway, restaurants, health, other`; rent `['name' => 'Rent', 'color' => '#6366f1', 'values' => [38607, 10000]]`; groceries values `[29349, 1000]`; health values `[15065, 0]` |
| same | `shows months without data as zero` | statements `2026-03` (number 3, groceries −1000) and `2026-06` (number 6, groceries −2000) → labels `['Mar 2026','Apr 2026','May 2026','Jun 2026']`, groceries values `[1000, 0, 0, 2000]`; `<h1>` `Mar 2026 – Jun 2026` |
| same | `aggregates per year` | statement `2025-12` (number 12, groceries −9999) + June fixture + July; `get(route('trends', ['by' => 'year']))`: labels `['2025','2026']`; groceries values `[9999, 30349]`; rent values `[0, 48607]`; `<h1>` `2025 – 2026`; `data-mode="year"` segment `aria-current="true"`; aria-label contains `per year` |
| same | `shows years without data as zero` | statements `2024-05` (number 5, other −500) and `2026-01` (number 1, other −700) → `?by=year` labels `['2024','2025','2026']`, other values `[500, 0, 700]` |
| same | `links the mode toggle` | statement `2026-06`; month page: the `data-mode="year"` element is an `<a` with `href` = `route('trends', ['by' => 'year'])`; year page: the `data-mode="month"` element is an `<a` with `href` = `route('trends')` |
| same | `redirects unknown modes to month mode` (dataset `['by' => 'month']`, `['by' => 'week']`, `['by' => ['x']]`) | with a `2026-06` statement → `assertRedirect(route('trends'))` |
| same | `treats an empty mode as month mode` | with a `2026-06` statement, `get('/trends?by=')` → 200, `data-mode="month"` segment `aria-current="true"` |
| same | `hints at importing a second month` | only the June fixture: sees `Import another month to compare trends.`; `<h1>` `Jun 2026`; chart labels `['Jun 2026']` |
| same | `shows no hint with several months in one year` | statements `2026-05` (number 5) and `2026-06` (number 6), each with out −1000 `groceries` → `?by=year`: no `data-single-period`; `<h1>` `2026`; labels `['2026']` |
| same | `charts spending only` | `2026-06` with out −1000 groceries, in +5000 (group null), in +51540 `ignored` true, out −4000 `other` `ignored` true → series keys `['groceries']`, values `[1000]` |
| same | `shows no chart without spending` | `2026-06` with only in +5000 → `data-no-spending` with `No spending in Jun 2026`; no `data-chart`; hint present |
| same | `includes unassigned spending` | `2026-06` with out −700 `group_key` null → series keys `['unassigned']`, name `Unassigned`, color `#cbd5e1`, values `[700]` |
| `tests/Feature/OverviewTest.php` (unchanged) | all | still pass after Steps 1–2 |
| `tests/Feature/PagesTest.php` (unchanged) | `renders each app page for the user`, `links the empty states to upload` | still pass: empty DB → Trends shows `No trends yet` + `Upload statement` |

Extracting from HTML: toggle segment with `preg_match('/<(a|span)[^>]*data-mode="year"[^>]*>/', $html, $m)`; heading with `preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $m)` then `trim()`.

## Automated verification

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `vendor/bin/sail artisan test --compact` | All tests pass (Issues 1–6), 0 failures |
| 2 | Code style | `vendor/bin/sail bin pint --format agent` | No files changed |
| 3 | Frontend builds without size warning | `vendor/bin/sail npm run build 2>&1 \| grep -ci 'larger than'` | Prints `0`; a second `vendor/bin/sail npm run build` exits 0 |
| 4 | ECharts still one lazy chunk | `ls public/build/assets/ \| grep -E '^echarts-.*\.js$' \| wc -l` and `wc -c public/build/assets/app-*.js` | `1`; main `app-*.js` < 150000 bytes |
| 5 | Only modular imports | `grep -rn "from 'echarts'" resources/js` | No output |
| 6 | Legend drives totals | `grep -n "legendselectchanged" resources/js/trend-chart.js` | One match |
| 7 | Route | `vendor/bin/sail artisan route:list --name=trends` | `GET|HEAD trends` → `App\Http\Controllers\TrendsController`, middleware `auth` + `DiscardPendingStatementImport` |
| 8 | Guest redirect over HTTP | `curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "http://localhost/trends?by=year"` | `302 http://localhost/login` |
| 9 | Dev DB untouched by tests | Boost `database-query-mongodb`: count `statements` and `transactions` before and after check 1 | Same counts |
| 10 | No errors logged | Boost `last-error` and `read-log-entries` (last 20) | No new errors or exceptions |

## Manual verification

- [ ] Start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run build`; make sure rules are seeded (`vendor/bin/sail artisan db:seed --class=RuleSeeder --no-interaction`); log in at http://localhost.
- [ ] With nothing imported, Trends still shows the "No trends yet" card with "Upload statement".
- [ ] Import `tests/Fixtures/ing-2026-06.pdf` (assign the −200,00 € transfer to "Other"), confirm, open Trends → URL `/trends`, "Month" active, title "Jun 2026", one stacked bar labelled **1.686 €**, hint "Import another month to compare trends." below the chart.
- [ ] Add demo months (May with data, April as a gap, March with data): `vendor/bin/sail artisan tinker --execute '$s = App\Models\Statement::factory()->create(["number" => 5, "period" => "2026-05"]); App\Models\Transaction::factory()->for($s)->createMany([["period" => "2026-05", "group_key" => "groceries", "amount_cents" => -25000], ["period" => "2026-05", "group_key" => "takeaway", "amount_cents" => -9000], ["period" => "2026-05", "group_key" => "rent", "share_divisor" => 3, "merchant" => "Miete", "amount_cents" => -115820]]); $m = App\Models\Statement::factory()->create(["number" => 3, "period" => "2026-03"]); App\Models\Transaction::factory()->for($m)->create(["period" => "2026-03", "group_key" => "groceries", "amount_cents" => -12000]);'`
- [ ] Reload Trends → title "Mar 2026 – Jun 2026"; four bars Mar/Apr/May/Jun with top labels **120 €**, **0 €**, **726 €**, **1.686 €**; no hint; segments in group colours with Rent (indigo) at the bottom; y-axis in whole euros.
- [ ] Hover June → tooltip "Jun 2026", one row per group with exact amounts ("Rent 386,07 €", "Electricity & Gas 129,34 €", …), last row bold "Total 1.686,28 €". Hover April → only "Total 0,00 €". The hovered bar gets a light shadow.
- [ ] Legend below the chart lists the 10 groups with coloured dots. Click "Rent" → it turns grey, Rent segments disappear, labels become May **340 €** and June **1.300 €**, June tooltip Total **1.300,21 €**. Click "Rent" again → back to 726 € / 1.686 €.
- [ ] Hide several groups at once → labels and tooltip totals always equal the sum of the visible segments. Reload → all groups visible again.
- [ ] Click "Year" → URL `/trends?by=year`, title "2026", one bar **2.532 €**. Click "Month" → back to `/trends`.
- [ ] Typing `/trends?by=week` lands on `/trends`.
- [ ] Remove the demo data afterwards: `vendor/bin/sail artisan tinker --execute 'App\Models\Statement::whereIn("period", ["2026-03", "2026-05"])->get()->each(function ($s) { $s->transactions()->delete(); $s->delete(); });'`
- [ ] Overview is unchanged: Month|Year toggle looks and works exactly as before.
- [ ] Resize the window (wide ↔ narrow): the chart resizes with its card, bars get narrower, axis labels thin out without overlapping, nothing overflows.
- [ ] Light mode: white card, slate axis labels, faint grid lines, grey bar-top labels, hidden legend items light grey.
- [ ] Dark mode: switch the OS theme **while the page is open** (with one group hidden) → axis labels, grid lines, legend, tooltip and bar labels switch to the dark palette without reload; the hidden group stays hidden and the bar labels still match the visible segments.
- [ ] Narrow window (~375 px): toggle and title wrap cleanly; the legend is one row with paging arrows; no horizontal page scroll.
- [ ] Keyboard: Tab reaches the inactive Month/Year segment with a visible emerald focus outline; Enter follows it.
- [ ] Network tab: the `echarts-*.js` chunk loads on Trends and Overview, not on Upload.

## Requirement traceability

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| Month \| Year toggle (same component as Overview) | Steps 2, 4, 6 | `links the mode toggle`, `defaults to month mode over the imported range`, `aggregates per year`, `redirects unknown modes to month mode`, `OverviewTest` (unchanged) | #1, #7; manual: Year/Month click, Overview unchanged, keyboard |
| Stacked bar chart: one bar per month (or year), segments = groups | Steps 3, 4, 5, 6 | `builds a continuous month axis…`, `folds months into years`, `lists only groups with spending in config order`, `passes the monthly series to the chart`, `aggregates per year`, `charts spending only`, `includes unassigned spending` | #1, #3–#5; manual: four bars, colours, Year bar |
| Click legend items to hide/show groups (totals update) | Step 5 | `sums the groups per period` (base totals) | #6; manual: legend clicks, several groups, dark-mode switch with hidden group |
| Range: all periods with data | Steps 1, 3, 4 | `builds a continuous month axis…`, `steps months across a year boundary`, `fills missing years with zeros`, `labels the range`, `defaults to month mode over the imported range` | #1; manual: title "Mar 2026 – Jun 2026" |
| Reuses the Issue 5 aggregation service (grouped by period) | Steps 3, 4 | `passes the monthly series to the chart`, `aggregates per year`, `charts spending only` | #1 |
| Tests: series shape per granularity | Step 7 | `TrendSeriesTest` (month + year), `passes the monthly series to the chart`, `aggregates per year` | #1 |
| Tests: months without data shown as zero | Step 7 | `builds a continuous month axis with zeros for missing months`, `fills missing years with zeros`, `shows months without data as zero`, `shows years without data as zero` | #1; manual: April bar `0 €` |
| Done when: with ≥2 imported months you can compare groups month over month and toggle groups on and off | Steps 1–6 | `passes the monthly series to the chart`, `shows months without data as zero` | #1, #6; manual: demo months, legend toggling |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] With the June fixture and at least one more month, `/trends` shows one stacked bar per month from the first to the last imported month (gaps as 0), segments in group colours, whole-euro totals on top.
- [ ] Clicking legend items hides/shows groups and the bar-top totals and tooltip totals follow the visible groups.
- [ ] `/trends?by=year` shows one bar per year; the Month|Year toggle looks and behaves like on Overview.
