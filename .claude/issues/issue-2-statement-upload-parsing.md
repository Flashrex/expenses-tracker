# Issue 2: Statement upload & parsing (no grouping yet)

> Source: `.claude/project-plan.md` › "Issue 2 – Statement upload & parsing (no grouping yet)"
> Planned: 2026-09-28
> Depends on: Issue 1 (`.claude/issues/issue-1-foundation.md`): auth, layouts, top bar, Upload page, `tests/Pest.php` with `DatabaseMigrations`, `config/expenses.php`.

## Goal

Upload an ING "Kontoauszug" PDF, parse it server-side (the PDF is thrown away right after parsing), review all parsed entries in a compact table, and store the statement and its entries only when the user confirms. Re-uploading a statement that already exists offers to replace it. The Upload page lists already imported months.

Original "Done when": **uploading the June 2026 statement shows 69 entries and confirming stores them. Re-uploading asks to replace.**

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run every PHP, Artisan, Composer, Node and test command through Sail: `vendor/bin/sail …`. Host PHP lacks `ext-mongodb`, so host `php artisan` fails. If containers are down, run `vendor/bin/sail up -d` first.
- Issue 1 must be fully implemented first (check: `routes/web.php` has the `upload` route and `resources/views/components/layouts/app.blade.php` exists). If it is not, stop and report.
- Load these project skills before writing the matching code: `laravel-best-practices` (models, migrations, controllers, requests, middleware, services), `tailwindcss-development` (Blade views), `testing-best-practices` (Pest tests).
- Use Boost `search-docs` for any framework or package API you are unsure about (Laravel 13, laravel-mongodb 5.11, Pest 5, Tailwind v4, Alpine 3).
- After editing PHP files run `vendor/bin/sail bin pint --dirty --format agent`.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| PHP (Sail runtime) | 8.4, with `zlib`, `iconv`, `mbstring` (checked in the container) | `compose.yaml`, `vendor/bin/sail exec laravel.test php -m` |
| Laravel | 13.33.0 | `composer.lock` |
| MongoDB driver | `mongodb/laravel-mongodb` 5.11.0 (`mongodb/mongodb` 2.4.2); server `mongodb/mongodb-atlas-local:8.0`, a replica set, so multi-document transactions work (`DB::transaction()`, implemented in `MongoDB\Laravel\Concerns\ManagesTransactions`) | `composer.lock`, `compose.yaml`, vendor source |
| Tests | Pest 5.2.1; Feature tests use `DatabaseMigrations` (set up in Issue 1). `RefreshDatabase` is not supported by laravel-mongodb | `composer.lock`, Issue 1 plan |
| Session | `mongodb` driver locally (tests: `array`), lifetime 120 min, `expire_on_close` false | `config/session.php`, `.env.example`, `phpunit.xml` |
| Frontend | Tailwind v4 (CSS-first, `resources/css/app.css`), Alpine ^3.17 started in `resources/js/app.js` (Issue 1), heroicons via `blade-ui-kit/blade-heroicons` (Issue 1) | Issue 1 plan |
| PDF parsing | **new:** `smalot/pdfparser` ^2.12 (resolves to v2.12.5; pure PHP; needs `ext-zlib`, `ext-iconv`; licence LGPL-3.0, used unmodified as a library) | spike (see below) |
| `pdftotext` / poppler | **Not available** on the host or in the Sail container, so no fallback to it | `which pdftotext` |

### Current state of the codebase

State after Issue 1 (per `.claude/issues/issue-1-foundation.md`):

- `routes/web.php`: guest group (`login`, `login.store`), auth group (`logout`, `home`, `overview`, `trends`, and `Route::view('/upload', 'pages.upload')->name('upload')`).
- `resources/views/components/layouts/app.blade.php` (`<x-layouts.app title="…">`), top bar with `<x-nav-link>` for Overview/Trends/Upload; the Upload link is active via `request()->routeIs('upload')`.
- `resources/views/components/empty-state.blade.php`; the "primary button" class string is defined in Issue 1 Step 6 and written inline.
- `resources/views/pages/upload.blade.php`: a dashed-border wrapper with the empty state "Statement upload arrives soon". **Replaced in this issue.**
- `tests/Feature/PagesTest.php`: dataset row `upload` / "Statement upload arrives soon". **Updated in this issue.**
- `app/Models/User.php` extends `MongoDB\Laravel\Auth\User`. No other models, no `app/Services`, no `app/Support`, no `app/Http/Middleware`, no `tests/Fixtures`.
- Migrations: `database/migrations/0001_01_01_00000{0,1,2}_*.php` use `MongoDB\Laravel\Schema\Blueprint` with `Schema::create('<collection>', function (Blueprint $collection) { $collection->unique('email'); … })`. Follow this style.
- The example statement lives at `.claude/2169001563_Kontoauszug_20260701_redacted.pdf` (270 KB, 8 pages).

### Parser spike results (already done while planning; do not repeat as a separate step)

Extraction was tested with `smalot/pdfparser` v2.12.5 on the fixture:

- `$page->getText()` returns lines, but glues type and counterparty together ("LastschriftRhoenEnergie Fulda GmbH") and drops spaces in purpose lines ("IhrEinkaufbeiSpotifyAB"). **Do not use `getText()`.**
- `$page->getDataTm()` returns word-level chunks: `[[a, b, c, d, x, y], text]` (matrix values are strings; `x = (float) $chunk[0][4]`, `y = (float) $chunk[0][5]`, y grows upwards). This keeps word spacing ("Ihr Einkauf bei Spotify AB") with occasional stray splits ("LOTTO He ssen GmbH", "Takeaway .com"), which Issue 3's normalization handles. **Use `getDataTm()`.**
- Some chunks contain **non-breaking spaces (U+00A0)**, for example "Neuer\u{00A0}Saldo", "Kontoauszug\u{00A0}Juni\u{00A0}2026", "Girokonto\u{00A0}Nummer". Replace U+00A0 with a normal space and `trim()` every chunk before anything else.
- Column positions (points) on every page:

| Content | x |
|---|---|
| Booking date (entry line) / value date (2nd line), "Girokonto Nummer", "Kontoauszug Juni 2026", "Abschluss für Konto" block | 70.8 |
| Legal footer (page 1) | 69.3 |
| Type ("Lastschrift", "Gutschrift/Dauerauftrag", …) and purpose lines | 141.6 |
| Counterparty (same line as type) | 195.0 |
| Amount (right-aligned) | 488–533 |
| Page-1 header labels "Datum", "Auszugsnummer", "Alter Saldo", "Neuer Saldo" | 311.7 (values on the same y at 488–547) |
| Page 2–7 "Datum" / "Seite" labels | 425.1 |

