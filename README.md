# Expenses Tracker

> [!IMPORTANT]
> **This project was created entirely by AI.** An AI coding assistant wrote all of the code, tests, configuration and documentation in this repository, including this README. A human set the requirements and reviewed the results, but did not write the code by hand.

Expenses Tracker is a small, single-user web app that turns ING bank statements (German "Kontoauszug" PDFs) into a spending overview. You upload your monthly statements, rules sort each entry into a spending group, and the app shows where your money goes each month and over time.

## Features

- **Statement upload**: drag and drop up to 12 ING statement PDFs at once (max. 10 MB each). The app reads each PDF, pulls out the entries and derives the merchant name from the booking text.
- **Review before saving**: every uploaded month opens in a review screen. You can confirm it, skip it, or assign a group to each entry that no rule matched. "Always use" turns a manual choice into a rule for future uploads. Uploading a month again replaces the old one but keeps the entry descriptions you added.
- **Rules and groups**: the Groups & rules page lets you create, reorder, recolour and delete groups. You build rules from conditions on merchant, counterparty, purpose, type or amount (contains, equals, greater than and so on). A rule can also:
  - count only a share of an entry (for example ⅓ of a rent payment split between flatmates), or
  - mark entries as ignored, so they count nowhere.
- **Rerun rules**: after you change rules, rerun them over all imported entries. The app shows a preview of every change, and you accept or decline each one before it is applied.
- **Overview**: shows totals, a donut chart by group and a comparison with the previous period, for a month or a whole year. It also has a searchable, filterable and paginated entries list (spending, income, ignored), and you can add your own description to any entry.
- **Trends**: a stacked bar chart of spending per group over time, plus a line chart of total spending. Click a group to follow that group on its own. Months without an imported statement are marked.
- **Single account**: login is throttled, and there is no sign-up. The only user is created from the command line.

## Tech stack

| Layer    | Technology |
|----------|------------|
| Backend  | PHP 8.4, Laravel 13 |
| Database | MongoDB 8 via [`mongodb/laravel-mongodb`](https://github.com/mongodb/laravel-mongodb) (also used for sessions, cache and queue) |
| PDF      | [`smalot/pdfparser`](https://github.com/smalot/pdfparser) |
| Frontend | Blade, Alpine.js, Tailwind CSS 4, Apache ECharts 6, Heroicons, Vite |
| Tests    | Pest 5 |
| Runtime  | Laravel Sail (Docker) |

## Getting started

The app runs through [Laravel Sail](https://laravel.com/docs/sail). PHP needs the `mongodb` extension, which the Sail container provides. Because of that, run all `php`, `artisan`, `composer` and test commands through `./vendor/bin/sail` and not on the host.

### Requirements

- Docker with Docker Compose
- Composer, for the first install only (or see the [Sail docs](https://laravel.com/docs/sail#installing-composer-dependencies-for-existing-projects) for a Docker-only install)

### Installation

```sh
git clone <repository-url> expenses-tracker
cd expenses-tracker

composer install --ignore-platform-req=ext-mongodb
cp .env.example .env
```

Optionally, set `APP_PORT` in `.env` if port 80 is already in use on your machine, for example `APP_PORT=8080`. Then set up the app:

```sh
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate      # creates collections and the starting groups
./vendor/bin/sail artisan db:seed      # adds the starting rules from config/expenses.php
./vendor/bin/sail artisan user:create  # asks for an email and password
./vendor/bin/sail npm install
./vendor/bin/sail npm run build        # or `sail npm run dev` while developing
```

Open http://localhost (or `http://localhost:<APP_PORT>`) and log in.

### Starting groups and rules

[config/expenses.php](config/expenses.php) contains the starting groups and rules. They are copied into the database only once, when the database is empty. After that, groups and rules live in the database, and you change them on the Groups & rules page. The shipped rules match the original author's own merchants and are only a starting point. Edit the config before the first `migrate` / `db:seed`, or change the rules later in the UI.

## Usage

1. Go to **Upload** and drop one or more ING statement PDFs.
2. Go through the review screen for each month. Assign groups to unmatched entries, then confirm the month.
3. Open **Overview** to see the month or year, and **Trends** to see how spending changes over time.
4. Adjust **Groups & rules** as you go, then rerun the rules to update older months.

Only the ING Germany statement layout is supported. The parser reads text by its position on the page, so PDFs from other banks will be rejected.

## Development

```sh
./vendor/bin/sail npm run dev               # Vite dev server with hot reload
./vendor/bin/sail artisan test --compact    # run the test suite
./vendor/bin/sail bin pint                  # format PHP code
```

Tests use Pest and live in [tests/](tests). The parser, upload and report tests need a real ING statement at `tests/Fixtures/ing-2026-06.pdf`. It contains personal banking data, so it is not in the repository (`.gitignore` excludes it). Put your own June 2026 statement there to run those tests. Some tests check exact totals and entry positions from the original file, so they may fail with a different statement.

### Project layout

| Path | Contents |
|------|----------|
| [app/Services/Statements/](app/Services/Statements) | PDF parsing (`IngStatementParser`), merchant derivation, import batches, review queue |
| [app/Services/Rules/](app/Services/Rules) | Rule matching, text normalization, rerunning rules |
| [app/Services/Groups/](app/Services/Groups) | Group catalog and the Groups & rules page cards |
| [app/Services/Reports/](app/Services/Reports) | Spending totals, period navigation, group comparison, trend series, entry list |
| [app/Support/](app/Support) | Money, percent and period helpers |
| [resources/views/pages/](resources/views/pages) | Blade pages: overview, trends, upload, review, groups |
| [resources/js/](resources/js) | Alpine components and ECharts setup |
| [config/expenses.php](config/expenses.php) | Starting groups and rules |
