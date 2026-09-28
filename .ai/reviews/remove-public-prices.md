# Review: chore/boost-and-agents + fix/remove-public-prices

Reviewer: Claude (independent, clean context). Scope: `chore/boost-and-agents`
(f404e0d, from `main`) and `fix/remove-public-prices` (66a1eae, from the
former). No application/config/test files were modified during this review;
only this report was written.

## Method

- Read `chore/boost-and-agents` full diff stat vs `main`, and the full diff
  `chore/boost-and-agents...fix/remove-public-prices`.
- Read `AGENTS.md`, `.claude/agents/programador.md`, `composer.json`,
  `composer.lock`, `.mcp.json`, `boost.json` at `fix/remove-public-prices`
  (current checkout).
- Started the app with
  `SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync php artisan
  serve --port=8099` (no `.env` changes) and curled all 6 required pages
  (`/`, `/contacto`, `/color-y-mechas`, `/corte-y-tratamientos`,
  `/peinados-eventos`, `/belleza-estetica`): all return HTTP 200.
- Ran `php artisan test --compact` with the same env vars: 8/8 tests pass
  (2 example + 6 dataset cases of the new pricing test).
- Verified JSON-LD blocks on `/` and `/color-y-mechas` with `json_decode`
  (all valid, no dangling commas).
- Grepped `€`, `euros?`, `tarifa`, `desde [0-9]+` across
  `resources/`, `lang/`, `public/`, `routes/`, `config/`, `app/`: no matches
  outside the intentional "precio exacto" CTA wording.
- Confirmed against `git show chore/boost-and-agents:<file>` that the removed
  price blocks/keys were the ones actually rendered (or were dead code), and
  that the new test's blocked strings were present pre-fix (so the test is
  not a test that can't fail).

## Findings

### 1. Moderate — `AGENTS.md` still documents the contact form as a live trap after it was removed

**File:** `AGENTS.md:66-70` (Known traps section), current content:

> `resources/views/pages/contacto.blade.php` has a "¿Prefieres que te
> contactemos?" block with input fields and a submit button that is **not**
> inside a `<form>` and has no backing route or JS handler — it does nothing
> when clicked. Do not leave it in place silently; any change touching
> contact needs the user's explicit direction on what replaces it.

`fix/remove-public-prices` deletes exactly this block from
`resources/views/pages/contacto.blade.php` (37 lines removed, per the user's
decision to remove it without replacement). `AGENTS.md` was not updated in
either branch and still describes the dead form as present and actionable.

**Impact:** a future agent (or the user) reading `AGENTS.md` will believe the
broken form still exists and may re-litigate a decision that is already
resolved, or waste time looking for a block that is gone. This is exactly the
pattern the project's own `programador` agent is instructed to avoid —
`.claude/agents/programador.md:99`: "Si has eliminado o renombrado algo...
busca con grep los comentarios, docblocks y textos que lo mencionen y
actualízalos en el mismo cambio."

**Recommendation:** update the "Known traps" bullet to state the form was
removed (2026-xx-xx) per the user's decision, or delete the bullet entirely
now that the trap no longer exists.

**Resolution (2026-09-28, `programador`):** deleted the "Known traps" bullet
about the contact form in `AGENTS.md` (the trap no longer exists — the form
was removed on `fix/remove-public-prices`). Kept the remaining "Commercial
copy" bullet. `php artisan test` still green after the edit. Also confirmed
with the user that the home page's visible testimonials (Alicia Egea, Josep
Bacardit, Raquel Iglesias, `resources/views/pages/home.blade.php:267-320`)
are real and stay untouched — nothing in `AGENTS.md` or this report claimed
otherwise, so no further change was needed on that point.

### 2. Informational — WhatsApp CTA links open in a new tab without warning assistive-tech users, but this matches the existing site-wide pattern

**Files:** `resources/views/partials/whatsapp-cta.blade.php:3`,
`resources/views/pages/services/belleza_estetica.blade.php` (3 inline
"Pregúntanos por WhatsApp" links).

