# Peluquería Jenver project instructions

## Canonical engineering guidance

`C:\Users\pepeb\Documents\code\developer-brain` is the canonical source for
engineering knowledge, methodology, workflows, procedures, and reusable
skills. Do not duplicate that material in this repository.

Before changing this project, read the relevant guidance in Developer Brain:

- `methodology/workflow.md` to select SIMPLE, FEATURE, or ARCHITECTURAL.
- The applicable procedure in `procedures/` and knowledge in `knowledge/`.
- The matching installed skill under `.claude/skills/` when one applies.
  Developer Brain skills copied here: `spec`, `tasks`, `feature`, `review`,
  `fix-review`, `architecture-review`. Laravel Boost skills also live here:
  `infer-conventions`, `laravel-best-practices`, `testing-best-practices`,
  `tailwindcss-development`.

Project-specific facts and decisions belong here. There is no `.ai/` folder
yet — create `.ai/specs/` and `.ai/reviews/` the first time a task needs one.

## Project

Peluquería Jenver is a marketing/SEO website — not a booking or management
app — for a unisex hair salon in Montcada i Reixac (Barcelona), specialized
in balayage, afro hair and curls. It is a client of COBA PROJECTS. Production:
https://www.peluqueriajenver.com/ (nginx). There is no CI/CD; deploys are
manual — ask the user how before assuming a process.

Stack: Laravel 13 (PHP ^8.3) + Blade + Tailwind 4 + Vite 8, Pest 4 for tests,
Pint for style. No JS framework, only vanilla JS in `resources/js/`.

Local environment: this checkout used to run under Laragon
(`/c/laragon/www/peluqueriajenver`), but that vhost no longer exists on this
machine. Local development now runs in Docker (`docker-compose.yml`) — see
"Docker environment" below. `php artisan serve` is not used any more: the
`.env` it depended on pointed `SESSION_DRIVER=database` at a MySQL instance
that no longer exists, and there was no `sessions` migration to create the
table even if it did (see "Production database" below).

Routes are plain closures in `routes/web.php` (home, 4 service pages,
`/contacto`, 3 legal pages with `noindex`, plus `/sitemap.xml`). Content
strings live in `lang/es/*.php`; `lang/en/` only has Laravel's own default
files — the site itself is Spanish-only.

The only way to book an appointment is by phone (`tel:+34633912050`) or
WhatsApp (`wa.me/34633912050` links, with a pre-filled message per page).
There is no booking system.

## Note on README.md

`README.md` at the repository root describes a client/appointment/staff
management platform. That is not this project — it is a leftover from a
generic template. Do not treat it as documentation of what exists; this
AGENTS.md and the code are the source of truth. Do not rewrite the README
unless the user asks.

## Docker environment

