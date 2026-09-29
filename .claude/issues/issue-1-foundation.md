# Issue 1: Foundation: auth, app shell, config

> Source: `.claude/project-plan.md` › "Issue 1 – Foundation: auth, app shell, config"
> Planned: 2026-09-28
> Depends on: None

## Goal

A deployment-ready app with login, logout and nothing else yet. It has its final look and navigation: a single user logs in and moves between three styled but empty pages (Overview, Trends, Upload), and the ten expense groups are defined in config with their colours as Tailwind tokens.

Original "Done when": **you can create the user, log in, and click through three empty but styled pages in light and dark mode.**

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run every PHP, Artisan, Composer, Node and test command through Sail: `vendor/bin/sail …`. Host PHP lacks `ext-mongodb`, so host `php artisan` fails. If containers are down, run `vendor/bin/sail up -d` first.
- Load these project skills before writing the matching code: `laravel-best-practices` (controllers, requests, command, routes), `tailwindcss-development` (Blade views, `app.css`), `testing-best-practices` (Pest tests).
- Use Boost `search-docs` for any framework or package API you are unsure about (Laravel 13, laravel-mongodb 5.11, Pest 5, Tailwind v4).
- After editing PHP files run `vendor/bin/sail bin pint --dirty --format agent`.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| PHP (Sail runtime) | 8.4 | `compose.yaml` (`runtimes/8.4`) |
| Laravel | 13.33.0 | `composer.lock` |
| MongoDB driver | `mongodb/laravel-mongodb` 5.11.0; server `mongodb/mongodb-atlas-local:8.0` (replica set) | `composer.lock`, `compose.yaml` |
| Tests | Pest 5.2.1 + `pestphp/pest-plugin-laravel` 5 | `composer.lock` |
| Laravel Prompts | 0.3.x (via framework) | `composer.lock` |
| Boost | 2.10.0 | `composer.lock` |
| Tailwind | v4 (`tailwindcss` ^4, `@tailwindcss/vite`), CSS-first config in `resources/css/app.css`, no `tailwind.config.js` | `package.json`, `resources/css/app.css` |
| Vite | ^8, `laravel-vite-plugin` ^3.1 (resolves to 3.2.0) | `package.json` |
| Font | Instrument Sans 400/500/600 via `bunny()` in `vite.config.js`. The plugin downloads the font at build time and serves it from `public/build` (self-hosted, no request to bunny.net at runtime). Included in Blade with `@fonts`. | `vite.config.js`, `laravel-vite-plugin` dist |
| Node in Sail | v24.21.0, npm 12.1.0 | `vendor/bin/sail node -v` |
| Session / cache / queue | `mongodb` driver (tests: session `array`, cache `array`) | `.env.example`, `phpunit.xml` |

### Current state of the codebase

Fresh Laravel 13 skeleton switched to MongoDB:

- `app/Models/User.php` extends `MongoDB\Laravel\Auth\User`, `#[Fillable(['name', 'email', 'password'])]`, `#[Hidden(['password', 'remember_token'])]`, casts `password => hashed`.
- `database/factories/UserFactory.php` sets `name`, `email`, `email_verified_at`, `password` (`password`), `remember_token`.
- `database/migrations/0001_01_01_000000_create_users_table.php` creates `users` (unique `email`), `password_reset_tokens`, `sessions`. Migrations already ran locally. Nothing to change here.
- `database/seeders/DatabaseSeeder.php` creates a `test@example.com` user. This conflicts with the single-user rule and is removed.
- `routes/web.php`: only `GET /` → `view('welcome')`. `resources/views/welcome.blade.php` is the default page and gets deleted. It shows the head pattern to reuse: `@fonts` followed by `@vite([...])`.
- `routes/console.php`: default `inspire` command. Leave it.
- `bootstrap/app.php`: `withMiddleware` is empty.
- `resources/css/app.css`: `@import 'tailwindcss'`, two `@source` lines, `@theme { --font-sans: 'Instrument Sans', … }`.
- `resources/js/app.js`: contains only `//`.
- No `resources/views/components`, no `app/Http/Requests`, no `app/Console`, no `config/expenses.php`.
- `tests/Pest.php`: `pest()->extend(TestCase::class)->in('Feature')` with `RefreshDatabase` commented out; example `toBeOne` expectation and `something()` helper.
- `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are default examples and get deleted.
- `node_modules` does not exist and there is no `package-lock.json`.
- `.env` already has `APP_NAME="Expenses Tracker"`; `.env.example` still has `APP_NAME=Laravel`.
- No `.ai/rules` directory exists.

**Important MongoDB testing constraint:** laravel-mongodb documents that `RefreshDatabase` and `DatabaseTransactions` are **not supported**. Feature tests must use `Illuminate\Foundation\Testing\DatabaseMigrations`, which runs `migrate:fresh` against the `testing` database from `phpunit.xml` before each test.

### Domain rules that apply

- Single-user app, deployed on the internet, so login is mandatory. Exactly one user, created via `vendor/bin/sail artisan user:create`. No registration, no password reset.
- UI language is English. Theme is light + dark and follows the OS (`prefers-color-scheme`). Look: modern, clean, minimal text; cards, whitespace, icons over labels.
- Groups are fixed and defined in code in `config/expenses.php` (key, name, colour, sort order). `.claude/groups.md` is only the dev-time source and is never read at runtime.

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | Navigation layout | **Top bar.** Left: brand (icon + "Expenses Tracker"). Middle: Overview, Trends, Upload as icon + label. Right: logout icon button. Below the `sm` breakpoint the nav labels and the brand text are hidden (icons only), still one row. |
| 2 | Group keys, names, colours | See the table in Step 3. Keys are snake_case; names are English. |
| 3 | Base palette | **Slate neutrals + emerald accent.** Light: page `bg-slate-50`, cards `bg-white`, borders `border-slate-200`, text `text-slate-900`, muted `text-slate-500`, accent `emerald-600`. Dark: page `bg-slate-950`, cards `bg-slate-900`, borders `border-slate-800`, text `text-slate-100`, muted `text-slate-400`, accent `emerald-400`. |
| 4 | Icons | **`blade-ui-kit/blade-heroicons`** (MIT), installed via Composer. SVGs render server-side from the local vendor dir, so there are no external requests (GDPR). Use outline icons `<x-heroicon-o-…>`. |
| 5 | Fonts / GDPR | Keep the existing `bunny()` + `@fonts` setup (self-hosted at build). No page may reference an external font or icon CDN. |
| 6 | App name | **Expenses Tracker** (`APP_NAME`, page titles `"<Page> · Expenses Tracker"`, brand text). |
| 7 | Routes | `/` → 302 redirect to `/overview`. Pages at `/overview`, `/trends`, `/upload`. Login at `/login`. After login: intended URL, else `/overview`. |
| 8 | Login throttling | Per email + IP, 5 attempts, then locked for the RateLimiter decay (60 s). Message: Laravel's `auth.throttle` ("Too many login attempts. Please try again in :seconds seconds."). |
| 9 | Login error text | Laravel's `auth.failed`: "These credentials do not match our records.", shown under the email field; email is kept, password is cleared. |
| 10 | Login page layout | Centered card (`max-w-sm`): brand icon + "Expenses Tracker" above the card; card holds Email, Password, "Remember me" checkbox, full-width emerald "Log in" button. No other links. |
| 11 | `user:create` prompts | Email (required, valid email) and password **once** (hidden, required, min 8 characters). No confirmation prompt, no name prompt. |
| 12 | User `name` | Not used. `user:create` stores only `email` + `password`; `name` is removed from `#[Fillable]` and from `UserFactory`. Top bar shows no user info. |
| 13 | Existing user | If any user exists, `user:create` prints the error "A user already exists. Only one account is allowed." and exits with code 1 **before** prompting. |
| 14 | Scaffolding cleanup | Empty `DatabaseSeeder::run()`; delete `resources/views/welcome.blade.php`, `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`. |
| 15 | Alpine.js | Install now (`alpinejs` ^3.17) and start it in `resources/js/app.js`. It is not used by any Issue 1 view. |
| 16 | Empty states | Centered card per page: icon, title, one line of text, optional CTA button. Exact content in Step 7. |
| 17 | Test DB reset | `DatabaseMigrations` for all Feature tests (laravel-mongodb does not support `RefreshDatabase`). |

## Scope

### In scope

- Login (email, password, remember me), logout, login throttling.
- All app routes behind `auth`; guests redirected to `/login`; logged-in users redirected away from `/login`.
- `user:create` Artisan command.
- `config/expenses.php` with the 10 groups.
- Tailwind v4 design tokens: one colour token per group, base palette, OS-driven dark mode.
- App layout with top bar, guest layout for login.
- Overview, Trends and Upload pages with empty states.
- Install `blade-ui-kit/blade-heroicons` and `alpinejs`.
- Removal of default scaffolding (welcome page, example tests, seeded test user).
- Pest tests for all of the above.

### Out of scope

- Statement upload, the PDF parser, the drop zone, review screen and imported-month chips (Issue 2). The Upload page only shows an empty state.
- Rules collection, rule seeder, matching, the `rules` section of `config/expenses.php` (Issue 3).
- Manual review queue and group picker (Issue 4).
- ECharts, KPI tiles, donut, aggregation, Month/Year toggle (Issues 5, 6).
- Registration, password reset, email verification, profile or password change, multiple users.
- Statements/transactions collections and models.
- Deployment pipeline or hosting setup, security headers, CSP.
- A manual light/dark toggle (the theme follows the OS only).