- A purpose line sits 0.3 pt lower than the date chunk on the same visual line, so chunks must be grouped into lines with a y tolerance of 1.0 pt.
- Table header line on every page: "Buchung", "Buchung / Verwendungszweck", "Betrag (EUR)", followed by a line "Valuta".
- The last entry ("Abschluss −0,05") is followed by a table line "Neuer Saldo 1.952,30" (x = 141.6), then the "Abschluss für Konto" summary block (x = 70.8, contains another "Abschluss −0,05" and a "−0,05" Dispo line), "Kunden-Information", and page 8 with legal text. Everything from that "Neuer Saldo" line onward must be ignored.
- A prototype following the algorithm in Step 4 produced exactly **69 entries**, sum **−319,72 € = 1.952,30 − 2.272,02**, header number 6, period "Juni 2026", date 30.06.2026.

### Domain rules that apply

- ING only; the parser is built for the ING layout, no multi-bank abstraction.
- Money is stored as integer cents (signed for entries: negative = outgoing).
- Month attribution = statement month: every entry in "Kontoauszug Juni 2026" gets `period = "2026-06"`, regardless of its booking date.
- Data model (MongoDB collections):
  - `statements`: `number` (Auszugsnummer), `period` (`YYYY-MM`), `statement_date`, `old_balance_cents`, `new_balance_cents`, `confirmed_at`.
  - `transactions`: `statement_id`, `period`, `booked_on`, `value_on`, `type`, `counterparty`, `purpose`, `merchant` (derived), `amount_cents` (signed), `direction` (`in`/`out`), `group_key` (nullable), `share_divisor` (default 1), `ignored` (bool), `rule_id` (nullable).
- Entries are editable only during upload; once confirmed they are final (no later editing/deleting except the Replace flow of this issue).
- Balance validation on import is out of scope: a mismatch between the entry sum and the balances does **not** block an import (the fixture test still asserts the sum).

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | Text extraction | `smalot/pdfparser` with `getDataTm()` word chunks and position-based parsing (spike above). No `pdftotext`. |
| 2 | Merchant for VISA and PayPal entries | Strip codes **and** legal suffixes. Exact algorithm in Step 3; expected values in the Tests table. |
| 3 | Merchant for entries without counterparty | First purpose line (max 60 characters); if that is empty, the type. E.g. "Netflix + Router", "Miete", "Echtzeitüberweisung", "Abschluss". |
| 4 | Where the pending import lives | In the session under key `statement_import`, as plain arrays. The PDF is never stored: it is parsed from the uploaded temp file, which PHP deletes at the end of the request. |
| 5 | "Leaving without confirming" | Middleware drops `statement_import` on every request inside the auth group except `upload.review`, `upload.confirm`, `upload.discard`. Reloading the review page keeps the data. A "Discard" button on the review page drops it too. Closing the tab: the session expires. No `beforeunload` beacon. |
| 6 | Duplicate (same `number` + `period` already stored) | No separate prompt screen. The review page shows an amber banner "June 2026 (statement 6) is already imported. Confirming replaces it." with a "Cancel" button (discards), and the confirm button reads "Replace import". Old statement + its transactions are deleted only on confirm, inside the same DB transaction that stores the new ones. |
| 7 | Number and date formats | German, like the bank: amounts `−1.158,20 €` (U+2212 minus, red) / `+1.348,19 €` (green); balances `2.272,02 €` (minus only when negative). Table dates `01.06.`. Period titles "June 2026", chips "Jun 2026" (English month names). |
| 8 | Review page head | Summary card: title "June 2026", subline "Statement 6 · 69 entries", then four figures: Old balance, New balance, In (sum of positive amounts), Out (sum of negative amounts). Buttons "Discard" (secondary) and "Confirm import" / "Replace import" (primary). The card is sticky below the top bar. |
| 9 | Review table columns | Date (booking date), Merchant, Amount. Nothing else (compact, as in the issue). |
| 10 | After confirm | Redirect to `/upload` with a green success banner "June 2026 imported · 69 entries" (same text for a replace); the new chip appears. |
| 11 | Upload validation | Field `statement`: required, file, `mimes:pdf`, `max:10240` (10 MB). All of these errors show one message: "Please choose a PDF file (max. 10 MB)." A PDF that is not a readable ING statement: "This doesn't look like an ING statement." Both shown under the drop zone. |
| 12 | Upload interaction | Auto-submit as soon as a file is dropped or picked; the drop zone then shows a spinner and "Reading statement…". Without JS a visible submit button is available (`<noscript>`). |
| 13 | Imported months | Read-only chips "Jun 2026" for every confirmed statement, newest first, under the heading "Imported" below the drop zone. Section hidden when there are none. |
| 14 | Discard target | "Discard"/"Cancel" redirect to `/upload` without a message. |

## Scope

### In scope

- Install `smalot/pdfparser`.
- `statements` and `transactions` collections (migrations with indexes), `Statement` and `Transaction` models with factories.
- ING parser (`IngStatementParser`), merchant derivation (`MerchantDeriver`), parse exception, value objects for parsed data.
- Money and period formatting helpers.
- Upload page with drag & drop / file picker, errors, success banner, imported-month chips.
- Review page with summary card, table, duplicate banner, Confirm/Replace and Discard.
- Confirm (store, with replace) and discard; middleware that discards on leaving.
- Test fixture `tests/Fixtures/ing-2026-06.pdf`; unit and feature tests.

### Out of scope

- Rules, the rule seeder, text normalization for matching, group chips / "÷3" / "ignored" badges on the review screen (Issue 3). In this issue every stored transaction gets `group_key = null`, `share_divisor = 1`, `ignored = false`, `rule_id = null`.
- Review queue, group picker, "Always use this group" (Issue 4).
- Anything on Overview/Trends (Issues 5, 6). They keep their Issue 1 empty states.
- Editing or deleting entries or statements after confirm (except the Replace flow), re-running rules, keeping PDFs, other banks, balance validation on import, export.
- A separate duplicate prompt screen (Decision 6), a `beforeunload` beacon (Decision 5).

## Prerequisites

Containers running:

```sh
vendor/bin/sail up -d
```

| Package | Constraint | Manager |
|---|---|---|
| `smalot/pdfparser` | `^2.12` | Composer (runtime dependency) |

No new `.env` keys. No npm packages.

## Implementation steps

### Step 1: Install the PDF parser and add the fixture

**Files**
- Modify: `composer.json`, `composer.lock` (via Composer)
- New: `tests/Fixtures/ing-2026-06.pdf` — byte-identical copy of `.claude/2169001563_Kontoauszug_20260701_redacted.pdf`

**Commands**

```sh
vendor/bin/sail composer require smalot/pdfparser:^2.12
mkdir -p tests/Fixtures
cp .claude/2169001563_Kontoauszug_20260701_redacted.pdf tests/Fixtures/ing-2026-06.pdf
```