`docker compose up -d --build` builds and starts: `nginx` serving the app at
`http://localhost:8082`, the Vite dev server on port `5175`
(`VITE_USE_POLLING=true`, required on Windows), and `mysql` (database
`peluqueriajenver`, matching production's driver) on host port `3310`.
Ports were chosen so this stack can run alongside the sibling projects at
the same time: cobaprojects uses `8081`/`5174`/`3308`/`3309`, obranur uses
`8080`/`5173`/`3306`/`3307`/`6381`.

There is no queue worker or scheduler: routes are plain closures that
return views, with no contact form or other background work, even though
`QUEUE_CONNECTION=database` matches production — there is simply nothing to
consume.

`vendor/` and `node_modules/` live in the named volumes `vendor-data` and
`node-modules-data`, not in the bind mount: autoloading their ~10k/~3.2k
small files over the Windows bind mount (9p/drvfs) is what made requests
and `artisan` calls slow in cobaprojects/obranur before this same fix.
`composer install` and `npm install` run automatically inside the
`app`/`node` containers on every `docker compose up`. There is no
`vendor/` on the host any more, so PHP IDE autocompletion for dependencies
is not available on Windows for this repository; this is an accepted
tradeoff, not a bug.

Laravel Boost's MCP server (`.mcp.json`) now runs via
`docker compose exec -T app php artisan boost:mcp`, so the containers must
already be up (`docker compose up -d`) before starting Claude Code in this
project, or the MCP server will fail to start.

OPcache in the `app` container uses `opcache.revalidate_freq=60`
(`docker/php/opcache.ini`, local-only) instead of the engine default of 2,
so an edited `.php` file can take up to 60s to show up; it does not need a
restart to be picked up, just that delay. `docker compose exec app php -r
"opcache_reset();"` does **not** speed this up — it resets a separate
OPcache instance private to that one-off CLI process, not php-fpm's. To see
a change immediately instead of waiting: `docker compose restart app`.

**First start:** the `mysql` service starts empty. Run
`docker compose exec app php artisan migrate` once after the first
`docker compose up -d` (not run automatically by the `app` container's
command — see "Production database" for why that matters here specifically:
the same command, unmodified, is what you would run against the real
database if this compose file were ever pointed at one).

## Production database

Production uses `SESSION_DRIVER=database`, but this repository has no
migration for the `sessions` table — only `users`, `cache` and `jobs`
(Laravel's stock skeleton migrations) existed before this change. That
means production's `sessions` table, if it exists, was created outside
Laravel's migration history and has no matching row in the `migrations`
table. The `0001_01_01_000003_create_sessions_table` migration added by
this change guards its `up()` with `Schema::hasTable('sessions')` so that
running `php artisan migrate --force` there does not fail or attempt to
recreate a live table, and its `down()` is deliberately a no-op so a
rollback can never drop it.

`cache` and `jobs` are not guarded the same way: their migrations already
existed in this repository from the project's initial setup, so — assuming
the standard Laravel deploy flow was followed (`composer.json`'s
`post-create-project-cmd` runs `artisan migrate --graceful` on first
install) — they should already be recorded in production's `migrations`
table. This is an assumption, not something verified against the real
server; confirm it before trusting it.

**Before running `php artisan migrate` (or `--force`) against production,
always check its current state first, from the server itself:**

```
php artisan migrate:status
```

If `0001_01_01_000003_create_sessions_table` (or any migration) is missing
from that list while its table already exists, do **not** run `migrate`
blindly — inspect further first:

```
php artisan tinker --execute 'echo config("database.connections.".config("database.default").".database");'
mysql -u<user> -p -e "SHOW TABLES;" <database>
```

Never run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, or
a manual `DROP`/`TRUNCATE` against production. If `migrate:status` shows
anything unexpected, stop and ask the user before proceeding.

## Production deploys

The VPS has nginx and PHP-FPM installed directly (no Docker in production),
and PHP-FPM runs as `www-data`. This reuses the pattern built and
independently reviewed for cobaprojects (`deploy.sh`, `deploy:check`,
`.ai/reviews/deploy-safety.md` in that repository), adapted to what this
project actually has — see "What this project does not need" below.

- `./deploy.sh`, run as the non-root `deploy` user from the checkout,
  deploys whatever is already pushed to the branch it is on (it only
  fast-forwards; it never switches branches). Order of operations:
  1. Checks that the remote is reachable and this branch has not diverged
     from its upstream (`git fetch origin` then
     `git merge-base --is-ancestor HEAD @{u}`) — **before** asking for
     sudo or touching the site at all. This is new compared to
     cobaprojects: on peluqueriajenver's first real deploy, `deploy.sh`
     put the site in maintenance mode and then `git pull` failed on an
     SSH problem with GitHub, leaving the site down with nothing to roll
     forward to. Checking this first means an SSH/access problem, or a
     genuinely diverged branch, is reported with the site still live and
     before sudo is even requested.
  2. Refuses to run as root or with a dirty working tree, fixes
     `storage`/`bootstrap/cache` ownership and permissions up front (in
     case an earlier root `artisan` call left something it owns), asks
     for `deploy`'s sudo password once at the start and again right
     before enabling maintenance mode — the last point it is safe to
     prompt — then enters maintenance mode **before** pulling, so every
     step that changes code (`git pull`, `composer install`,
     `npm run build`) happens inside the controlled downtime window
     instead of in front of live traffic (`npm run build` empties
     `public/build` before writing to it, which is what makes this order
     matter: the site 500s on `ViteManifestNotFoundException` for as long
     as `public/build/manifest.json` is missing).
  3. Runs migrations, `optimize`, fixes the same permissions again
     (non-interactively, with `sudo -n`, so an expired credential fails
     fast instead of hanging the site in maintenance mode), runs
     `php artisan deploy:check` **as `www-data`** before leaving
     maintenance mode, and finally checks `/`, `/contacto`,
     `/avisos-legales` and `/sitemap.xml` on the live site (each request
     capped at 20s; `/sitemap.xml` is included because it already broke
     once in this project, see "Known traps"). On any failure — including
     a non-200 response or an unreachable site in that last check — it
     stays in maintenance mode rather than risk exposing a half-deployed
     site, and prints what to do next.
  4. Its executable bit is tracked in Git
     (`git update-index --chmod=+x deploy.sh`), so a fresh checkout never
     needs a local `chmod +x` that `core.fileMode` would then see as an
     uncommitted change (see Developer Brain's `knowledge/vps-ovh.md`,
     "Trampa de `core.fileMode`" — this already bit this project once).
- **Standing rule: never run `php artisan` as root on the VPS** — always
  as `deploy` (`sudo -u deploy php artisan ...` or `su - deploy`). An
  `artisan` call run as root can leave files PHP-FPM cannot write to,
  500ing every page until the ownership is fixed by hand.
- `php artisan deploy:check` — the check `deploy.sh` runs as `www-data`
  before leaving maintenance mode. Fails (exit 1) if `APP_ENV` is not
  `production`, `APP_DEBUG` is not `false`, `APP_URL` does not start with
  `https://`, or if `storage/framework/views`, `storage/logs`,
  `storage/framework/cache` or `bootstrap/cache` is not writable by
  whoever runs it.

### What this project does not need

- **No `contact:notify-pending`-style retry command.** cobaprojects has
  one because its `/contacto` page has a real form that saves a row and
  sends an email synchronously; this project's `/contacto` is a static
  page (phone and WhatsApp links only, see "Project" above), with no form,
  no `ContactSubmission`-like model and no outgoing mail of its own. If
  that changes — for instance if the unmerged `claude/email-discount-code`
  branch, which adds a hero email form for a discount code, is ever
  merged — revisit this and consider the same pattern.
- **No cron entry.** Nothing above needs one without the retry command.

### Before the first real deploy

These are unresolved prerequisites, not yet done — the next deploy is the
first time any of this gets confirmed:

- **VPS path:** not yet annotated anywhere (`developer-brain/knowledge/vps-ovh.md`
  lists it as "sin anotar"; this repository's own `NGINX-CACHE-CONFIG.md`
  only guesses `/etc/nginx/sites-available/peluqueriajenver.com` "o
  similar"). Confirm the real checkout path on the VPS during the first
  deploy and record it in `developer-brain/knowledge/vps-ovh.md`.
- **`deploy`'s SSH access to GitHub:** the user manages one deploy key per
  repository, with a per-repo alias in `~/.ssh/config` — for this project,
  `github-peluqueriajenver`. Confirm the key and the alias are set up for
  the `deploy` user (not root) before the first `./deploy.sh` run, or the
  new remote/divergence check above will fail immediately (by design —
  that is the point of checking it first).
- **`storage`/`bootstrap/cache` ownership:** unlike cobaprojects and
  obranur, this has not been checked on the VPS yet for this project. Run
  `ls -ld storage bootstrap/cache` (and the first level below them) before
  the first `./deploy.sh` run and fix ownership by hand
  (`chown -R deploy:www-data storage bootstrap/cache`) if it is not
  already `deploy:www-data`-writable — `deploy.sh`'s `fix_permissions`
  re-applies this on every run, but the very first `php artisan` call,
  before `deploy.sh` has run even once, needs it too.

## How to verify locally

- `docker compose exec app php artisan test` runs the Pest suite
  (`tests/Feature`, `tests/Unit`) inside the container, against an
  in-memory SQLite database (`phpunit.xml` forces this — see its comments);
  it never touches the `mysql` service, so there is no separate testing
  database to manage.
- `docker compose exec node npm run build` compiles Tailwind/Vite assets
  into `public/build`; the `node` service already runs `npm run dev` with
  hot-module reload against `http://localhost:5175`.
- Visit `http://localhost:8082` to check pages manually — there is no
  visual regression tooling.
- Run Pint inside the container too:
  `docker compose exec app vendor/bin/pint --dirty --format agent`.

## Known traps

- Commercial copy (service pages, meta descriptions, FAQ) must only state
  facts the user has confirmed — see the `programador` agent's rules on
  commercial copy before writing marketing text.
- `phpunit.xml` forces `DB_CONNECTION=sqlite` for tests, so the `app`
  image needs the `pdo_sqlite` PHP extension in addition to `pdo_mysql`
  (used for the real `mysql` service) — both are installed in
  `docker/php/Dockerfile`. The host PHP install on this machine does not
  have `pdo_sqlite` at all, which is one more reason tests only run inside
  the container, never from the host.

## Working agreements

- Use TDD, Clean Code, SOLID, and PSR-12.
- Write source code, comments, tests, pull-request text, and commit messages
  in English.
- Branches: `feature/<short-description>`, `fix/<short-description>`, or
  `chore/<short-description>` for tooling/environment changes with no
  behavior change (matching cobaprojects/obranur, e.g.
  `chore/docker-local`).
- Images served to visitors are optimized WebP with a `<picture>`/`srcset`
  fallback (the existing `<x-optimized-image>` component and the hero
  `<picture>` already follow this). PNG/JPG-only exceptions are limited to
  favicon/app icons, emails, PDFs, and `og:image`.
- Claude implements through the `programador` subagent (plan first, implement
  after user approval) and resolves reviews. Independent review is done by
  Claude in a clean context, writing findings only to `.ai/reviews/`; the
  reviewer does not edit application code, tests, configuration, or
  migrations.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
