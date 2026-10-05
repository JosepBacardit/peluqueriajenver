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
The online booking system is specified in `.ai/specs/reservas.md`, with its
tasks and coverage matrix in `.ai/tasks/reservas/index.md`.

## Project

Peluquería Jenver is a marketing/SEO website, now growing an online booking
system and a hand-made admin panel (see "Booking system and admin panel"
below), for a unisex hair salon in Montcada i Reixac (Barcelona), specialized
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

Public marketing routes are plain closures in `routes/web.php` (home, 4
service pages, `/contacto`, 3 legal pages with `noindex`, plus
`/sitemap.xml`); the booking pages and the admin panel use controllers.
Content strings live in `lang/es/*.php`; `lang/en/` only has Laravel's own
default files — the site itself is Spanish-only. Because of that,
`config/app.php` pins `locale` to `es` (not read from `APP_LOCALE`, so a
stale production `.env` cannot switch validation messages to English) and
`timezone` to `Europe/Madrid` (every booking time is salon-local).
`lang/es/validation.php` holds the Spanish validation messages.

Besides the booking system, customers still book by phone
(`tel:+34633912050`) or WhatsApp (`wa.me/34633912050` links, with a
pre-filled message per page); the salon records those in the admin agenda.

## Booking system and admin panel

- **Admin panel** at `/admin`, hand-made Blade (no Filament/Livewire, user
  decision 2026-10-03), behind Laravel's session `auth` with a hand-made
  login (`Admin\LoginController`, 5 failed attempts per minute per
  email+IP). There is no public registration and no password reset screen.
  Every account has the same permissions.
- **Accounts:** created (or their password changed) only with
  `php artisan admin:create-user`, which asks for the password
  interactively. The repository is public: never seed accounts or commit
  credentials (`DatabaseSeeder` is intentionally empty). On the VPS run it
  as `deploy`, like every other `artisan` call.
- **Adding a module:** add one entry to the `$modules` array at the top of
  `resources/views/layouts/admin.blade.php` plus its routes inside the
  `auth` group in `routes/web.php`. Admin UI strings are written directly
  in Spanish in the admin views (internal tool, not SEO content), unlike
  public copy, which lives in `lang/es/`.
- **Booking rules** (capacity, slot interval, min/max notice, cancellation
  limit) live in the single-row `booking_settings` table and the weekly
  schedule in `opening_hours`; both are created with their default values
  by their migrations. Services are not seeded: the salon enters them.
- **Public booking** at `/reservas` (server-rendered Blade, no JS; a
  vanilla-JS calendar is a possible later step) and the customer's page
  `/cita/{token}` (random 48-character token, `noindex`). Both are excluded
  from the public HTML cache in `App\Http\Middleware\CacheHeaders` (free
  times change constantly and the forms carry a CSRF token). Availability
  lives in `App\Booking\AvailabilityCalculator`; every booking (web or
  admin) goes through `App\Actions\CreateAppointment`, which re-checks
  availability under a row lock on `booking_settings` so concurrent
  bookings cannot overbook. Prices are internal: never render
  `price_cents` on a public page or a customer email
  (`PublicPagesHaveNoPublicPricingTest` and the booking tests guard this).