**Step check:** `vendor/bin/sail composer show smalot/pdfparser` shows v2.12.x; `cmp .claude/2169001563_Kontoauszug_20260701_redacted.pdf tests/Fixtures/ing-2026-06.pdf` prints nothing.

### Step 2: Collections, models, factories

**Files**
- New: `database/migrations/2026_09_28_100000_create_statements_collection.php`
- New: `database/migrations/2026_09_28_100001_create_transactions_collection.php`
- New: `app/Models/Statement.php`
- New: `app/Models/Transaction.php`
- New: `database/factories/StatementFactory.php`
- New: `database/factories/TransactionFactory.php`

Generate with `vendor/bin/sail artisan make:model Statement --factory --no-interaction` and `… make:model Transaction --factory --no-interaction`, create the migrations by hand in the style of `0001_01_01_000000_create_users_table.php` (`MongoDB\Laravel\Schema\Blueprint $collection`).

**Migrations**

- `statements`: `$collection->unique(['number', 'period']);` `$collection->index('period');` `down()`: `Schema::dropIfExists('statements')`.
- `transactions`: `$collection->index('statement_id');` `$collection->index('period');` `down()`: `Schema::dropIfExists('transactions')`.

**`Statement`** (`App\Models\Statement extends MongoDB\Laravel\Eloquent\Model`, `use HasFactory`):

- `#[Fillable(['number', 'period', 'statement_date', 'old_balance_cents', 'new_balance_cents', 'confirmed_at'])]` (attribute style like `User`).
- `casts()`: `number => 'integer'`, `statement_date => 'date'`, `old_balance_cents => 'integer'`, `new_balance_cents => 'integer'`, `confirmed_at => 'datetime'`.
- `transactions(): HasMany` → `$this->hasMany(Transaction::class)` (foreign key `statement_id`).

**`Transaction`** (`App\Models\Transaction extends MongoDB\Laravel\Eloquent\Model`, `use HasFactory`):

- Fillable: `statement_id, period, booked_on, value_on, type, counterparty, purpose, merchant, amount_cents, direction, group_key, share_divisor, ignored, rule_id`.
- `casts()`: `booked_on => 'date'`, `value_on => 'date'`, `amount_cents => 'integer'`, `share_divisor => 'integer'`, `ignored => 'boolean'`.
- `$attributes = ['group_key' => null, 'share_divisor' => 1, 'ignored' => false, 'rule_id' => null];`
- `statement(): BelongsTo`.

**Factories**

- `StatementFactory`: `number => 6`, `period => '2026-06'`, `statement_date => '2026-06-30'`, `old_balance_cents => 227202`, `new_balance_cents => 195230`, `confirmed_at => now()`.
- `TransactionFactory`: `statement_id => Statement::factory()`, `period => '2026-06'`, `booked_on => '2026-06-01'`, `value_on => '2026-06-01'`, `type => 'Lastschrift'`, `counterparty => 'VISA TEGUT FILIALE 5020'`, `purpose => 'NR XXXX 9533 FULDA DE KAUFUMSATZ'`, `merchant => 'TEGUT'`, `amount_cents => -399`, `direction => 'out'`.

**Commands**

