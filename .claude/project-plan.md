# Expenses Tracker – Project Plan

Personal, single-user expenses tracker. Upload a monthly ING "Kontoauszug" PDF, have its entries grouped automatically (manually where needed), and explore spending per month/year and over time.

## Global decisions

| Topic | Decision |
|---|---|
| Stack | Laravel 13, Blade, Alpine, Tailwind v4, MongoDB (`mongodb/laravel-mongodb`), run via Sail |
| Hosting | Deployed (internet-reachable), so login is mandatory |
| Users | Exactly one. Created via `sail artisan user:create`. No registration, no password reset |
| UI language | English |
| Theme | Light + dark, follows OS (`prefers-color-scheme`) |
| Look | Modern, clean, minimal text. Cards, whitespace, icons over labels |
| Bank | ING only. Parser built for the ING layout, no multi-bank abstraction |
| Charts | Apache ECharts via npm (Apache 2.0, free, no account/API key). Import only the needed modules (tree-shaking) to keep the bundle small. Shared theme for group colours and dark mode |
| Money | Stored as integer cents |
| Tests | Pest. The redacted example PDF is committed as a fixture (`tests/Fixtures/`) |

## Domain rules

- **Groups are fixed** and defined in code: `config/expenses.php` (key, name, color, sort order). `.claude/groups.md` is only the dev-time source and is not read at runtime.
- **Rules live in the DB**, seeded once from `config/expenses.php`. A rule matches an entry (field + "contains" pattern + direction in/out/any, with priority) and does one of the following:
  - assigns a **group**, optionally with a **share divisor** (e.g. ÷3), or
  - marks the entry as **ignored**.
- **Shared costs**: rent, Strom/Gas and Rundfunkbeitrag count as *outgoing ÷ 3*. The share sits **on the rule**, not the group, so other entries in the same group count in full.
- **Roommate reimbursements are ignored** (e.g. incoming "Gutschrift/Dauerauftrag … Miete", incoming "Rundfunkbeitrag") through seeded ignore rules. Ignored entries are stored but excluded from all totals.
- **Income** (e.g. salary) is stored and shown as an income total. It is not grouped.
- **Unmatched outgoing entries stay unassigned** until you pick a group. "Other" is only set by explicit rules or by you.
- **Month attribution = statement month** (everything in "Kontoauszug Juni 2026" counts in June 2026).
- **Entries are editable only during upload.** Once confirmed they are final: no later editing, deleting or re-running rules.
- **Matching must normalize text**: the PDF text layer inserts stray spaces ("Takeaway .com", "LOTTO He ssen", "Discover y"). Before comparing, uppercase, remove whitespace and fold umlauts (ü→ue etc.).

## Data model (MongoDB collections)

- `users`: the single account.
- `statements`: `number` (Auszugsnummer), `period` (`YYYY-MM`), `statement_date`, `old_balance_cents`, `new_balance_cents`, `confirmed_at`.
- `transactions`: `statement_id`, `period`, `booked_on`, `value_on`, `type` (Lastschrift, Gutschrift/Dauerauftrag, Gehalt/Rente, Echtzeitüberweisung, Abschluss, …), `counterparty`, `purpose`, `merchant` (derived), `amount_cents` (signed), `direction` (in/out), `group_key` (nullable), `share_divisor` (default 1), `ignored` (bool), `rule_id` (nullable).
- `rules`: `field` (merchant/counterparty/purpose/type), `pattern`, `direction`, `priority`, `group_key` (nullable), `share_divisor`, `ignore`, `source` (seeded/manual).

The counted amount of an entry is `amount_cents / share_divisor`, and only for non-ignored entries.

---

## Issue 1 – Foundation: auth, app shell, config

**Goal:** A deployed-ready, logged-in, empty app with the final look and navigation.

- Login page (email + password, remember me) and logout. All other routes behind `auth`.
- `user:create` artisan command (prompts for email + password, refuses if a user exists).
- App layout: top or side nav with **Overview**, **Trends**, **Upload**. Tailwind design tokens (colors per group, light/dark via OS).
- `config/expenses.php` with the 10 groups (key, name, color) taken from `groups.md`.
- Overview/Trends/Upload pages exist with polished empty states.
- Tests: guest redirect, login/logout, command creates the user once.

**Done when:** you can create the user, log in, and click through three empty but styled pages in light and dark mode.

## Issue 2 – Statement upload & parsing (no grouping yet)

**Goal:** Upload an ING PDF, see all parsed entries, confirm, and have them saved.

- Upload page: drag & drop / file picker, PDF only.
- Parser spike, then implementation: extract text (candidate: `smalot/pdfparser`, pure PHP with no system binary needed on the host; fall back to `pdftotext` if line order is unreliable). Parse:
  - header: Auszugsnummer, period ("Kontoauszug Juni 2026"), Datum, Alter/Neuer Saldo,
  - entries: booking date, value date, type, counterparty, purpose (multi-line), amount (German number format),
  - skip page headers/footers, legal text and the "Abschluss für Konto" summary block. Keep the "Abschluss" booking itself.
  - **Derived merchant**: e.g. `VISA TEGUT FILIALE 5020` → `TEGUT`, PayPal `…Ihr Einkauf bei Spotify AB` → `Spotify AB`, direct debits → counterparty.