## Prerequisites

Make sure containers run:

```sh
vendor/bin/sail up -d
```

Packages (installed in Step 1):

| Package | Constraint | Manager |
|---|---|---|
| `blade-ui-kit/blade-heroicons` | `^2.7` (pulls `blade-ui-kit/blade-icons` ^1.6; both allow Laravel 13) | Composer |
| `alpinejs` | `^3.17` | npm (dependency) |

`.env` keys: none new. Only `.env.example` changes (`APP_NAME="Expenses Tracker"`, Step 2).

## Implementation steps

### Step 1: Install dependencies

**Files**
- Modify: `composer.json`, `composer.lock` (via Composer)
- Modify: `package.json`; new `package-lock.json` (via npm)
- Modify: `resources/js/app.js` — start Alpine

**Details**

`resources/js/app.js` becomes exactly:

```js
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
```

Do not publish the blade-heroicons config; the defaults (prefix `heroicon`, components `x-heroicon-o-*`, `x-heroicon-s-*`, `x-heroicon-m-*`) are what this plan uses.

**Commands**

```sh
vendor/bin/sail composer require blade-ui-kit/blade-heroicons:^2.7
vendor/bin/sail npm install
vendor/bin/sail npm install alpinejs@^3.17
vendor/bin/sail npm run build
```

**Step check:** `vendor/bin/sail composer show blade-ui-kit/blade-heroicons` shows 2.7.x; `package.json` lists `alpinejs` under `dependencies`; `npm run build` exits 0.

### Step 2: Clean up default scaffolding and user model

**Files**
- Delete: `resources/views/welcome.blade.php` — replaced by the app pages.
- Delete: `tests/Feature/ExampleTest.php` — tests `GET /` = 200, which becomes a redirect.
- Delete: `tests/Unit/ExampleTest.php` — placeholder.
- New: `tests/Unit/.gitkeep` — keeps the `Unit` suite directory from `phpunit.xml`.
- Modify: `database/seeders/DatabaseSeeder.php` — `run()` body becomes empty (keep the method with its docblock; remove the `User` import and factory call). Keep `use WithoutModelEvents;`.
- Modify: `app/Models/User.php` — `#[Fillable(['email', 'password'])]` (drop `name`). Everything else unchanged.
- Modify: `database/factories/UserFactory.php` — remove the `'name' => fake()->name(),` line. Everything else unchanged.
- Modify: `tests/Pest.php` — see below.
- Modify: `.env.example` — `APP_NAME="Expenses Tracker"`.

`tests/Pest.php`: replace the `RefreshDatabase` import and comment with `DatabaseMigrations`, and drop the example `toBeOne` expectation and the `something()` helper:

```php
<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Feature');
```

Remove the remaining boilerplate comment blocks from the file.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** `grep -rn "welcome" routes resources` returns only `routes/web.php` (fixed in Step 5). Running the suite is not meaningful yet (no tests).

### Step 3: Group config

**Files**
- New: `config/expenses.php`

**Details**

Array keyed by group key, in sort order. Each entry has `name`, `color` (lowercase 6-digit hex) and `sort` (int). Add a PHPDoc array shape on top: `@return array{groups: array<string, array{name: string, color: string, sort: int}>}`.

| sort | key | name | color |
|---|---|---|---|
| 1 | `rent` | Rent | `#6366f1` |
| 2 | `utilities` | Electricity & Gas | `#f59e0b` |
| 3 | `groceries` | Groceries & Personal Care | `#10b981` |
| 4 | `subscriptions` | Subscriptions & Internet | `#8b5cf6` |
| 5 | `hobbies` | Hobbies & Entertainment | `#ec4899` |
| 6 | `online_orders` | Online Orders | `#0ea5e9` |
| 7 | `takeaway` | Takeaway & Fast Food | `#f97316` |
| 8 | `restaurants` | Restaurants & Bars | `#f43f5e` |
| 9 | `health` | Health | `#14b8a6` |
| 10 | `other` | Other | `#94a3b8` |

```php
return [
    'groups' => [
        'rent' => ['name' => 'Rent', 'color' => '#6366f1', 'sort' => 1],
        // … the other nine rows exactly as in the table
    ],
];
```

Only the `groups` key goes in this file for now (the `rules` section belongs to Issue 3).

**Step check:** `vendor/bin/sail artisan config:show expenses.groups` lists 10 groups in the order above.

### Step 4: Design tokens in Tailwind

**Files**
- Modify: `resources/css/app.css`

**Details**

Keep the existing `@import`, both `@source` lines and `--font-sans`. Add a second block with `@theme static` (so the variables are always emitted as CSS custom properties, even when no utility uses them yet; Issues 5/6 read them for charts) holding one token per group. Names are `--color-group-<key with _ replaced by ->`:

```css
@theme static {
    --color-group-rent: #6366f1;
    --color-group-utilities: #f59e0b;
    --color-group-groceries: #10b981;
    --color-group-subscriptions: #8b5cf6;
    --color-group-hobbies: #ec4899;
    --color-group-online-orders: #0ea5e9;
    --color-group-takeaway: #f97316;
    --color-group-restaurants: #f43f5e;
    --color-group-health: #14b8a6;
    --color-group-other: #94a3b8;
}
```

This yields utilities such as `bg-group-rent` and `text-group-online-orders`. The hex values **must** equal `config/expenses.php`; a test checks this (Tests table).

Base palette and dark mode need no extra CSS: views use Tailwind's built-in `slate-*` / `emerald-*` / `rose-*` colours with `dark:` variants, and Tailwind v4's default `dark` variant follows `prefers-color-scheme`. Do **not** add a custom `@custom-variant dark`.

Add to the same file so native controls (checkbox, scrollbars) follow the theme:

```css
@layer base {
    html {
        color-scheme: light dark;
    }
}
```

**Commands**

```sh
vendor/bin/sail npm run build
```

**Step check:** `grep -oi -- '--color-group-[a-z-]*:#[0-9a-f]*' public/build/assets/app-*.css | sort` prints all 10 tokens with the hex values above, compared case-insensitively.

### Step 5: Routes, auth controller, login request, middleware redirects

**Files**
- New: `app/Http/Controllers/Auth/LoginController.php`
- New: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