```sh
vendor/bin/sail artisan migrate --no-interaction
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** `vendor/bin/sail artisan migrate:status` lists both new migrations as ran; Boost `database-schema` (or `database-query-mongodb` `listIndexes`) shows the unique index on `statements` `{number: 1, period: 1}`.

### Step 3: Parsed-data value objects, exception, merchant derivation

**Files**
- New: `app/Services/Statements/ParsedEntry.php`
- New: `app/Services/Statements/ParsedStatement.php`
- New: `app/Services/Statements/StatementParseException.php`
- New: `app/Services/Statements/MerchantDeriver.php`

These classes must **not** use the Laravel container, facades or helpers that need the app (`config()`, `app()`, `now()`), because they are unit-tested without booting Laravel. `Illuminate\Support\Str` static methods and plain PHP are fine.

**`ParsedEntry`** — `final readonly class` with constructor properties:
`string $bookedOn` (`Y-m-d`), `string $valueOn` (`Y-m-d`), `string $type`, `?string $counterparty` (null when the line has none), `string $purpose` (purpose lines joined with `"\n"`, `''` when none), `string $merchant`, `int $amountCents`.
Methods: `direction(): string` → `'out'` if `amountCents < 0`, else `'in'`; `toArray(): array` (keys `booked_on`, `value_on`, `type`, `counterparty`, `purpose`, `merchant`, `amount_cents`, `direction`); `static fromArray(array $data): self`.

**`ParsedStatement`** — `final readonly class`:
`int $number`, `string $period` (`YYYY-MM`), `string $statementDate` (`Y-m-d`), `int $oldBalanceCents`, `int $newBalanceCents`, `array $entries` (`list<ParsedEntry>`, add the PHPDoc).
Methods: `toArray()` (keys `number`, `period`, `statement_date`, `old_balance_cents`, `new_balance_cents`, `entries` → list of entry arrays), `static fromArray(array $data): self`, `incomingCents(): int` (sum of positive amounts), `outgoingCents(): int` (sum of negative amounts, negative number).

**`StatementParseException`** — `final class StatementParseException extends \RuntimeException`.

**`MerchantDeriver`** — `final class` with `derive(string $type, ?string $counterparty, string $purpose): string`. Rules, in order:

1. `$counterparty` null or `''`: take the first line of `$purpose` (split on `"\n"`), trimmed. If empty, use `$type`. Cut to 60 characters (`mb_substr`), `rtrim`. Go to rule 5.
2. `$counterparty` matches `/^PayPal\b/i`: join purpose lines with a single space; if it matches `/Ihr\s*Einkauf\s*bei\s+(.+?)(?:\s+Mandat:|\s+Referenz:|$)/u`, use group 1; else use `$counterparty`. Go to rule 5.
3. `$counterparty` starts with `VISA ` (exactly, case-sensitive): remove that prefix, then in order: remove a leading `/^(PAYPAL\s*\*|UZR\*)\s*/i`; remove `/\*.*$/` (first `*` and everything after); trim; remove `/\s+FILIALE\s+\d+$/i`; remove `/,.*$/`; trim; remove `/\s+\d+$/`. Go to rule 5.
4. Otherwise use `$counterparty`.
5. Legal suffixes: repeat until nothing changes: `preg_replace('/[\s,]+(?:&|G\s?m\s?b\s?H|AG|AB|B\.\s?V\.?|SE|KG|S\.C\.A)[\s.,]*$/iu', '', $m)`. Then `trim($m, " ,.&")`.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** the `MerchantDeriverTest` cases (Tests table) pass once written in Step 9; for now `vendor/bin/sail artisan tinker --execute "echo (new App\Services\Statements\MerchantDeriver)->derive('Lastschrift', 'VISA TEGUT FILIALE 5020', '');"` prints `TEGUT`.

### Step 4: ING statement parser

**Files**
- New: `app/Services/Statements/IngStatementParser.php`

`final class IngStatementParser`, constructor `public function __construct(private MerchantDeriver $merchants = new MerchantDeriver) {}`, method `parse(string $path): ParsedStatement`. Same no-container rule as Step 3.

**Algorithm**

1. `(new \Smalot\PdfParser\Parser)->parseFile($path)`; wrap in `try`/`catch (\Throwable $e)` → `throw new StatementParseException('Unreadable PDF.', previous: $e)`.
2. For each page (`$document->getPages()`, keep the page index): map `getDataTm()` to `['x' => (float) $c[0][4], 'y' => (float) $c[0][5], 't' => trim(str_replace("\u{00A0}", ' ', $c[1]))]`, drop chunks with `t === ''`, sort by `y` descending then `x` ascending, group into lines (a chunk joins the current line when `abs($lineY - $chunk['y']) <= 1.0`, where `$lineY` is the y of the line's first chunk), sort each line by `x`.
3. **Header** (collect across pages, first value wins):
   - Period: first chunk matching `/^Kontoauszug (Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember) (\d{4})$/u` → `YYYY-MM` via a German month map (Januar → 01 … Dezember → 12).
   - On the **first page only**, for a line whose first chunk has `x > 300` and text `Datum`, `Auszugsnummer`, `Alter Saldo` or `Neuer Saldo`: value = text of the line's last chunk. `Datum` → `d.m.Y` → `Y-m-d`; `Auszugsnummer` → int; saldo values have the form `2.272,02 Euro` → strip ` Euro` → cents.
4. **Entries**: per page, state `inTable = false`; global state `done = false`, `current = null`.
   - Line text `= implode(' ', texts)`. If `done`, skip.
   - Line starting with `Buchung Buchung / Verwendungszweck` → `inTable = true`, continue. Skip lines before that and the line `Valuta`.
   - Definitions: `isDate` = first chunk `x < 100` and matches `/^\d{2}\.\d{2}\.\d{4}$/`; `hasAmount` = last chunk `x >= 480` and matches `/^-?\d{1,3}(\.\d{3})*,\d{2}$/`.
   - First chunk text `Neuer Saldo` with `100 <= x < 480` → close `current`, `done = true`.
   - `isDate && hasAmount` → close `current`; start a new entry: booking date = first chunk; type = second chunk's text; counterparty = the remaining chunks between type and amount joined with `' '` (null if none); amount = last chunk.
   - Else if `current` exists and has no value date yet and `isDate` → value date = first chunk; the remaining chunks (if any) form purpose line 1.
   - Else if `current` exists and first chunk `100 <= x < 480` → another purpose line (whole line text).
   - Else → close `current` (footer, "Girokonto Nummer", legal text, …).
   - At the end of each page close `current`.
   - Closing an entry builds a `ParsedEntry`: dates `d.m.Y` → `Y-m-d`; value date falls back to the booking date if the second line was missing; amount `'-1.158,20'` → `-115820` (remove `.`, replace `,` with nothing after validating the format, cast to int); purpose = lines joined with `"\n"`; merchant = `MerchantDeriver::derive(type, counterparty, purpose)`.
5. Throw `StatementParseException` (message naming what is missing) if any header field (number, period, statement date, old balance, new balance) is missing or no entries were found.

Do **not** compare the entry sum with the balances (out of scope); the test does that for the fixture only.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan tinker --execute "\$s = (new App\Services\Statements\IngStatementParser)->parse(base_path('tests/Fixtures/ing-2026-06.pdf')); echo count(\$s->entries), ' ', \$s->period, ' ', array_sum(array_map(fn (\$e) => \$e->amountCents, \$s->entries));"
```

**Step check:** tinker prints `69 2026-06 -31972`.

### Step 5: Formatting helpers

**Files**
- New: `app/Support/Money.php`
- New: `app/Support/Period.php`

**`Money`** — `final class Money`, `public static function format(int $cents, bool $signed = false): string`:
- Absolute value formatted with `number_format($abs / 100, 2, ',', '.')` + `' €'`.
- Prefix: `'−'` (U+2212) when `$cents < 0`; `'+'` when `$signed` and `$cents > 0`; nothing otherwise.
- Examples: `format(-115820, true)` → `−1.158,20 €`; `format(134819, true)` → `+1.348,19 €`; `format(227202)` → `2.272,02 €`; `format(-5)` → `−0,05 €`; `format(0, true)` → `0,00 €`.

**`Period`** — `final class Period`:
- `label(string $period): string` → `"June 2026"`; `short(string $period): string` → `"Jun 2026"`. Use `Carbon\CarbonImmutable::createFromFormat('!Y-m', $period)->locale('en')->isoFormat('MMMM YYYY')` / `'MMM YYYY'`.

**Step check:** covered by unit tests in Step 9.

### Step 6: Routes, middleware, request, controller

**Files**
- New: `app/Http/Middleware/DiscardPendingStatementImport.php`
- New: `app/Http/Requests/StoreStatementUploadRequest.php`
- New: `app/Http/Controllers/StatementUploadController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/layouts/app.blade.php` — Upload nav link active also on `upload.*`