- The PDF is **discarded right after parsing**. Parsed data is held server-side (session/cache) until you confirm.
- **Review screen**: compact table (date, merchant, amount in/out coloured) plus a "Confirm import" button. **Leaving without confirming discards everything.**
- **Duplicate handling**: if a statement with the same number + period exists, ask **Replace** (old statement + entries are deleted on confirm) or **Cancel**.
- Upload page shows imported months as read-only chips, so you can see what's already imported.
- Tests: parser against the fixture (entry count 69, both separate 515.40 credits present, sum of entries = new − old balance, sample merchants), upload → review → confirm flow, duplicate prompt, discard on abandon.

**Done when:** uploading the June 2026 statement shows 69 entries and confirming stores them. Re-uploading asks to replace.

## Issue 3 – Automatic grouping via rules

**Goal:** Entries arrive on the review screen already grouped (or ignored / ÷3) wherever a rule matches.

- `rules` collection + seeder reading a rules section in `config/expenses.php`.
- Matching service: normalize text (see Domain rules), evaluate by priority (so `AMAZON PRIM` wins over `AMAZON`, and `Baderbetrieb` wins over `RhoenEnergie`), first match wins.
- Initial seeded rules (from `groups.md` + example statement):

| Group | Patterns (field) | Extra |
|---|---|---|
| 1 Housing – Rent | `Miete` (purpose, out, Dauerauftrag) | ÷3 |
| 2 Housing – Strom + Gas | `RhoenEnergie Fulda` (counterparty, out) | ÷3 |
| 3 Groceries + Personal Care | TEGUT, REWE, EDEKA, ALDI SUED, BAECKEREI HAPP, TEO FULDA, ROSSMANN, MUELLER | |
| 4 Subscriptions + Internet | `Netflix + Router`, Spotify, Discovery, AMAZON PRIM, E-Plus / ALDI TALK | |
| 5 Hobbies & Entertainment | STEAM, steampowered, CineStar, Baderbetrieb, Google Payment Ireland | |
| 6 Online Orders | AMAZON, WWW.AMAZON, rebuy | |
| 7 Takeaway & Fast Food | Takeaway.com, Lieferando, McDonalds, UNI DONER, Selecta, Kiosk | |
| 8 Restaurants & Bars | VIVA HAVANNA, RESTAURANT PIZZERIA, RISTORANTE LA ROMA, LS CHUMBOS | |
| 9 Health | DAK-Gesundheit, Apotheke | |
| 10 Other | Rundfunkbeitrag (out) ÷3, LOTTO, Bargeldauszahlung, Abschluss | |
| *ignore* | incoming `Miete`, incoming `Rundfunkbeitrag` | |

- Review screen shows a group chip per entry, a "÷3" badge and an "ignored" badge. Stored with `group_key`, `share_divisor`, `ignored`, `rule_id`.
- Tests: each seeded rule against fixture entries, priority conflicts, normalization, share/ignore flags persisted.

**Done when:** the June fixture imports with everything grouped except genuinely unknown entries (e.g. the `Echtzeitüberweisung` −200 with no readable recipient).

## Issue 4 – Manual review of unassigned entries

**Goal:** Every unmatched outgoing entry gets a group before the import is confirmed.

- After upload you land in a review queue. Unassigned entries appear first, with a counter ("3 to review") in the header.
- Group picker: row of coloured chips (one click). Optional checkbox **"Always use this group for ‹merchant›"**:
  - applies immediately to other unassigned entries of the same merchant in this upload,
  - is saved as a `manual` rule **on confirm** (nothing persists before confirm).
- "Confirm import" stays disabled while outgoing entries are unassigned. Income and ignored entries need no group.
- Tests: confirm blocked with open entries, manual assignment stored, rule created only when ticked and only on confirm, rule used on next import.

**Done when:** you can assign the −200 transfer, tick "always", confirm, and a later upload matches it automatically.

## Issue 5 – Overview dashboard

**Goal:** See where the money went in a month or a year.

- **Month | Year** toggle + `‹ June 2026 ›` arrows (disabled beyond data range). Defaults to the latest imported month.
- KPI tiles: **Spent**, **Income**, **Net**. Uses counted amounts (÷ share, ignored excluded).
- **Donut** by group (group colours, total in centre, hover shows amount + %).
- **Comparison vs previous period** per group: amount + ↑/↓ % (for spending, up = red, down = green).
- Install ECharts (modular imports: pie, bar, legend, tooltip, SVG/canvas renderer), add an Alpine component wrapping it with a shared light/dark theme that follows the OS and resizes with its container. Donut total in the centre via a `graphic`/title element.
- Aggregation in a dedicated query/service (Mongo aggregation pipeline), unit-tested with known sums (e.g. June: Housing Rent = 1,158.20 / 3 = 386.07).
- Tests: aggregation correctness (shares, ignored, income), period navigation bounds, empty state.

**Done when:** June 2026 shows correct totals per group and switching to 2026 aggregates all imported months.

## Issue 6 – Trends (groups over time)

**Goal:** See how groups evolve.

- **Month | Year** toggle (same component as Overview).
- **Stacked bar chart**: one bar per month (or per year), segments = groups. Click legend items to hide/show groups (totals update).
- Range: all periods with data.
- Reuses the Issue 5 aggregation service (grouped by period).
- Tests: series shape per granularity, months without data shown as zero.

**Done when:** with ≥2 imported months you can compare groups month over month and toggle groups on and off.

---

## Order & dependencies

1 → 2 → 3 → 4 → 5 → 6. Issues 5 and 6 need at least one confirmed import, and are best checked with 2+ months.

## Out of scope (for now)

Editing/deleting entries or statements after confirm, re-running rules, rule management UI, editable groups, other banks, budgets, income grouping, export, keeping PDFs, balance validation on import, deployment pipeline/hosting setup.