- **Email** is sent synchronously (no queue worker) by
  `App\Booking\AppointmentNotifier`: confirmation with the personal link to
  the customer, notice of web bookings and customer cancellations to
  `BOOKING_NOTIFICATION_EMAIL`, and cancellation emails. A failed send is
  logged and never undoes the booking; failed creation notices stay with a
  null `customer_notified_at`/`salon_notified_at` and are retried by
  `php artisan appointments:notify-pending` (cron, see "Production
  deploys").

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

There is no queue worker or scheduler: booking emails are sent
synchronously (locally `MAIL_MAILER=log` writes them to
`storage/logs/laravel.log`), even though `QUEUE_CONNECTION=database`
matches production — there is nothing to consume. Locally, run
`docker compose exec app php artisan appointments:notify-pending` by hand
if you need to exercise the retry.

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

Never run `migrate:fresh`, `migrate:refresh`, `migrate:reset`,
`migrate:rollback`, `db:wipe`, or a manual `DROP`/`TRUNCATE` against
production. If `migrate:status` shows anything unexpected, stop and ask the
user before proceeding. `migrate:rollback` is on the list because the
booking migrations' `down()` drops `appointments` and the other booking
tables, which hold customers' personal data: rolling back a release means
reverting its commits (`git revert`, push, `./deploy.sh`) and leaving the
new tables in place unused, never undoing migrations.

## Production deploys

The VPS has nginx and PHP-FPM installed directly (no Docker in production),
and PHP-FPM runs as `www-data`. This reuses the pattern built and
independently reviewed for cobaprojects (`deploy.sh`, `deploy:check`,
`.ai/reviews/deploy-safety.md` in that repository), adapted to what this
project actually has — see "What this project does not need" below.

- `./deploy.sh`, run as the non-root `deploy` user from the checkout,
  deploys whatever is already pushed to the branch it is on (it only
  fast-forwards; it never switches branches). Order of operations:
  1. Refuses to run as root or with a dirty working tree, fixes
     `storage`/`bootstrap/cache` ownership and permissions up front (in
     case an earlier root `artisan` call left something it owns), asking
     for `deploy`'s sudo password once at the start for this.
  2. Still with the site live, checks that GitHub is reachable and the
     branch can fast-forward: `git fetch` (no remote argument — it uses
     the branch's configured upstream), then that `@{u}` actually
     resolves (the branch has an upstream configured), then
     `git merge-base --is-ancestor HEAD "@{u}"`. This preflight — and the
     later "Pulling the latest code" step merging `@{u}` instead of
     pulling again, so it reuses this exact fetch instead of risking a
     second one — mirrors cobaprojects' `deploy.sh`: on **that**
     project's first real deploy, it put the site in maintenance mode and
     only then found `git pull` failing on an SSH problem with GitHub,
     leaving the site down for nothing. This project has not had its
     first real deploy yet, so it gets the same preflight from the start
     rather than waiting to hit the same problem. Any of the three checks
     failing aborts here, with the site still live and before the second
     `sudo` prompt: a failed `fetch` points at `deploy`'s SSH key/alias, a
     missing upstream at `git branch --set-upstream-to=origin/<branch>`,
     a diverged branch at fixing it by hand (rebase, reset) — including a
     detached `HEAD`, which also has no `@{u}` to resolve.
  3. Asks for `deploy`'s sudo password again right before enabling
     maintenance mode — the last point it is safe to prompt — then enters
     maintenance mode **before** pulling, so every step that changes code
     (merging `@{u}`, `composer install`, `npm run build`) happens inside
     the controlled downtime window instead of in front of live traffic
     (`npm run build` empties `public/build` before writing to it, which
     is what makes this order matter: the site 500s on
     `ViteManifestNotFoundException` for as long as
     `public/build/manifest.json` is missing).
  4. Runs migrations, `optimize`, fixes the same permissions again
     (non-interactively, with `sudo -n`, so an expired credential fails
     fast instead of hanging the site in maintenance mode), runs
     `php artisan deploy:check` **as `www-data`** before leaving
     maintenance mode, and finally checks `/`, `/contacto`, `/reservas`,
     `/avisos-legales`, `/sitemap.xml` and `/up` on the live site (each
     request capped at 20s). `/sitemap.xml` is included because it
     already broke once in this project (see "Known traps"). `/up` is
     Laravel's own health route (`health: '/up'` in `bootstrap/app.php`)
     and is the only one of the five that compiles a Blade view on every
     request instead of serving an already-compiled one, so it is the
     one that actually catches a `storage/framework/views` permissions
     problem — the other four can keep returning 200 from an
     already-compiled view even when that directory is unwritable. On any
     failure — including a non-200 response or an unreachable site in
     that last check — it stays in maintenance mode rather than risk
     exposing a half-deployed site, and prints what to do next; if the
     failure happened after leaving maintenance mode, it explicitly does
     **not** suggest `git checkout <previous-commit> && ./deploy.sh` —
     the preflight above only ever fast-forwards, so it would refuse to
     deploy a commit that is not a descendant of the current one — and
     instead points at `git revert <commit>`, pushed, then `./deploy.sh`
     again.
  5. Its executable bit is tracked in Git
     (`git update-index --chmod=+x deploy.sh`), so a fresh checkout never
     needs a local `chmod +x` that `core.fileMode` would then see as an
     uncommitted change (see Developer Brain's `knowledge/vps-ovh.md`,
     "Trampa de `core.fileMode`" — that already bit this project once,
     unrelated to deploying).
- **Standing rule: never run `php artisan` as root on the VPS** — always
  as `deploy` (`sudo -u deploy php artisan ...` or `su - deploy`). An
  `artisan` call run as root can leave files PHP-FPM cannot write to,
  500ing every page until the ownership is fixed by hand.
- `php artisan deploy:check` — the check `deploy.sh` runs as `www-data`
  before leaving maintenance mode. Fails (exit 1) if `APP_ENV` is not
  `production`, `APP_DEBUG` is not `false`, `APP_URL` does not start with
  `https://`, if `APP_NAME` is still the skeleton default (`Laravel`) —
  every booking email carries this name as its sender and in its branded
  theme — if `storage/framework/views`, `storage/logs`,
  `storage/framework/cache` or `bootstrap/cache` is not writable by
  whoever runs it, or if booking email cannot really be sent:
  `MAIL_MAILER` is `log`/`array`/empty, the SMTP `MAIL_HOST` is empty or
  local, `MAIL_FROM_ADDRESS` is missing or `hello@example.com`, or
  `BOOKING_NOTIFICATION_EMAIL` is missing or invalid; and if
  `SESSION_SECURE_COOKIE` is not `true` (the admin session cookie must be
  https-only). `deploy.sh` runs the same mail-shape checks against `.env`
  in its preflight (`check_env_mail`), before maintenance mode, so a
  missing setting no longer causes an outage. `php artisan deploy:check
  --smtp` additionally connects and logs in to the SMTP server without
  sending anything: run it by hand (as `deploy`) after setting the mail
  credentials, since wrong credentials otherwise only show up as failed
  sends in the log. SMTP calls time out after `MAIL_TIMEOUT` seconds
  (default 10), so a dead mail server cannot hang a booking.

### Cron (booking email retry)

`appointments:notify-pending` resends the booking confirmations and salon
notices that failed when the appointment was made (only for upcoming
confirmed appointments created more than 5 minutes ago; each notice is
sent once). It needs one entry in the **`deploy` user's** crontab
(`crontab -e` as `deploy`, never root), with `flock` so two runs never
overlap:

```
*/10 * * * * cd /var/www/peluqueriajenver && flock -n /tmp/peluqueriajenver-notify-pending.lock php artisan appointments:notify-pending >> /dev/null 2>&1
```

There is no Laravel scheduler (`schedule:run`) entry: nothing else needs
one yet. A day-before reminder would add it (planned as a later PR).

### Booking abuse limits and cleanup

Online bookings are capped at 5 submissions per minute and 10 per day per
IP (`booking-submissions` limiter in `AppServiceProvider`) and at 2
upcoming confirmed online appointments per email or per phone (compared by
its last 9 digits; `CreateAppointment::MAX_UPCOMING_ONLINE`). Bookings made
from the admin panel are not capped. If a flood of fake bookings still gets
through (e.g. from many IPs), find them read-only first, for example
`php artisan tinker --execute 'App\Models\Appointment::where("source","web")->where("created_at",">=",now()->subDay())->orderBy("created_at")->get(["id","customer_email","customer_phone","starts_at","created_at"])->each(fn($a)=>print($a->toJson().PHP_EOL));'`,
confirm with the user which ones are fake, and cancel them (never delete:
`status`/`cancelled_at`, through the panel or `App\Actions\CancelAppointment`).
If it keeps happening, consider a cookie-less CAPTCHA (Turnstile, Friendly
Captcha) on `/reservas`.

### Before deploying the booking system (PRs 1-4)

Blocking prerequisites, all pending as of 2026-10-03:

- **Mailbox and SMTP:** the salon's sending mailbox (likely on Hostalia,
  like obranur/cobaprojects: `smtp.servidor-correo.net:587`) is not
  decided. Set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`,
  `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`,
  `MAIL_FROM_NAME` and `BOOKING_NOTIFICATION_EMAIL` in the VPS `.env`
  **before** running `./deploy.sh`, then `php artisan optimize` as
  `deploy`; otherwise `deploy:check` fails with the site already in
  maintenance mode (`deploy.sh` now also refuses to start, with the site
  still live, if they are missing). Configure SPF/DKIM/DMARC for the
  sending domain, then run `php artisan deploy:check --smtp` as `deploy`.
- **`SESSION_SECURE_COOKIE=true`** in the VPS `.env` (required by
  `deploy:check`).
- **Analytics consent (review finding M4, pending the user's decision):**
  GTM/GA4/Ahrefs still load before any cookie choice on every page except
  `/cita/{token}`, while the privacy policy says non-technical cookies rely
  on consent. Either gate them on consent or fix the policy text before
  going live (`.ai/reviews/reservas.md`).
- **Privacy policy data:** `/privacidad` shows "[Pendiente de confirmar
  …]" markers for the data controller's legal name, NIF, contact email
  and the retention period. The client must provide them before going
  live.
- **Migrations:** the release adds five tables (`services`,
  `opening_hours`, `booking_settings`, `appointments`, `schedule_blocks`),
  additive only. Check `php artisan migrate:status` on the server first
  (see "Production database").
- **Admin account:** after deploying, create at least one with
  `php artisan admin:create-user` (as `deploy`), then let the salon enter
  its services in `/admin/servicios`. Until a bookable service exists,
  `/reservas` shows the "call or WhatsApp" message.
- **Cron:** add the entry above.
- **nginx:** confirm the server config adds no HTML caching of its own for
  `/reservas` or `/cita/` (the app already sends `no-store` for them).

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
  `find storage bootstrap/cache \( ! -user deploy -o ! -group www-data \) -print`
  before the first `./deploy.sh` run — unlike `ls -ld`, which only shows
  the top level, this walks every file and directory underneath, which is
  where a stray root-owned entry actually breaks a deploy. Fix ownership
  by hand (`chown -R deploy:www-data storage bootstrap/cache`) if it
  prints anything — `deploy.sh`'s `fix_permissions` re-applies this on
  every run, but the very first `php artisan` call, before `deploy.sh`
  has run even once, needs it too.
- **`.env` values `deploy:check` requires:** confirm in the VPS `.env`
  itself, before the first `./deploy.sh` run, that `APP_ENV=production`,
  `APP_DEBUG=false` and `APP_URL=https://www.peluqueriajenver.com` — if
  any of them is wrong, `deploy.sh` will reach `deploy:check` with the
  site already in maintenance mode and fail there instead of up front
  (the behavior is safe — the site just stays down until it is fixed —
  but it is an avoidable maintenance window). After changing `.env` by
  hand, run `php artisan optimize` as `deploy` (not root): configuration
  is cached, so edits to `.env` are invisible until it is rebuilt.

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
- `/sitemap.xml` 500s with `short_open_tag=On` (the case in the Docker
  `app` image, see `docker/php/Dockerfile`): the leading literal `<?xml`
  compiles as a PHP open tag instead of being emitted as text. Production
  has `short_open_tag=Off` and never showed this. Fixed in `d65c8d8` by
  emitting the XML declaration so it survives either setting;
  `tests/Feature/SitemapTest.php` guards against a regression.

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