**Routes** (all inside the existing `auth` group; replace the Issue 1 `Route::view('/upload', …)` line):

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/upload` | `upload` | `StatementUploadController@create` |
| POST | `/upload` | `upload.store` | `StatementUploadController@store` |
| GET | `/upload/review` | `upload.review` | `StatementUploadController@review` |
| POST | `/upload/confirm` | `upload.confirm` | `StatementUploadController@confirm` |
| POST | `/upload/discard` | `upload.discard` | `StatementUploadController@discard` |

Change the auth group to `Route::middleware(['auth', DiscardPendingStatementImport::class])->group(…)`. In the layout, the Upload `<x-nav-link>` gets `:active="request()->routeIs('upload', 'upload.*')"`.

**`DiscardPendingStatementImport`**: `const SESSION_KEY = 'statement_import';` `handle()`: unless `$request->routeIs('upload.review', 'upload.confirm', 'upload.discard')`, call `$request->session()->forget(self::SESSION_KEY)`; then `return $next($request)`. (Put the session key constant here and reference it from the controller.)

**`StoreStatementUploadRequest`**: `authorize()` → `true`; `rules()` → `['statement' => ['required', 'file', 'mimes:pdf', 'max:10240']]`; `messages()` → the same text for `statement.required`, `statement.file`, `statement.mimes`, `statement.max`, `statement.uploaded`: `"Please choose a PDF file (max. 10 MB)."`.

**`StatementUploadController`** (`extends Controller`):

- `create(): View` → `view('pages.upload', ['imported' => Statement::query()->whereNotNull('confirmed_at')->orderByDesc('period')->pluck('period')])`.
- `store(StoreStatementUploadRequest $request, IngStatementParser $parser): RedirectResponse`:
  - `try { $parsed = $parser->parse($request->file('statement')->getRealPath()); } catch (StatementParseException) { return back()->withErrors(['statement' => "This doesn't look like an ING statement."]); }`
  - Do **not** call `store()`/`move()` on the upload and do not `unlink` it (PHP deletes the temp file after the request).
  - `session([SESSION_KEY => $parsed->toArray()])`; `return redirect()->route('upload.review')`.
- `review(): View|RedirectResponse`: no pending data → `redirect()->route('upload')`. Otherwise `$statement = ParsedStatement::fromArray(...)`; `$existing = Statement::query()->where('number', $statement->number)->where('period', $statement->period)->exists()`; `view('pages.upload-review', compact('statement', 'existing'))`.
- `confirm(): RedirectResponse`: no pending data → `redirect()->route('upload')`. Otherwise:

  ```php
  DB::transaction(function () use ($parsed) {
      Statement::query()->where('number', $parsed->number)->where('period', $parsed->period)
          ->get()->each(function (Statement $old) {
              $old->transactions()->delete();
              $old->delete();
          });
      $statement = Statement::create([... 'confirmed_at' => now()]);
      $statement->transactions()->createMany(/* one row per entry: statement fields + period, group_key null, share_divisor 1, ignored false, rule_id null */);
  });
  session()->forget(SESSION_KEY);
  return redirect()->route('upload')->with('status', Period::label($parsed->period).' imported · '.count($parsed->entries).' entries');
  ```

  `DB` is `Illuminate\Support\Facades\DB`; the default connection is `mongodb`, whose `transaction()` starts a MongoDB session transaction (replica set available locally and in tests).
- `discard(): RedirectResponse`: forget the key, `redirect()->route('upload')`.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan route:list --path=upload
```

**Step check:** `route:list --path=upload` shows the 5 routes with names above and middleware `web`, `auth`, `App\Http\Middleware\DiscardPendingStatementImport`.

### Step 7: Upload page

**Files**
- Modify (rewrite): `resources/views/pages/upload.blade.php`

`<x-layouts.app title="Upload">`, content in `<div class="mx-auto max-w-2xl space-y-6">`:

1. **Success banner** (`@if (session('status'))`): `<div role="status" class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300">` + `<x-heroicon-o-check-circle class="size-5 shrink-0" />` + `{{ session('status') }}`.
2. **Drop zone form**: `<form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" x-data="{ dragging: false, busy: false }" x-ref="form">` + `@csrf`.
   - `<label for="statement" …>` as the drop area: `flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center transition dark:border-slate-700 dark:bg-slate-900`, with `:class="dragging ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-400/10' : 'hover:border-slate-400 dark:hover:border-slate-600'"`, `@dragover.prevent="dragging = true"`, `@dragleave.prevent="dragging = false"`, `@drop.prevent="dragging = false; $refs.input.files = $event.dataTransfer.files; busy = true; $refs.form.requestSubmit()"`.
   - Idle content (`x-show="!busy"`): icon bubble as in the Issue 1 empty state with `<x-heroicon-o-arrow-up-tray class="size-7" />`, `<p class="mt-4 text-lg font-semibold">Drop your ING statement here</p>`, `<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">or click to choose a PDF</p>`.
   - Busy content (`x-show="busy" x-cloak`): `<x-heroicon-o-arrow-path class="size-7 animate-spin text-emerald-600 dark:text-emerald-400" />` + `<p class="mt-4 text-sm font-medium">Reading statement…</p>`.
   - `<input id="statement" name="statement" type="file" accept="application/pdf,.pdf" class="sr-only" x-ref="input" @change="busy = true; $refs.form.requestSubmit()">`.
   - `<noscript><button type="submit" class="mt-4 …primary button classes…">Upload</button></noscript>`.
   - `@error('statement') <p role="alert" class="mt-3 text-center text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror`.
   - Add `[x-cloak] { display: none !important; }` inside the `@layer base` block of `resources/css/app.css`.
3. **Imported** (`@if ($imported->isNotEmpty())`): `<section>` with `<h2 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400"><x-heroicon-o-archive-box class="size-4" /> Imported</h2>` and `<ul class="flex flex-wrap gap-2">` of `<li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm dark:border-slate-800 dark:bg-slate-900"><x-heroicon-m-check class="size-4 text-emerald-600 dark:text-emerald-400" />{{ \App\Support\Period::short($period) }}</li>`. Not links, no hover state.

The text "Upload statement" must not appear on this page (Issue 1 test).

**Commands**

```sh
vendor/bin/sail npm run build
```

**Step check:** logged in, `/upload` renders the drop zone (check via the feature test in Step 9 or Boost `get-absolute-url` + browser).

### Step 8: Review page

**Files**
- New: `resources/views/pages/upload-review.blade.php`

`<x-layouts.app title="Review">`, content in `<div class="mx-auto max-w-3xl space-y-4">`:

1. **Duplicate banner** (`@if ($existing)`): `<div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">` with `<x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />` and the text `{{ Period::label($statement->period) }} (statement {{ $statement->number }}) is already imported. Confirming replaces it.`, plus a `<form method="POST" action="{{ route('upload.discard') }}">@csrf<button type="submit" class="…secondary button…">Cancel</button></form>`.
2. **Summary card** (sticky): `sticky top-16 z-[5] rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-sm backdrop-blur sm:p-6 dark:border-slate-800 dark:bg-slate-900/90`.
   - Row 1: left `<h1 class="text-lg font-semibold">June 2026</h1>` (`Period::label`) and `<p class="text-sm text-slate-500 dark:text-slate-400">Statement 6 · 69 entries</p>`; right two forms side by side: `upload.discard` button "Discard" (secondary), `upload.confirm` button with `<x-heroicon-o-check class="size-5" />` and "Confirm import" or, when `$existing`, "Replace import" (primary).
   - Row 2: `<dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">` with Old balance (`Money::format(old)`), New balance (`Money::format(new)`), In (`Money::format($statement->incomingCents(), true)`, emerald), Out (`Money::format($statement->outgoingCents(), true)`, rose). `<dt>` `text-xs text-slate-500 dark:text-slate-400`, `<dd>` `mt-1 font-semibold tabular-nums`.
3. **Table** in a card `overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900`: `<table class="w-full text-sm">` with a visually hidden `<thead class="sr-only">` (Date, Merchant, Amount); rows in statement order, `divide-y divide-slate-100 dark:divide-slate-800`:
   - Date cell: `\Illuminate\Support\Carbon::parse($entry->bookedOn)->format('d.m.')`, `w-16 px-4 py-2.5 text-slate-500 tabular-nums dark:text-slate-400`.
   - Merchant cell: `$entry->merchant`, `px-2 py-2.5 font-medium`, `truncate` with `max-w-0 w-full` so long names do not break the layout; full text in `title`.
   - Amount cell: `Money::format($entry->amountCents, true)`, `whitespace-nowrap px-4 py-2.5 text-right font-medium tabular-nums`, `text-rose-600 dark:text-rose-400` for out, `text-emerald-600 dark:text-emerald-400` for in.

