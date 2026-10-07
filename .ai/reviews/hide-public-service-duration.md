# Review: feature/hide-public-service-duration (T061)

Reviewer: Claude (independent, clean context). Scope: `main..feature/hide-public-service-duration`
(commit `ff9e46c`), task `.ai/tasks/reservas/T061-ocultar-duracion-publica.md`
(PRF-149, PRF-150; PRF-027, PRF-039, PRF-050, PRF-051, PRF-082, PRF-127,
PRF-130 ajustados in `.ai/specs/reservas.md`). No application/test/config
files were modified during this review; only this report was written.

## Method

- Read `AGENTS.md`, the `review` skill, and the task file.
- Read the full diff (`git diff main..feature/hide-public-service-duration`):
  spec, task index/matrix, 5 view/mail files, 4 test files, 1 new test file.
- Read every customer-facing email template
  (`appointment-confirmed`, `appointment-cancelled`,
  `appointment-rescheduled`) and the two salon-facing ones
  (`new-appointment`, `customer-cancelled-appointment`), plus the shared
  partial `mail/partials/appointment-services.blade.php`.
- Read `resources/views/pages/reservas.blade.php` in full (not just the
  diff) and its two includes (`reservas-calendar`, `reservas-form`), and
  `resources/views/pages/cita.blade.php` in full.
- Grepped the whole app (`resources/views`, `lang/`, `app/`) for
  `duration_label`, `duration_minutes`, `formatDuration`, `ends_at`,
  `data-minutes`, `total_label`, `.ics`/`VCALENDAR` to find every place a
  duration or an end time could leak, and checked each hit individually.
- Read `app/Http/Controllers/BookingController.php` in full to confirm the
  duration sum it computes for slot-finding is never passed into the view.
- Checked the two JSON-LD partials (`schema-service.blade.php`,
  `schema-local.blade.php`): they describe static marketing service names,
  not catalogue `Service` records, so they have no duration to leak.
- Ran the new and the touched tests inside Docker
  (`docker compose exec -u www-data app php artisan test …`): the new
  `HidePublicServiceDurationTest` (9/9), plus
  `MultiServiceBookingTest`, `MultiServiceDisplayTest`, `PublicBookingTest`,
  `CustomerAppointmentTest`, `AppointmentNotificationsTest` (94/94, 410
  assertions) — all green.
- Ran Pint (`--test --format agent`) on every changed `.blade.php`/test
  file — pass.
- Rendered `NewAppointmentMail` and `AppointmentConfirmedMail` by hand with
  a 2-service appointment (`php artisan tinker`) to inspect the actual
  compiled HTML: confirmed the Markdown list renders as one clean `<ul>`
  with no broken list split by the new `@if($showDuration)` block, and that
  the salon email shows "Corte (30 min), Barba (15 min)" + "Duración total:
  45 min" while the customer email shows only "Corte, Barba".
- Confirmed the new test is not a test that can't fail: before this change
  (`git show main:…`), `reservas.blade.php` rendered `$item->duration_label`
  and `data-minutes`, `cita.blade.php` rendered
  `Service::formatDuration(...)`, and the 3 customer mails included the
  partial with no `showDuration` argument (so it defaulted to the old,
  always-shown behavior) — i.e. every string the new tests assert absent
  was actually present before.

## Findings

### No findings — no leak of duration or end time to the customer

- `/reservas` step 1 (`reservas.blade.php`): `duration_label` span and the
  `data-minutes` attribute on each checkbox are both removed; the
  `#service-total-preview` live-total paragraph and its
  `@include('partials.service-total-script', …)` are removed with it. The
  shared `partials/service-total-script.blade.php` itself is untouched and
  still used by the two admin forms (`admin/appointments/create.blade.php`,
  `edit.blade.php`), so nothing there was orphaned.
- `/reservas` step 2/3 header (same file, the `@else` branch, shared by
  steps 2 and 3): the `· Duración total: …` suffix after the service list
  is removed; only `ServiceList::label(...)` (names) remains.