All new WhatsApp links use `target="_blank" rel="noopener noreferrer"` with
link text limited to "WhatsApp" / "💬 WhatsApp" / "Pregúntanos por WhatsApp",
with no `aria-label`/visually-hidden text indicating the link opens a new
tab. `rel="noopener noreferrer"` is correctly present (security), and the
surrounding paragraph gives context that satisfies WCAG 2.4.4 (link purpose
in context).

This is **not a regression**: the exact same pattern (`target="_blank"
rel="noopener noreferrer"`, no new-tab warning) is already used everywhere
else in the site for outbound links (Instagram, TikTok, Google Maps, and the
pre-existing WhatsApp CTAs in `home.blade.php`). Logging as informational
only; fixing it would be a site-wide change out of scope for this diff.

### 3. Informational — no image changes in this diff

Neither branch touches any `<img>`/`<picture>` markup or image assets, so the
WebP/`<picture>`-with-PNG-fallback check does not apend here. Nothing to
flag beyond what the project wiki may already record.

### 4. No findings — Laravel Boost scoping and production safety

- `composer.json`: `laravel/boost` is correctly in `require-dev` only.
- `composer.lock`: confirmed via script that `laravel/boost` (and its
  dependencies `laravel/mcp`, `laravel/roster`, `composer/semver`,
  `symfony/yaml`) appear only in `packages-dev`, not in `packages` — a
  production `composer install --no-dev` will not install Boost.
- `bootstrap/cache/` (where a stale `packages.php`/`services.php` could
  otherwise hardcode `Laravel\Boost\BoostServiceProvider` and break
  production if Boost were later removed from `vendor/`) is fully
  gitignored (`bootstrap/cache/.gitignore` contains `*` / `!.gitignore`,
  and `git ls-files bootstrap/cache/` shows only `.gitignore` tracked), so
  no compiled provider cache is shipped via `git pull`.