**Secondary button style** (write inline): `inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-emerald-400`. Primary button: the Issue 1 class string.

Use `@use('App\Support\Money')` and `@use('App\Support\Period')` at the top of the view.

**Commands**

```sh
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** covered by the feature tests in Step 9.

### Step 9: Tests

**Files**
- New: tests listed in the Tests table (create with `vendor/bin/sail artisan make:test --pest <Name> --no-interaction`, add `--unit` for Unit tests).
- Modify: `tests/Feature/PagesTest.php` — dataset row for `upload` becomes `upload` / "Drop your ING statement here".
- Modify: `tests/Pest.php` — add the global helpers below.

Helpers in `tests/Pest.php`:

```php
function fixturePath(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/** Upload of a temp copy of the fixture, so the real fixture is never touched. */
function statementUpload(string $fixture = 'ing-2026-06.pdf'): \Illuminate\Http\UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'stmt');
    copy(fixturePath($fixture), $tmp);

    return new \Illuminate\Http\UploadedFile($tmp, 'statement.pdf', 'application/pdf', null, true);
}

/** A valid one-page PDF that is not an ING statement. */
function blankPdf(): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        "<< /Length 44 >>\nstream\nBT /F1 24 Tf 72 720 Td (Hello world) Tj ET\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}
```

(Verified during planning: smalot parses this as a 1-page PDF, so the parser must reject it for missing header data.)

**Commands**

```sh
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** all tests pass.

## Tests

Test runner and command: `vendor/bin/sail artisan test --compact`

