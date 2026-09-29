# Plan Template

Copy this structure into `.claude/issues/<issue-name>.md`. Keep every section and its order. If a section truly does not apply, keep the heading and write "None" with a one-line reason. Replace every `<…>` placeholder; none may remain in the final plan.

````markdown
# Issue <N>: <Title>

> Source: `<path to project plan>` › "<issue heading>"
> Planned: <YYYY-MM-DD>
> Depends on: <earlier issues that must be done first, or "None">

## Goal

<The issue's goal in 1 to 3 sentences, plus its original "Done when" line verbatim.>

## Instructions for the implementing agent

- Work through "Implementation steps" in order. Do not skip steps or reorder them.
- Everything needed is in this file. Do not ask the user; if something is truly impossible as written, stop and report what blocks you instead of improvising.
- Run all commands as written (<runtime note, e.g. "through Sail: `vendor/bin/sail …`; host PHP lacks ext-mongodb">).
- Load these project skills before writing the matching code: <e.g. `laravel-best-practices` (PHP), `tailwindcss-development` (Blade/CSS), `testing-best-practices` (tests)>.
- Finish by working through "Automated verification" until every check passes, then hand the "Manual verification" checklist to the user.

## Context

### Stack (as installed)

| Area | Version / choice | Source |
|---|---|---|
| <e.g. Laravel> | <13.x> | <composer.lock> |

### Current state of the codebase

<What exists today that this issue touches or relies on, with paths. What default scaffolding gets replaced.>

### Domain rules that apply

<Only the project-plan rules relevant to this issue, restated so the agent needs no other file.>

## Decisions

Decisions made with the user while planning. They are final.

| # | Question | Decision |
|---|---|---|
| 1 | <question> | <answer, with exact values> |

## Scope

### In scope

- <bullet per deliverable>

### Out of scope

- <things that belong to later issues or are excluded, so they are not built here>

## Prerequisites

<Packages to install (exact name and version constraint), with the exact install/publish/setup commands and any .env keys (with values for local dev). "None" if nothing is needed.>

## Implementation steps

### Step 1: <short title>

**Files**
- New: `<path>` — <purpose>
- Modify: `<path>` — <what changes>
- Delete: `<path>` — <why>

**Details**

<Exactly what to build: signatures, config values, route names/URIs/middleware, validation rules, user-facing texts, behaviour on errors and edge cases. Short code sketches only where they remove ambiguity.>

**Commands**

```sh
<commands to run for this step, if any>
```

**Step check:** <a quick check that proves this step works before moving on>

### Step 2: <…>

<repeat>

## Tests

Test runner and command: `<e.g. vendor/bin/sail artisan test --compact>`

| File (new/modify) | Test name | Asserts |
|---|---|---|
| `<tests/Feature/…Test.php>` | `<it does X>` | <what exactly is asserted> |

<Notes on factories, fixtures, fakes and database handling needed by the tests.>

## Automated verification

Checks the implementing agent runs itself, in order. Each has an exact command or action and the expected result. All must pass.

| # | Check | Command / action | Expected result |
|---|---|---|---|
| 1 | Test suite green | `<test command>` | <all tests pass, 0 failures> |
| 2 | Code style | `<e.g. vendor/bin/sail bin pint --test>` | <no issues> |
| 3 | Frontend builds | `<e.g. vendor/bin/sail npm run build>` | <exit code 0, no warnings about …> |
| 4 | <behaviour check> | <e.g. curl -I http://localhost/overview, a database-query, an artisan command> | <e.g. 302 to /login> |
| 5 | No errors logged | <Boost `last-error` / `read-log-entries`> | <no new errors> |

## Manual verification

Checklist for the user. Things only a human can judge: look, feel, light/dark mode, responsiveness, interactions. Each item: steps and what to look for.

- [ ] <Setup step, e.g. start the app: `vendor/bin/sail up -d && vendor/bin/sail npm run dev`, open <URL>>
- [ ] <Check>: <steps> → <expected>
- [ ] Light mode: <what to check where>
- [ ] Dark mode (switch the OS theme): <what to check where>
- [ ] Narrow window / mobile width: <what to check>

## Requirement traceability

Every bullet and the "Done when" line of the original issue.

| Requirement (from issue) | Implementation step(s) | Test(s) | Verification check(s) |
|---|---|---|---|
| <requirement> | <Step n> | <test name> | <#n / manual item> |

## Definition of done

- [ ] All implementation steps done.
- [ ] All tests pass and all automated verification checks pass.
- [ ] Manual verification checklist handed to the user.
- [ ] <issue-specific criteria from "Done when">
````