- `/cita/{token}` (`cita.blade.php`): each service now renders
  `$item->service_name` alone (no `(duration)`), and the
  `reservas.total_label` paragraph below the list is deleted outright. The
  "when" row uses only `$appointment->starts_at` — it never rendered
  `ends_at` even before this change (PRF-039 already said "nunca la de
  fin" pre-dating this task), so there was nothing to remove there, and
  nothing reintroduces it.
- Mail partial (`mail/partials/appointment-services.blade.php`): the new
  `showDuration` parameter (`@php($showDuration ??= false)`) is `false` by
  default, so every include with no extra argument — the 3 customer
  emails — gets the name-only branch and skips the `Duración total` line
  entirely (`@if ($showDuration)` wraps it). Confirmed by reading all 3
  customer templates: none passes `showDuration`.
- The two salon emails (`new-appointment.blade.php`,
  `customer-cancelled-appointment.blade.php`) explicitly pass
  `['showDuration' => true]` and keep their own `{{ $appointment->starts_at
  }}–{{ $appointment->ends_at }}` line untouched — both duration and end
  time are preserved there, as intended.
- Grepped every `.blade.php` for `duration_label`, `duration_minutes`,
  `formatDuration`, `data-minutes` outside `resources/views/admin/`: the
  only remaining hit outside `admin/` is the mail partial itself (which now
  gates it behind `$showDuration`). No public page, no lang string, no
  `aria-*`, no JSON-LD, and no `.ics`/calendar file exists in this project
  to carry a duration or an end time — confirmed by a repo-wide search for
  `.ics`/`VCALENDAR` (no matches).
- The two structured-data partials (`schema-service.blade.php`,
  `schema-local.blade.php`) list static marketing category/service names
  hand-written in PHP arrays, unrelated to the `Service` catalogue model —
  they carry no duration field to begin with, so PRF-149 does not apply to
  them and nothing needed changing.
- `BookingController::index()`/`store()` still compute
  `$durationMinutes = $selectedServices->sum('duration_minutes')` to feed
  `AvailabilityCalculator` (slot-finding, per PRF-025/PRF-121, explicitly
  unaffected by PRF-149), but that value is never added to the array passed
  to `view('pages.reservas', [...])` — confirmed by reading the full method
  body. No accidental leak through a view variable.

### No findings — the panel and the two salon emails are unaffected

- `admin/services/index.blade.php`, `admin/appointments/create.blade.php`
  and `edit.blade.php`, `admin/agenda/_day.blade.php` and
  `_timeline-column.blade.php`, `admin/agenda/index.blade.php` are all
  untouched by this diff (confirmed by `git diff --stat`: zero files under
  `resources/views/admin/` appear) and still read
  `duration_label`/`formatDuration`/`ends_at` directly — verified by
  grepping each file individually (see Method). The new
  `HidePublicServiceDurationTest` also exercises
  `admin.services.index` and asserts `2 h` is still shown there.
- The rendered-HTML check above confirms the two salon mails keep both the
  per-service duration, the total, and the `10:00–12:00` end-time range,
  byte for byte the same markup shape as before.

### Informational — `lang/es/reservas.php`'s `total_label` key is now dead code

**File:** `lang/es/reservas.php:29` (`'total_label' => 'Duración total'`).

Before this change, `total_label` was read in two places:
`reservas.blade.php` (step 1's live-preview script label and the step 2/3
header's "· Duración total: …" suffix) and `cita.blade.php`'s total-duration
paragraph. Both call sites were removed by this diff, and nothing else in
the codebase reads `reservas.total_label` any more — confirmed by grepping
`resources/` and `app/` for `total_label` after the change (only the
definition itself remains). The admin views that still show "Duración
total" use the literal Spanish string directly, not this translation key,
so they are unaffected either way.

**Impact:** none functionally — an unused translation key does not render
anywhere and cannot leak. It is only dead weight that a future reader might
assume is still wired to something.

**Recommendation:** delete the `total_label` line from
`lang/es/reservas.php` in a follow-up cleanup (or in `fix-review` for this
task), since it is now unreferenced.

**Resolution (`fix-review`, same branch):** confirmed with a repo-wide
grep (`resources/`, `app/`, `lang/`, `tests/`, `.ai/`) that the only
remaining hit was the definition itself; deleted the `'total_label' =>
'Duración total',` line from `lang/es/reservas.php`. Full Pest suite and
Pint re-run clean after the deletion (see commit for the test/Pint
output). Status: **Resolved**.

### No findings — spec and coverage matrix are coherent with the code

- PRF-149/PRF-150 (new) and the adjustments to PRF-027, PRF-039, PRF-050,
  PRF-051, PRF-082, PRF-127, PRF-130 in `.ai/specs/reservas.md` accurately
  describe the final code: each adjusted point's "modificado"/wording
  matches what the diff actually does (e.g. PRF-051 now explicitly says
  "este correo es interno y no está sujeto a PRF-149/PRF-150", matching
  `new-appointment.blade.php` passing `showDuration: true`).
- CA-21 and the two new matrix rows (PRF-149, PRF-150, both `covered`) are
  consistent with the test evidence: `HidePublicServiceDurationTest` alone
  covers both end-to-end (public page, customer page, 3 customer emails,
  2 salon emails, admin panel), and the matrix also correctly points the
  pre-existing PRF-027/PRF-039/PRF-050/PRF-130 rows at the updated
  assertions in `PublicBookingTest`/`MultiServiceBookingTest`/
  `MultiServiceDisplayTest` rather than leaving them referencing removed
  behavior.
- The task file's "Resolución" section matches the diff file-by-file (every
  file it names was actually touched the way it describes, and no file it
  says was left alone was actually touched).

### No findings — test quality (would fail if the leak came back)

- `HidePublicServiceDurationTest` and the rewritten assertions in
  `PublicBookingTest`/`MultiServiceBookingTest`/`MultiServiceDisplayTest`
  assert the *absence* of concrete, non-generic strings (`'2 h'`,
  `'Duración total'`, `'data-minutes'`, the specific end time `'12:00'`)
  rather than relying on a passing test count, and in the same test also
  assert the *presence* of the service name and the start time, so a test
  that accidentally stopped rendering anything would not pass vacuously.
- Verified against `main` that every blocked string was actually rendered
  pre-fix (see Method), so none of these are tests that could never fail.
- The salon-side test (`'the 2 emails to the salon keep showing the
  duration, the total and the end time'`) and the admin-panel test guard
  the other direction, so a future change that accidentally hid the
  duration from the salon too would also be caught.

## Severity summary

| # | Severity | File | Status |
|---|----------|------|--------|
| 1 | Informational | `lang/es/reservas.php:29` | Resolved — `total_label` key deleted (dead code, confirmed unreferenced) |

No blocking, critical, or moderate findings. No leak of service duration,
appointment total duration, or end time to the customer was found in
`/reservas`, `/cita/{token}`, the 3 customer emails, the page source
(`data-*`), JSON-LD, `lang/es/*`, validation/flash messages, or any other
public surface. The panel and the 2 salon emails keep showing both values
unchanged. All 94 relevant Pest tests and Pint pass.