Unit tests (`tests/Unit`, no Laravel boot, no DB) use `IngStatementParser`/`MerchantDeriver`/`Money`/`Period` directly. Parse the fixture once per file (`beforeAll` or a static cache) to keep them fast. Feature tests use `DatabaseMigrations` (from `tests/Pest.php`), `User::factory()->create()` and `actingAs`, `statementUpload()` for uploads, and `Storage::fake('local')` where noted.

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `tests/Unit/IngStatementParserTest.php` (new) | `reads the statement header` | `number` 6, `period` `2026-06`, `statementDate` `2026-06-30`, `oldBalanceCents` 227202, `newBalanceCents` 195230 |
| same | `parses all 69 entries` | `count(entries)` = 69 |
| same | `sums the entries to the balance difference` | sum of `amountCents` = `newBalanceCents − oldBalanceCents` = −31972 |
| same | `keeps both separate 515,40 credits` | exactly 2 entries with `amountCents` 51540, both `direction()` `in`, type `Gutschrift/Dauerauftrag`, booked `2026-06-29`, purposes `Miete` and one starting `Miete 313,34 und Nebenkosten` |
| same | `parses the first entry` | entries[0]: booked/value `2026-06-01`, type `Dauerauftrag/Terminueberw.`, counterparty null, purpose `Netflix + Router`, amount −750, merchant `Netflix + Router` |
| same | `separates type and counterparty` | entry with amount −19700: type `Lastschrift`, counterparty `RhoenEnergie Fulda GmbH` |
| same | `reads multi-line purposes` | the −1299 entry (Spotify) purpose has 4 lines; line 4 = `Referenz: 1050619809280`; contains `Ihr Einkauf bei` |
| same | `reads a value date that differs from the booking date` | the −5000 entry: booked `2026-06-02`, value `2026-06-01`, counterparty `Bargeldauszahlung VISA Card SPARKASSE FULDA` |
| same | `keeps the Abschluss booking but skips the summary block` | exactly 1 entry with type `Abschluss`: amount −5, purpose `''`, merchant `Abschluss`; no entry has a purpose containing `Neuer Saldo`, `Dispokredit` or `Freistellungsauftrag` |
| same | `parses the transfer without recipient` | the −20000 entry: type `Echtzeitüberweisung`, counterparty null, purpose `''`, merchant `Echtzeitüberweisung` |
| same | `parses income` | the 134819 entry: type `Gehalt/Rente`, direction `in`, merchant `CGS Clinical Guideline Serv ices` |
| same | `derives sample merchants` (dataset: amountCents → merchant, all unique in the fixture) | −399 → `TEGUT`; −4898 → `AMAZON`; −1154 → `WWW.AMAZON`; −899 → `AMAZON PRIM`; −1940 → `LOTTO He ssen`; −1299 → `Spotify`; −1167 → `rebuy recommerc e`; −2044 → `Takeaway.com Payments`; −1199 → `Discovery Communication s Benelux`; −470 → `RhonEnergie Baderbetrieb`; −2500 → `www.s teampowered.com`; −84 → `ROSSMANN`; −750 (type `Lastschrift`) → `UNI DONER`; −570 → `BAECKEREI HAPP`; −510 → `RISTORANTE LA ROMA`; −4300 → `LS CHUMBOS FULDA`; −999 → `E-Plus Service`; −15065 → `DAK-Gesundheit`; −115820 → `Miete`; 1836 → `Rundfunkbeitrag (3 Monate)` |
| same | `sets direction by sign` | every entry: `direction()` is `out` iff `amountCents < 0`; 4 entries are `in` |
| same | `rejects a pdf that is not an ING statement` | write `blankPdf()` to a temp file; `parse()` throws `StatementParseException` |
| same | `rejects a file that is not a pdf` | temp file with content `not a pdf`; `parse()` throws `StatementParseException` |
| `tests/Unit/MerchantDeriverTest.php` (new) | `derives merchants` (dataset `[type, counterparty, purpose, expected]`) | `VISA TEGUT FILIALE 5020` → `TEGUT`; `VISA AMAZON* NQ0NU61X4` → `AMAZON`; `VISA WWW.AMAZON.* NL8J71384` → `WWW.AMAZON`; `VISA AMAZON PRIM* BZ41E2GO5` → `AMAZON PRIM`; `VISA PAYPAL *STEAM GAMES` → `STEAM GAMES`; `VISA UZR*RISTORANTE LA ROMA` → `RISTORANTE LA ROMA`; `VISA ROSSMANN 3290` → `ROSSMANN`; `VISA UNI DONER, FULDA` → `UNI DONER`; `VISA BAECKEREI HAPP GMBH &` → `BAECKEREI HAPP`; `VISA LS CHUMBOS FULDA GMBH` → `LS CHUMBOS FULDA`; `VISA REWE KAI UWE GRASMUECK` → `REWE KAI UWE GRASMUECK`; PayPal (`PayPal Europe S.a.r.l. et Cie S.C.A`, purpose `"1050619809280/PP.5741.PP/. Spotify AB, Ihr Einkauf bei\nSpotify AB\nMandat: 5QD22259HS784\nReferenz: 1050619809280"`) → `Spotify`; PayPal without "Ihr Einkauf bei" (purpose `foo`) → `PayPal Europe S.a.r.l. et Cie`; `RhoenEnergie Fulda GmbH` → `RhoenEnergie Fulda`; `CGS Clinical Guideline Serv ices Gm bH` → `CGS Clinical Guideline Serv ices`; null counterparty, purpose `"Netflix + Router"` → `Netflix + Router`; null counterparty, purpose `''`, type `Echtzeitüberweisung` → `Echtzeitüberweisung`; null counterparty, purpose of 80 × `x` → 60 × `x` |
| `tests/Unit/MoneyTest.php` (new) | `formats cents the German way` (dataset) | the five `Money::format` examples from Step 5 |
| `tests/Unit/PeriodTest.php` (new) | `formats periods` | `label('2026-06')` = `June 2026`; `short('2026-06')` = `Jun 2026`; `label('2026-02')` = `February 2026` |
| `tests/Feature/StatementUploadTest.php` (new) | `redirects guests from the upload routes` | guest `get('/upload/review')`, `post('/upload')`, `post('/upload/confirm')`, `post('/upload/discard')` → redirect to `route('login')` |
| same | `shows the drop zone` | `get(route('upload'))` 200, sees `Drop your ING statement here`, `name="statement"`, `accept="application/pdf,.pdf"`, `enctype="multipart/form-data"`; does not see `Imported` |
| same | `shows imported months as chips` | factory statements `2026-05` and `2026-06` (confirmed) and one with `confirmed_at` null for `2026-07`; response sees `Imported`, `assertSeeInOrder(['Jun 2026', 'May 2026'])`, does not see `Jul 2026` |
| same | `rejects a file that is not a pdf` | `post(route('upload.store'), ['statement' => UploadedFile::fake()->create('a.txt', 10, 'text/plain')])` → `assertSessionHasErrors(['statement' => 'Please choose a PDF file (max. 10 MB).'])`; session missing `statement_import` |
| same | `rejects a missing file` | post `[]` → same error message |
| same | `rejects a pdf larger than 10 MB` | `UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')` → same error message |
| same | `rejects a pdf that is not an ING statement` | `UploadedFile::fake()->createWithContent('x.pdf', blankPdf())` from `route('upload')` → redirect to `route('upload')`, `assertSessionHasErrors(['statement' => "This doesn't look like an ING statement."])`; `Statement::count()` 0 |
| same | `parses the upload and shows the review` | `Storage::fake('local')`; post `statementUpload()` → `assertRedirect(route('upload.review'))`; `session('statement_import.entries')` has 69 items; `Storage::disk('local')->allFiles()` is empty; nothing in `statements`/`transactions`. Then `get(route('upload.review'))` 200, sees `June 2026`, `Statement 6 · 69 entries`, `Confirm import`, `Discard`, `−1.158,20 €`, `+1.348,19 €`, `2.272,02 €`, `1.952,30 €`, `Netflix + Router`, `01.06.`; `substr_count($html, '<tr')` = 70 (69 entry rows + 1 header row); does not see `already imported` |
| same | `keeps the pending import when the review is reloaded` | upload, then `get(route('upload.review'))` twice → both 200 |
| same | `redirects to upload when nothing is pending` | `get(route('upload.review'))` → redirect `route('upload')`; `post(route('upload.confirm'))` → redirect `route('upload')`, `Statement::count()` 0 |
| same | `stores the statement and entries on confirm` | upload, `post(route('upload.confirm'))` → redirect `route('upload')`, `assertSessionHas('status', 'June 2026 imported · 69 entries')`, session missing `statement_import`; `Statement::count()` 1 with number 6, period `2026-06`, statement_date `2026-06-30`, balances 227202/195230, `confirmed_at` not null; `Transaction::count()` 69, all with that `statement_id` and period `2026-06`; `sum('amount_cents')` −31972; every transaction has `group_key` null, `share_divisor` 1, `ignored` false, `rule_id` null; the RhoenEnergie −19700 row has type `Lastschrift`, counterparty `RhoenEnergie Fulda GmbH`, merchant `RhoenEnergie Fulda`, direction `out`, `booked_on` 2026-06-01; the follow-up `get(route('upload'))` sees the success text and chip `Jun 2026` |
| same | `asks to replace an already imported statement` | factory statement number 6 / `2026-06` with 2 factory transactions; upload → review sees `June 2026 (statement 6) is already imported. Confirming replaces it.`, `Replace import`, `Cancel`; does not see `Confirm import` |
| same | `replaces the existing statement on confirm` | as above, then confirm → `Statement::count()` 1 and its id differs from the old one; `Transaction::count()` 69; no transaction with the old `statement_id` |
| same | `does not treat another month with the same number as duplicate` | factory statement number 6 / `2025-06`; upload → review does not see `already imported`; confirm → `Statement::count()` 2 |
| same | `discards the pending import` | upload, `post(route('upload.discard'))` → redirect `route('upload')`; session missing `statement_import`; `Statement::count()` 0 |
| same | `discards the pending import when leaving the review` (dataset: `overview`, `trends`, `upload`) | upload, `get(route($name))` → session missing `statement_import`; then `get(route('upload.review'))` → redirect `route('upload')`; `Statement::count()` 0, `Transaction::count()` 0 |
| same | `marks upload as active on the review page` | upload, `get(route('upload.review'))`: `substr_count($html, 'aria-current="page"')` = 1 and `preg_match('/<a[^>]*href="'.preg_quote(route('upload'), '/').'"[^>]*aria-current="page"/', $html)` = 1 (Issue 1's nav-link renders `href` before `aria-current`) |
| `tests/Feature/PagesTest.php` (modify) | `renders each app page for the user` | dataset row `upload` title → `Drop your ING statement here`; other rows unchanged; `links the empty states to upload` still passes |

## Automated verification

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `vendor/bin/sail artisan test --compact` | All tests pass (Issue 1 and Issue 2), 0 failures |
| 2 | Code style | `vendor/bin/sail bin pint --format agent` | No files changed |
| 3 | Frontend builds | `vendor/bin/sail npm run build` | Exit 0 |
| 4 | Package installed | `vendor/bin/sail composer show smalot/pdfparser` | Version 2.12.x |
| 5 | Parser on fixture | `vendor/bin/sail artisan tinker --execute` command from Step 4 | `69 2026-06 -31972` |
| 6 | Routes | `vendor/bin/sail artisan route:list --path=upload` | `upload` (GET), `upload.store` (POST), `upload.review` (GET), `upload.confirm` (POST), `upload.discard` (POST), each with `auth` and `DiscardPendingStatementImport` |
| 7 | Guest redirect over HTTP | `curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost/upload/review` | `302 http://localhost/login` |
| 8 | Indexes | Boost `database-query-mongodb` on the default DB: `listIndexes` for `statements` and `transactions` | `statements`: unique `{number:1, period:1}` and `{period:1}`; `transactions`: `{statement_id:1}`, `{period:1}` |
| 9 | No PDFs kept | `find storage/app -type f -name '*.pdf'` after the test run | No output |
| 10 | Dev DB untouched by tests | Boost `database-query-mongodb`: count `statements` and `transactions` in the default DB before and after check 1 | Same counts (tests use the `testing` DB) |
| 11 | No errors logged | Boost `last-error` and `read-log-entries` (last 20) | No new errors or exceptions |

## Manual verification

- [ ] Start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run build`, log in at http://localhost.
- [ ] Upload page: dashed drop zone with upload icon, "Drop your ING statement here", "or click to choose a PDF". No "Imported" section yet.
- [ ] Drag `tests/Fixtures/ing-2026-06.pdf` over the drop zone → border turns emerald; drop → spinner "Reading statement…", then the review page opens. The top bar still highlights Upload.
- [ ] Review page: summary card "June 2026", "Statement 6 · 69 entries", Old balance 2.272,02 €, New balance 1.952,30 €, In +2.397,35 €, Out −2.717,07 €; buttons Discard and Confirm import. Scroll: the card stays pinned below the top bar.
- [ ] Table: 69 rows, dates like "01.06.", merchants readable (TEGUT, AMAZON, Spotify, Netflix + Router, Miete …), outgoing amounts red with "−", incoming green with "+". Long merchants are truncated with "…" and show in full on hover.
- [ ] Reload the review page → still there. Click "Overview" in the top bar, then go back to Upload → nothing pending; opening http://localhost/upload/review sends you to Upload.
- [ ] Upload again, click "Confirm import" → Upload page with a green banner "June 2026 imported · 69 entries" and a chip "Jun 2026" under "Imported".
- [ ] Upload the same PDF again → amber banner "June 2026 (statement 6) is already imported. Confirming replaces it." with "Cancel", primary button reads "Replace import". Click Cancel → back on Upload, still one chip. Upload again → Replace import → green banner; still exactly one "Jun 2026" chip.
- [ ] Choose a non-PDF (a .txt or .png file) via the file picker → red "Please choose a PDF file (max. 10 MB)." under the drop zone.
- [ ] Choose any other PDF (not an ING statement) → red "This doesn't look like an ING statement."
- [ ] Light mode: drop zone, banners (green, amber), summary card and table readable; red/green amounts clearly distinguishable.
- [ ] Dark mode (switch OS theme, reload): same pages; no white boxes, banners use the dark tints, chips and table borders visible but subtle.
- [ ] Narrow window (~375 px): drop zone fits, summary figures wrap into 2 columns, buttons stay usable, table has no horizontal scroll (merchant truncates), amounts not wrapped.
- [ ] Keyboard: Tab to the drop zone (focus the hidden input via the label), press Enter/Space → file picker opens; Discard / Confirm reachable with visible focus outline.

(In/Out totals above: In = 18,36 + 1.348,19 + 515,40 + 515,40 = 2.397,35 €; Out = −319,72 − 2.397,35 = −2.717,07 €.)

## Requirement traceability

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| Upload page: drag & drop / file picker, PDF only | Steps 6, 7 | `shows the drop zone`, `rejects a file that is not a pdf`, `rejects a missing file`, `rejects a pdf larger than 10 MB` | #1, #6; manual: upload page, drag & drop, non-PDF |
| Parser spike, then implementation with `smalot/pdfparser` (fallback `pdftotext` if needed) | Spike in Context (done; `getDataTm()` reliable, no `pdftotext` available), Steps 1, 4 | all `IngStatementParserTest` tests | #4, #5 |
| Parse header: Auszugsnummer, period, Datum, Alter/Neuer Saldo | Step 4 | `reads the statement header` | #5 |
| Parse entries: booking date, value date, type, counterparty, multi-line purpose, German amount | Steps 3, 4 | `parses the first entry`, `separates type and counterparty`, `reads multi-line purposes`, `reads a value date that differs…`, `parses income`, `sets direction by sign` | #5 |
| Skip page headers/footers, legal text, "Abschluss für Konto" block; keep the "Abschluss" booking | Step 4 | `keeps the Abschluss booking but skips the summary block`, `parses all 69 entries` | #5 |
| Derived merchant (VISA, PayPal, direct debits) | Step 3 | `derives merchants`, `derives sample merchants` | #1; manual: review table |
| PDF discarded right after parsing; parsed data held server-side until confirm | Steps 6 (store) | `parses the upload and shows the review` (no files, nothing in DB) | #9 |
| Review screen: compact table (date, merchant, amount coloured) + Confirm import | Steps 5, 8 | `parses the upload and shows the review`, `formats cents the German way`, `formats periods` | #1; manual: review page, table, light/dark, narrow |
| Leaving without confirming discards everything | Step 6 (middleware, discard) | `discards the pending import`, `discards the pending import when leaving the review`, `keeps the pending import when the review is reloaded`, `redirects to upload when nothing is pending` | #1; manual: reload / leave |
| Duplicate (same number + period): Replace (delete old on confirm) or Cancel | Steps 6, 8 | `asks to replace an already imported statement`, `replaces the existing statement on confirm`, `does not treat another month with the same number as duplicate` | #1, #8; manual: re-upload |
| Upload page shows imported months as read-only chips | Steps 6, 7 | `shows imported months as chips`, `stores the statement and entries on confirm` | #1; manual: chip after confirm |
| Confirm stores statement + entries (data model) | Steps 2, 6 | `stores the statement and entries on confirm` | #1, #8, #10 |
| Tests: fixture count 69, both 515.40 credits, sum = new − old, sample merchants | Steps 1, 9 | `parses all 69 entries`, `keeps both separate 515,40 credits`, `sums the entries to the balance difference`, `derives sample merchants` | #1, #5 |
| Tests: upload → review → confirm, duplicate prompt, discard on abandon | Step 9 | `StatementUploadTest` | #1 |
| Done when: June 2026 upload shows 69 entries, confirm stores them, re-upload asks to replace | Steps 1–8 | full suite | #1–#11; manual checklist |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] Uploading `tests/Fixtures/ing-2026-06.pdf` shows a review with 69 entries; confirming stores 1 statement and 69 transactions (sum −319,72 €).
- [ ] Uploading it again shows the "already imported" banner with "Replace import"; replacing leaves exactly one June 2026 statement with 69 transactions.
- [ ] No uploaded PDF is kept anywhere; leaving the review page discards the pending import.