**Routes** (`routes/web.php`, replace the file's current route):

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| GET | `/login` | `login` | `LoginController@create` | `guest` |
| POST | `/login` | `login.store` | `LoginController@store` | `guest` |
| POST | `/logout` | `logout` | `LoginController@destroy` | `auth` |
| GET | `/` | `home` | `Route::get('/', fn () => redirect()->route('overview'))->name('home')` (302) | `auth` |
| GET | `/overview` | `overview` | `Route::view('/overview', 'pages.overview')` | `auth` |
| GET | `/trends` | `trends` | `Route::view('/trends', 'pages.trends')` | `auth` |
| GET | `/upload` | `upload` | `Route::view('/upload', 'pages.upload')` | `auth` |

Group the guest routes in `Route::middleware('guest')->group(…)` and the auth routes in `Route::middleware('auth')->group(…)`. Because `/` is inside the `auth` group, a guest hitting `/` goes straight to `/login`.

**`bootstrap/app.php`** `withMiddleware`:

```php
$middleware->redirectGuestsTo(fn () => route('login'));
$middleware->redirectUsersTo(fn () => route('overview'));
```

**`LoginRequest`** (`App\Http\Requests\Auth\LoginRequest`, extends `FormRequest`):

- `authorize(): bool` → `true`.
- `rules()`: `email` → `['required', 'string', 'email']`, `password` → `['required', 'string']`. No rule for `remember`; read it with `$this->boolean('remember')`.
- `authenticate(): void`:
  1. `ensureIsNotRateLimited()`.
  2. `Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))`. On failure: `RateLimiter::hit($this->throttleKey())` (default decay 60 s) and throw `ValidationException::withMessages(['email' => __('auth.failed')])`.
  3. On success: `RateLimiter::clear($this->throttleKey())`.
- `ensureIsNotRateLimited(): void`: if `RateLimiter::tooManyAttempts($this->throttleKey(), 5)` is false, return. Otherwise `event(new Lockout($this))`, `$seconds = RateLimiter::availableIn($this->throttleKey())` and throw `ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)])])`.
- `throttleKey(): string` → `Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip())`.

**`LoginController`** (`App\Http\Controllers\Auth\LoginController`, extends `App\Http\Controllers\Controller`):

- `create(): View` → `view('auth.login')`.
- `store(LoginRequest $request): RedirectResponse` → `$request->authenticate(); $request->session()->regenerate(); return redirect()->intended(route('overview'));`
- `destroy(Request $request): RedirectResponse` → `Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');`

Validation failure redirects back with the email kept (`old('email')`) and password not flashed (Laravel's default `dontFlash` covers `password`).

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan route:list --except-vendor
```

**Step check:** `route:list` shows exactly the 7 routes above (plus `up` and the framework's `storage.local` if present) with the listed names and middleware. Views do not exist yet, so do not load pages in the browser before Step 7.

### Step 6: Layouts and shared components

**Files**
- New: `resources/views/components/layouts/app.blade.php` — authenticated shell (`<x-layouts.app>`)
- New: `resources/views/components/layouts/guest.blade.php` — login shell (`<x-layouts.guest>`)
- New: `resources/views/components/nav-link.blade.php` — top-bar link
- New: `resources/views/components/empty-state.blade.php` — empty-state card

**Shared `<head>`** (both layouts): `<!DOCTYPE html>`, `<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">`, `meta charset utf-8`, `meta viewport width=device-width, initial-scale=1`, `meta name="color-scheme" content="light dark"`, `<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>`, then `@fonts` and `@vite(['resources/css/app.css', 'resources/js/app.js'])`. Both layouts take `@props(['title' => null])`. `<body>` classes: `min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100`.

**`layouts/app`**

- `<header>`: `sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80`. Inner container `mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6`.
  - Brand (link to `route('overview')`): `<x-heroicon-o-banknotes class="size-7 text-emerald-600 dark:text-emerald-400" />` + `<span class="hidden font-semibold sm:inline">{{ config('app.name') }}</span>`.
  - `<nav aria-label="Main">` with `flex flex-1 items-center justify-center gap-1` containing three `<x-nav-link>`:
    - `route('overview')`, `routeIs('overview')`, icon `heroicon-o-chart-pie`, label "Overview"
    - `route('trends')`, `routeIs('trends')`, icon `heroicon-o-chart-bar`, label "Trends"
    - `route('upload')`, `routeIs('upload')`, icon `heroicon-o-arrow-up-tray`, label "Upload"
  - Logout: `<form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" title="Log out" aria-label="Log out" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400"><x-heroicon-o-arrow-right-start-on-rectangle class="size-6" /></button></form>`.
- `<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">{{ $slot }}</main>`.

**`nav-link`** props: `href`, `active` (bool), `icon` (Blade component name such as `heroicon-o-chart-pie`), `label`.
- Render `<a href="{{ $href }}" @if($active) aria-current="page" @endif aria-label="{{ $label }}" …>` with `<x-dynamic-component :component="$icon" class="size-5" />` and `<span class="hidden sm:inline">{{ $label }}</span>`.
- Base classes: `flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400`.
- Active: `bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400`.
- Inactive: `text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100`.

**`layouts/guest`**: body content is `<main class="flex min-h-screen flex-col items-center justify-center px-4 py-12">{{ $slot }}</main>`.

**`empty-state`** props: `icon` (component name), `title`, `text`; optional named slot `action`.
- Card: `mx-auto flex max-w-md flex-col items-center rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900`.
- Icon bubble: `flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400` containing `<x-dynamic-component :component="$icon" class="size-7" />`.
- Title: `<h1 class="mt-4 text-lg font-semibold">`; text: `<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">`.
- If `isset($action)`: `<div class="mt-6">{{ $action }}</div>`.

**Primary button style** (used by the login button and empty-state CTAs, write it inline; no separate component): `inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400`.

**Step check:** none yet (rendered in Step 7).

### Step 7: Pages (login + three empty pages)

**Files**
- New: `resources/views/auth/login.blade.php`
- New: `resources/views/pages/overview.blade.php`
- New: `resources/views/pages/trends.blade.php`
- New: `resources/views/pages/upload.blade.php`

**`auth/login`** → `<x-layouts.guest title="Log in">`:

- Above the card: `<x-heroicon-o-banknotes class="size-10 text-emerald-600 dark:text-emerald-400" />` and `<p class="mt-2 text-xl font-semibold">Expenses Tracker</p>` (use `config('app.name')`), centered, `mb-8`.
- Card `w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900` with `<form method="POST" action="{{ route('login.store') }}" class="space-y-5">` + `@csrf`:
  - Email: `<label for="email">Email</label>`, `<input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">`.
  - Password: label "Password", `<input id="password" name="password" type="password" required autocomplete="current-password">`.
  - Inputs: `mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-emerald-600 focus:outline-2 focus:outline-emerald-600/30 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-400 dark:focus:outline-emerald-400/30`. Labels: `text-sm font-medium`.
  - Remember me: `<label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400"><input type="checkbox" name="remember" value="1" class="size-4 rounded accent-emerald-600" @checked(old('remember'))> Remember me</label>`.
  - Error: `@error('email') <p class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p> @enderror` directly below the email input. Add `aria-invalid="true"` to the email input when there is an error.
  - Submit: `<button type="submit" class="w-full …primary button classes…">Log in</button>`.

**`pages/overview`** → `<x-layouts.app title="Overview">` +

```blade
<x-empty-state icon="heroicon-o-chart-pie" title="No data yet"
    text="Upload your first bank statement to see where your money goes.">
    <x-slot:action>
        <a href="{{ route('upload') }}" class="…primary button classes…">
            <x-heroicon-o-arrow-up-tray class="size-5" /> Upload statement
        </a>
    </x-slot:action>
</x-empty-state>
```

**`pages/trends`** → `<x-layouts.app title="Trends">` + `<x-empty-state icon="heroicon-o-chart-bar" title="No trends yet" text="Trends appear once you have imported a statement.">` with the same "Upload statement" action.

**`pages/upload`** → `<x-layouts.app title="Upload">` + `<x-empty-state icon="heroicon-o-arrow-up-tray" title="Statement upload arrives soon" text="You will be able to drop your ING statement PDF here.">` with **no** action slot. Wrap the empty state in `<div class="rounded-3xl border-2 border-dashed border-slate-300 p-6 dark:border-slate-700">` to hint at the future drop zone.

**Commands**

```sh
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** `curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost/overview` prints `302 http://localhost/login`, and `curl -s http://localhost/login | grep -c 'Log in'` is ≥ 1.

### Step 8: `user:create` command

**Files**
- New: `app/Console/Commands/CreateUser.php` (generate with `vendor/bin/sail artisan make:command CreateUser --no-interaction`, then edit)

**Details**

Use the Laravel 13 attribute style from the stub:

```php
#[Signature('user:create')]
#[Description('Create the single application user')]
class CreateUser extends Command
{
    public function handle(): int
```

Behaviour of `handle()`, in order:

1. If `User::query()->exists()`: `$this->components->error('A user already exists. Only one account is allowed.')` and `return self::FAILURE;` (no prompts shown).
2. `$email = text(label: 'Email', required: true, validate: ['email' => ['required', 'email']])` (Laravel Prompts `Laravel\Prompts\text`).
3. `$password = password(label: 'Password', required: true, validate: ['password' => ['required', 'string', 'min:8']])` (Laravel Prompts `Laravel\Prompts\password`).
4. `User::create(['email' => $email, 'password' => $password])` (the `hashed` cast hashes it).
5. `$this->components->info("User {$email} created.")` and `return self::SUCCESS;`.

Laravel 13 auto-discovers commands in `app/Console/Commands`; no registration needed. The `validate: ['email' => [...]]` form (associative array, key = attribute name) is what the framework's prompt validator expects. It produces "The email field must be a valid email address." and "The password field must be at least 8 characters.". Interactively the prompt re-asks after an error. Under tests the framework prints the error and throws `Illuminate\Console\PromptValidationException`; the tests rely on this.

**Commands**

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan list user
```

**Step check:** `vendor/bin/sail artisan list user` shows `user:create  Create the single application user`. Do not create the real user here; the user does that during manual verification.

### Step 9: Tests

Write the tests from the "Tests" section with `vendor/bin/sail artisan make:test --pest <Name> --no-interaction`, then run them.

**Commands**

```sh
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
```

**Step check:** all tests pass.

## Tests

Test runner and command: `vendor/bin/sail artisan test --compact`

All files are Feature tests (they use the `DatabaseMigrations` trait via `tests/Pest.php`). Use `User::factory()->create(['email' => 'me@example.com'])` for the user; the factory password is `password`.

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `tests/Feature/Auth/GuestRedirectTest.php` (new) | `redirects guests from protected pages to login` (dataset: `/`, `/overview`, `/trends`, `/upload`) | `get($uri)` → `assertRedirect(route('login'))`; `assertGuest()` |
| same | `redirects the root url to overview for users` | `actingAs($user)->get('/')` → `assertRedirect(route('overview'))` |
| same | `redirects logged in users away from the login page` | `actingAs($user)->get('/login')` → `assertRedirect(route('overview'))` |
| `tests/Feature/Auth/LoginTest.php` (new) | `shows the login form` | `get('/login')` 200, `assertSee('Email')`, `assertSee('Password')`, `assertSee('Remember me')` |
| same | `logs in with valid credentials` | `post(route('login.store'), [email, password])` → `assertRedirect(route('overview'))`, `assertAuthenticatedAs($user)` |
| same | `redirects to the intended page after login` | `get('/trends')` then post valid credentials → `assertRedirect(route('trends'))` |
| same | `sets the remember cookie when remember me is checked` | post with `'remember' => '1'` → `assertCookie(Auth::guard('web')->getRecallerName())`; `$user->fresh()->remember_token` is not null |
| same | `does not set the remember cookie without remember me` | post without `remember` → `assertCookieMissing(Auth::guard('web')->getRecallerName())` |
| same | `rejects a wrong password` | from `/login`, post wrong password → `assertRedirect('/login')`, `assertSessionHasErrors(['email' => 'These credentials do not match our records.'])`, `assertGuest()`, `assertSessionHasInput('email', 'me@example.com')`, `assertSessionMissing('_old_input.password')` |
| same | `requires email and password` | post `[]` → `assertSessionHasErrors(['email', 'password'])` |
| same | `locks out after five failed attempts` | 5 wrong posts, then a 6th **with the correct password** → `assertSessionHasErrors('email')` with a message starting "Too many login attempts."; `assertGuest()` |
| `tests/Feature/Auth/LogoutTest.php` (new) | `logs the user out` | `actingAs($user)->post(route('logout'))` → `assertRedirect(route('login'))`, `assertGuest()` |
| same | `rejects logout via get` | `actingAs($user)->get('/logout')` → `assertStatus(405)` |
| `tests/Feature/PagesTest.php` (new) | `renders each app page for the user` (dataset: `overview`/"No data yet", `trends`/"No trends yet", `upload`/"Statement upload arrives soon") | `actingAs($user)->get(route($name))` 200, `assertSee($title)`, `assertSee('<title>'.ucfirst($name).' · Expenses Tracker</title>', false)` |
| same | `shows the navigation with the current page marked` | `get(route('trends'))`: `assertSee(route('overview'))`, `assertSee(route('trends'))`, `assertSee(route('upload'))`, `assertSee('aria-current="page"', false)` exactly once in the response (`substr_count` = 1), `assertSee(route('logout'))` |
| same | `links the empty states to upload` | overview and trends responses contain "Upload statement" and `route('upload')`; upload response does not contain "Upload statement" |
| same | `uses no external font or icon hosts` | responses of `/login` and `/overview` do not contain `bunny.net`, `fonts.googleapis`, `cdn.` or `unpkg` (`assertDontSee(…, false)`) |
| `tests/Feature/CreateUserCommandTest.php` (new) | `creates the user` | `artisan('user:create')->expectsQuestion('Email', 'me@example.com')->expectsQuestion('Password', 'secret-pass')->expectsOutputToContain('User me@example.com created.')->assertSuccessful()`; then `User::count()` = 1, `Hash::check('secret-pass', User::first()->password)` true, stored `name` is null |
| same | `refuses when a user already exists` | factory user exists; `artisan('user:create')->expectsOutputToContain('A user already exists. Only one account is allowed.')->assertFailed()`; `User::count()` stays 1 |
| same | `creates the user only once` | run the successful flow, then run `user:create` again → `assertFailed()` with the "already exists" message; `User::count()` = 1 |
| same | `rejects an invalid email` | `expect(fn () => $this->artisan('user:create')->expectsQuestion('Email', 'not-an-email')->run())->toThrow(Illuminate\Console\PromptValidationException::class)`; then `User::count()` = 0 |
| same | `rejects a password shorter than 8 characters` | same pattern: `expectsQuestion('Email', 'me@example.com')->expectsQuestion('Password', 'short')->run()` throws `PromptValidationException`; `User::count()` = 0 |
| `tests/Feature/GroupConfigTest.php` (new) | `defines the ten groups in order` | `array_keys(config('expenses.groups'))` equals `['rent','utilities','groceries','subscriptions','hobbies','online_orders','takeaway','restaurants','health','other']`; `sort` values are 1..10 in that order; every `color` matches `/^#[0-9a-f]{6}$/`; names equal the Step 3 table |
| same | `has a matching tailwind token for every group colour` | read `resource_path('css/app.css')`; for each group assert the CSS contains `--color-group-<key with _→->: <color>;` |

## Automated verification

Checks the implementing agent runs itself, in order. Each has an exact command or action and the expected result. All must pass.

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `vendor/bin/sail artisan test --compact` | All tests from the Tests table pass, 0 failures, 0 risky |
| 2 | Code style | `vendor/bin/sail bin pint --format agent` | Reports no files changed |
| 3 | Frontend builds | `vendor/bin/sail npm run build` | Exit 0; `public/build/manifest.json` exists; output lists an `app-*.css` and `app-*.js` asset and the Instrument Sans `.woff2` files |
| 4 | Group tokens in built CSS | `grep -oi -- '--color-group-[a-z-]*:#[0-9a-f]*' public/build/assets/app-*.css \| sort` | 10 lines, hex values equal to the Step 3 table |
| 5 | Alpine bundled | `grep -l 'Alpine' public/build/assets/app-*.js` | One file matched |
| 6 | Routes | `vendor/bin/sail artisan route:list --except-vendor` | `login` (GET, guest), `login.store` (POST, guest), `logout` (POST, auth), `home` (GET `/`, auth), `overview`, `trends`, `upload` (GET, auth) |
| 7 | Guest redirect over HTTP | `curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost/overview` (repeat for `/`, `/trends`, `/upload`) | `302 http://localhost/login` each |
| 8 | Login page renders | `curl -s -o /dev/null -w "%{http_code}\n" http://localhost/login` | `200` |
| 9 | No external asset hosts | `curl -s http://localhost/login \| grep -Eci 'bunny\.net\|googleapis\|unpkg\|jsdelivr\|cdnjs'` | `0` |
| 10 | Command registered | `vendor/bin/sail artisan list user` | Shows `user:create` with description "Create the single application user" |
| 11 | Config resolves | `vendor/bin/sail artisan config:show expenses.groups` | 10 groups in the Step 3 order |
| 12 | Dev DB untouched by tests | Boost `database-query-mongodb` on the default connection: count documents in `users` | Same count as before running the tests (tests use the `testing` database) |
| 13 | No errors logged | Boost `last-error` and `read-log-entries` (last 20) after checks 7–9 | No new errors or exceptions |

## Manual verification

Checklist for the user. Things only a human can judge.

- [ ] Start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run build` (or `vendor/bin/sail npm run dev` in a second terminal), then open http://localhost.
- [ ] Create your user: `vendor/bin/sail artisan user:create`, enter your email and a password (≥ 8 chars) → "User … created." Run it again → red error "A user already exists. Only one account is allowed.", no prompts.
- [ ] Guest redirect: open http://localhost/overview in a private window → you land on the login page.
- [ ] Login page: centered card with the emerald banknotes icon and "Expenses Tracker" above it; Email, Password, Remember me, full-width emerald "Log in" button; no register/forgot links.
- [ ] Wrong password → red message "These credentials do not match our records." under Email; email still filled, password empty.
- [ ] Correct login with "Remember me" ticked → lands on Overview. Close and reopen the browser → still logged in.
- [ ] Top bar: brand left, Overview / Trends / Upload in the middle with icons, logout icon right. The current page is highlighted (emerald tint); hover states on the other items.
- [ ] Click through Overview, Trends, Upload: each shows a centered card with icon, title and one line of text. Overview and Trends have an "Upload statement" button that opens Upload; Upload shows a dashed border area and no button. Browser tab titles read "Overview · Expenses Tracker" etc.
- [ ] Light mode (OS set to light): slate-50 page background, white cards, dark text, emerald accents; everything readable, no harsh contrast.
- [ ] Dark mode (switch the OS theme to dark, reload): near-black slate background, dark cards, light text, emerald-400 accents; the Remember-me checkbox and input fields are dark too; no white flashes or unreadable text.
- [ ] Narrow window / mobile width (~375 px, browser dev tools): top bar stays one row, brand text and nav labels disappear leaving icons; empty-state cards fit without horizontal scrolling; login card fits with side padding.
- [ ] Keyboard: Tab through the login form and the top bar; every focused element shows a visible emerald focus outline.
- [ ] Logout icon → back on the login page; pressing the browser Back button and reloading does not show app pages.
- [ ] Network tab (dev tools) on login and overview: every request goes to localhost only (fonts included).

## Requirement traceability

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| Login page (email + password, remember me) | Steps 5, 6, 7 | `shows the login form`, `logs in with valid credentials`, `redirects to the intended page after login`, `sets the remember cookie…`, `does not set the remember cookie…`, `rejects a wrong password`, `requires email and password`, `locks out after five failed attempts` | #1, #8; manual: login page, wrong password, remember me |
| Logout | Steps 5, 6 | `logs the user out`, `rejects logout via get` | #1, #6; manual: logout |
| All other routes behind `auth` | Step 5 | `redirects guests from protected pages to login`, `redirects the root url to overview for users`, `redirects logged in users away from the login page` | #6, #7; manual: guest redirect |
| `user:create` prompts for email + password | Step 8 | `creates the user`, `rejects an invalid email`, `rejects a password shorter than 8 characters` | #1, #10; manual: create user |
| `user:create` refuses if a user exists | Step 8 | `refuses when a user already exists`, `creates the user only once` | #1; manual: create user (second run) |
| App layout: top nav with Overview, Trends, Upload | Step 6 | `shows the navigation with the current page marked` | #1; manual: top bar, mobile width |
| Tailwind design tokens: colours per group | Step 4 | `has a matching tailwind token for every group colour` | #4 |
| Light/dark via OS | Steps 4, 6, 7 | none (visual only) | #3; manual: light mode, dark mode |
| `config/expenses.php` with 10 groups (key, name, color) | Step 3 | `defines the ten groups in order` | #11 |
| Overview/Trends/Upload pages with polished empty states | Steps 6, 7 | `renders each app page for the user`, `links the empty states to upload` | #1; manual: click-through, light, dark, mobile |
| Tests: guest redirect, login/logout, command creates the user once | Step 9 | all tests above | #1 |
| GDPR: fonts and icons served locally (Decision 4, 5) | Steps 1, 6 | `uses no external font or icon hosts` | #9; manual: network tab |
| Done when: create the user, log in, click through three empty but styled pages in light and dark mode | Steps 1–8 | full suite | #1–#13; manual checklist complete |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] `vendor/bin/sail artisan user:create` creates the one user and refuses a second.
- [ ] That user can log in (with remember me) and log out; guests only ever see `/login`.
- [ ] Overview, Trends and Upload are reachable from the top bar, each showing its styled empty state in both light and dark mode.
- [ ] `config/expenses.php` holds the 10 groups and `app.css` holds matching `--color-group-*` tokens.