- `boost.json`: `"agents": ["claude_code"]` — Boost is scoped to Claude Code
  only, matching the user's decision. `.mcp.json` only invokes `php artisan
  boost:mcp` (a dev-only, on-demand command); it does nothing at request
  time and breaks nothing in production if `laravel/boost` is absent (it
  would only fail if literally launched with `php artisan boost:mcp`,
  which nothing in the deployed app does).
- No `.codex`, `.cursor`, or Copilot-specific files (e.g.
  `.github/copilot-instructions.md`) were introduced. The diff does add
  `.agents/skills/*` and `.github/skills/*` alongside `.claude/skills/*`
  (mirrored copies of the Developer Brain skills: `spec`, `tasks`,
  `feature`, `review`, `fix-review`, `architecture-review`). This mirrors
  the same convention already used in `obranur` (confirmed by inspecting
  `obranur/.agents/skills/` and `obranur/.github/skills/`), so it is
  consistent with established practice, not a foreign/leaked agent config.
- `AGENTS.md`'s description of the project (marketing/SEO site, no booking
  app, Laravel 13 + Blade + Tailwind 4, Pest, no CI/CD, manual `git pull`
  deploys, phone/WhatsApp-only booking, plain-closure routes) matches the
  actual codebase and the independently-recorded project notes, aside from
  finding 1 above.

### 5. No findings — price/amount removal is complete

- Grepped the whole app tree (`resources/`, `lang/`, `public/`, `routes/`,
  `config/`, `app/`) for `€`, `euros?`, `tarifa`, `desde [0-9]+`: zero
  matches outside the new CTA copy ("Te decimos el precio exacto en el
  diagnóstico gratuito"), which names no figure.
- Rendered HTML of all 6 pages (verified with curl against a running
  `php artisan serve`) contains none of: `€`, `pricerange`,
  `aggregaterating`, `"review"`, `tarifas orientativas`, `precios
  especiales`, `enviar mensaje`.
- `resources/views/partials/precios.blade.php` was deleted and no
  `@include('partials.precios', ...)` call remains anywhere (grepped).
- `lang/es/home.php`'s removed `todos_servicios` and `servicios` keys (which
  held hardcoded € prices) were **dead code**: `resources/views/pages/home.
  blade.php` never referenced them, in either branch — confirmed by
  grepping the pre-fix `home.blade.php` for `todos_servicios`/`servicios.`
  and finding no match. Their removal is pure cleanup, not a functional
  change.
- `lang/es/servicios.php`'s removed `precios` sub-arrays (per service
  category) were likewise never read by any view — the 4 service pages
  hard-coded their own price tables directly via
  `@include('partials.precios', ['precios' => [...]])`, which is exactly
  what got replaced by `@include('partials.whatsapp-cta', [...])`. No
  orphaned `lang()`/`__()` lookups result from either removal.
- `currenciesAccepted => "EUR"` and `paymentAccepted => "Cash, Credit Card"`
  remain in `schema-local.blade.php` — these are not prices/amounts, so
  correctly left alone (the user only asked to remove `priceRange` and the
  `AggregateRating`/review block).

### 6. No findings — JSON-LD stays valid and `hasMap` deduplication is correct

- `resources/views/partials/schema-local.blade.php`: pre-fix, the PHP array
  literal defined `hasMap` **twice** — once as a plain URL string (next to
  `priceRange`) and once as a proper `Map`-typed object further down; the
  second silently won at array-construction time (a PHP array can't have
  duplicate keys), matching the trap already noted in the project wiki
  (`hasMap` duplicated at lines 49/69, second overwrites first). The fix
  removes the first (string) occurrence and `priceRange`, keeping the
  richer object-form `hasMap` — correct, and it resolves the pre-existing
  wiki trap as a side effect.
- `resources/views/partials/schema-aggregate-rating.blade.php` was deleted
  in full, and its `@include('partials.schema-aggregate-rating')` was
  removed from `resources/views/layouts/app.blade.php:96-98`. No other file
  references it.
- Verified by rendering: `/` now emits exactly 3 `<script
  type="application/ld+json">` blocks (`HairSalon`/`Organization`,
  `BreadcrumbList`, `FAQPage`), all `json_decode`-valid, no dangling commas.
  `/color-y-mechas` emits 3 valid blocks too.
- The **visible** homepage testimonials (Alicia Egea, Josep Bacardit,
  Raquel Iglesias, and the "5★ · Basado en reseñas de Google Business
  Profile" line) are untouched — only the self-reported `AggregateRating`/
  `Review` structured-data block was removed, per the user's decision.

### 7. No findings — `/contacto` layout after removing the dead form block

`<section>`/`</section>` counts are balanced (2/2) and the page ends cleanly
right before `@endsection`. No orphaned markup.

### 8. Informational nit — the new test could assert JSON-LD validity, not just absence of strings

**File:** `tests/Feature/PublicPagesHaveNoPublicPricingTest.php`

The test is solid: it covers exactly the 6 required public pages via a Pest
dataset, asserts `200 OK`, and blocks the exact strings that were removed
(`€`, `pricerange`, `aggregaterating`, `"review"`, `tarifas orientativas`,
`precios especiales`, `enviar mensaje`). It deliberately does **not** block
the bare word "precio" (which would break the new legitimate CTA copy) —
that's a good, deliberate choice, not an oversight.

Verified it is not a test that can't fail: the blocked strings are all
present in the pre-fix (`chore/boost-and-agents`) versions of
`color_mechas.blade.php` et al. (`git show` confirms `desde 80€`, `Tarifas
Orientativas`, etc.), and `php artisan test --compact` passes 8/8 on the
current branch.

One improvement for defense-in-depth: the test never `json_decode()`s the
JSON-LD blocks, so a future edit that reintroduces a syntax error in the
`schema-local`/`schema-breadcrumb` PHP arrays (dangling comma, etc.) would
not be caught by this test. This is optional — not blocking — since this
review manually confirmed JSON-LD validity for this change.

## Severity summary

| # | Severity | File | Status |
|---|----------|------|--------|
| 1 | Moderate | `AGENTS.md:66-70` | Resolved — bullet removed, see resolution note above |
| 2 | Informational | `resources/views/partials/whatsapp-cta.blade.php` + inline links | Pre-existing site-wide pattern, no action required |
| 3 | Informational | n/a | No image changes in this diff |
| 8 | Informational | `tests/Feature/PublicPagesHaveNoPublicPricingTest.php` | Optional strengthening, not blocking |

No blocking or critical findings. Sections 4-7 are explicit "No findings"
verifications, not just skipped checks.
