---
name: plan-issue
description: "Turn a short issue description (e.g. an \"Issue N\" section of `.claude/project-plan.md`) into a complete, self-contained implementation plan saved to `.claude/issues/<issue-name>.md`. Explores the codebase and tech stack, asks the user about every open or underspecified point instead of assuming, and writes a plan detailed enough for a fresh agent to implement the whole issue without further user input, including an agent-runnable verification section and a manual verification checklist for the user. Only run when the user explicitly asks to plan an issue; never start it as part of another task. Do not use for implementing the issue itself."
argument-hint: "[issue reference, e.g. \"Issue 2\" or a path + heading; defaults to the selected text]"
disable-model-invocation: true
metadata:
  author: project
---

# Plan Issue

Turn a short issue description into a final implementation plan that another agent can execute end to end, alone. You are planning, not implementing: do not create or change application code, install packages or run migrations. The only file you write is the plan.

## Ground Rules (read before you start)

- Never assume. Anything the description, the project docs or the code does not settle is a question for the user. This includes "A or B" wording ("top or side nav"), vague quality words ("polished", "clean", "modern"), unstated behaviour (validation messages, error states, redirects, empty states, limits), naming, UX details and edge cases. When you catch yourself writing "probably", "e.g.", "something like" or "TBD" in the plan, stop and ask instead.
- Ground everything in reality. Every file path, class, route, config key, package and command in the plan must either exist in the codebase (you checked) or be explicitly marked as new. Use the versions actually installed (`composer.json` / `composer.lock`, `package.json`), and look up version-specific APIs with the Boost `search-docs` tool rather than memory.
- Follow the project, not your taste. Match the conventions already in the codebase and in `.ai/rules`, `AGENTS.md`, `CLAUDE.md`, the project skills and the auto-memory (e.g. how commands must be run). Global decisions in the project plan are fixed; do not re-open them.
- Stay inside the issue. Features belonging to later issues or listed as out of scope stay out, but name them in the plan's "Out of scope" section so the implementing agent does not drift. Include only groundwork later issues need when the issue itself asks for it.
- Self-contained output. The implementing agent will not see this conversation, the user's answers or your exploration notes. Everything it needs (decisions, exact values, commands, expected results) goes into the plan file.

## Process

Each step ends on a checkable completion criterion. Do not advance until it holds.

### Step 0: Identify the issue

Resolve which issue to plan from, in order: the skill argument, the user's IDE selection, or the message itself. If the reference points to a file section (e.g. "Issue 2"), read that section in full. If nothing identifies a single issue, ask.

Derive the output file name as `issue-<number>-<short-kebab-slug>.md` (e.g. `issue-1-foundation.md`), using the issue number and the first words of its title. If the issue has no number, use only the slug. If `.claude/issues/<name>.md` already exists, read it and ask the user whether to overwrite it, refine it or pick another name.

Done when: you have the full issue text and a confirmed output path.

### Step 1: Load project context

Read, when present:

- the whole project plan the issue comes from (global decisions, domain rules, data model, the other issues, "Out of scope"), plus any files it references (e.g. `.claude/groups.md`);
- `CLAUDE.md`, `AGENTS.md`, `.ai/rules/**`, `boost.json`, and the auto-memory index;
- previously written plans in `.claude/issues/`, because earlier issues define what already exists or will exist when this one starts;
- the list of project skills in `.claude/skills/` (the plan should tell the implementing agent which ones to load, e.g. `laravel-best-practices`, `tailwindcss-development`, `testing-best-practices`).

Done when: you can state the fixed stack decisions, the domain rules that touch this issue, and which earlier issues this one depends on.

### Step 2: Explore the codebase

Build an accurate picture of the current state. Delegate the sweep to an `Explore` subagent when the codebase is large; otherwise do it yourself. Cover:

- Stack and versions: `composer.json`, `composer.lock`, `package.json`, `vite.config.js`, CSS entry points (Tailwind version and config style), test runner and config (`phpunit.xml`, `tests/Pest.php`).
- Runtime: how commands are run (Sail, Docker, host), database driver and connection, `.env.example`, `compose.yaml`.
- Structure: `app/` tree, `routes/`, `resources/views` (layouts, components), `config/`, `database/` (migrations, factories, seeders), `tests/`.
- Everything the issue touches: existing models, auth setup, middleware, layouts, components or config it must extend or replace. Note the default scaffolding that must be removed or rewritten (e.g. `welcome.blade.php`, the default route).
- Conventions to match: naming, controller style, validation style, Blade components vs. partials, test style.
- Tooling the verifying agent can use: Boost MCP tools (`database-query`, `last-error`, `browser-logs`, `read-log-entries`, `get-absolute-url`), Pint, the frontend build.

For anything that must be installed, confirm the package name, a version compatible with the installed framework version, and the install and setup commands (use `search-docs` or the package docs, not memory).

Done when: you have a list of files to create, files to modify, packages to install, and the exact commands to run them, each backed by what you saw.

### Step 3: Resolve open questions

List every point the issue, project docs and code leave open (see Ground Rules). Include decisions the codebase exposes that the issue did not anticipate (e.g. an existing field that conflicts with the plan, a package that needs a PHP extension the runtime lacks).

Ask them with the `AskUserQuestion` tool, up to 4 questions per call, grouped by topic, repeating until nothing is open. For each question give 2 to 4 concrete options, put your recommendation first with "(Recommended)" and a one-line reason drawn from the codebase or project plan. Use `preview` for visual or code choices (layout sketches, color token tables, file structure). If the tool is unavailable, ask in chat as a numbered list and stop until the user answers.

Answers can raise new questions; ask those too. Do not ask about what is already decided in the project plan, the codebase or conventions, and do not ask about pure implementation details a competent agent should settle by following the project's conventions.

Done when: no open point remains, and every answer is noted for the plan's "Decisions" section.

### Step 4: Write the plan

Create `.claude/issues/` if needed and write the plan following `references/plan-template.md` exactly (all sections, in order). Requirements:

- Implementation steps are ordered so the app stays working after each one, and each names the exact files to create or modify, what goes in them, and the commands to run.
- Give exact values where the result depends on them: config content, color tokens, route names and URIs, command signatures and prompts, validation rules, messages shown to the user, test names. Add short code sketches only where they remove ambiguity (signatures, config arrays, a tricky query); do not paste whole files of routine code.
- Every requirement and every "Done when" point from the issue maps to at least one implementation step, one test and one verification check. Add the traceability table from the template.
- The automated verification section contains only checks the agent can run itself (commands with their expected outcome, database queries, HTTP requests, log checks, build output). Anything that needs human eyes (visual styling, light/dark appearance, responsiveness, feel of interactions) goes into the manual verification section as a concrete checklist with steps and expected results.

Done when: the file exists and every template section is filled.

### Step 5: Self-review

Reread the plan as the implementing agent with no other context and fix it until all of these hold:

- No "TBD", "e.g.", "probably", "as appropriate", "or similar", or unresolved "A or B".
- Every referenced existing path exists; every new path is marked new.
- Every command uses the project's runtime (e.g. `vendor/bin/sail artisan ...` when the app runs in Sail).
- Every issue requirement appears in the traceability table with a step, a test and a check.
- Nothing from later issues or "Out of scope" crept into the steps.

If the review surfaces a new open point, go back to Step 3.

Done when: every item above holds.

### Step 6: Report

Tell the user the plan's path, a 3 to 5 line summary (main steps, packages installed, number of tests and checks), and the decisions they made that shaped it. Do not start implementing.
