<?php

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    // Tuesday; default schedule Tue-Sat 09:00-19:00, capacity 2.
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
});

/**
 * PRF-120 (T043): the selector lists "Cualquiera" plus every active
 * service with its duration, never an inactive one.
 */
test('the service selector lists "Cualquiera" and the active services with their duration', function () {
    Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30]);
    Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 120]);
    Service::factory()->inactive()->create(['name' => 'Descatalogado']);

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('>Cualquiera<');
    expect($html)->toContain('Corte (30 min)');
    expect($html)->toContain('Balayage (2 h)');
    expect($html)->not->toContain('Descatalogado');
});

test('the selector is not shown in vista Mes', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->not->toContain('id="servicio"');
});

/**
 * Review finding M1 (T043) / T048: a long service name used to push "Ver"
 * past a 360 px viewport when the filter was a <select> (no "min-width:
 * 0", no "flex-wrap"). T048 replaced the <select> with a <details>
 * disclosure of checkboxes instead, which never forces a row width in the
 * first place — the summary truncates and the checkbox list wraps
 * naturally — exercised here with a ~40-character name, longer than any
 * real service.
 */
test('the service selector never forces a row width, even with a long service name', function () {
    Service::factory()->create(['name' => 'Coloración completa con tratamiento capilar']);

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('<details class="mb-6 text-sm border border-[#2A2A2A]">');
    expect($html)->toContain('class="truncate"');
    expect($html)->toContain('Coloración completa con tratamiento capilar');
});

/**
 * PRF-123/124 (T044): a service fits every free half hour up to the one
 * that still leaves it room before closing (09:00-19:00); it highlights in
 * every free lane (coordinator's decision 2), not just one.
 */
test('selecting a service highlights "Cabe" only where it fits, in every free lane', function () {
    $service = Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 120]);

    $html = $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk()->getContent();

    // 09:00 + 2h = 11:00, well inside 09:00-19:00: fits, both lanes.
    expect($html)->toContain('aria-label="Hueco libre a las 09:00, plaza 1, cabe Balayage"');
    expect($html)->toContain('aria-label="Hueco libre a las 09:00, plaza 2, cabe Balayage"');
    // 17:00 + 2h = 19:00, exactly closing: still fits.
    expect($html)->toContain('aria-label="Hueco libre a las 17:00, plaza 1, cabe Balayage"');
    // 17:30 + 2h = 19:30, past closing: does not fit — stays exactly as a
    // normal hueco libre, no "cabe" suffix, no visible "Cabe" text.
    expect($html)->toContain('aria-label="Hueco libre a las 17:30, plaza 1"');
    expect($html)->not->toContain('aria-label="Hueco libre a las 17:30, plaza 1, cabe Balayage"');

    // Día: 09:00 to 17:00 fits (17 half hours) in both lanes = 34 visible
    // "Cabe" labels; 17:30/18:00/18:30 do not.
    expect(substr_count($html, 'Cabe'))->toBe(34);
});

/**
 * Review finding N2: a full gold border on every fitting half hour made a
 * short service turn the whole grid gold, with nothing standing out.
 * Replaced with a quieter left stripe and a faint background — still two
 * properties, never only a color swap.
 */
test('a fitting hueco gets a subtle left stripe, not a full border', function () {
    $service = Service::factory()->create(['duration_minutes' => 30]);

    $html = $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk()->getContent();

    expect($html)->toContain('border-l-4 border-gold bg-gold/10');
    expect($html)->not->toContain('border-2 border-gold bg-gold/15');
});

/**
 * PRF-121: capacity still governs "cabe", not a particular lane — with
 * capacity reduced to 1 and an existing appointment, a service long enough
 * to overlap it no longer fits there, in any lane.
 */
test('a service that would exceed capacity does not fit, regardless of lane', function () {
    BookingSetting::current()->update(['capacity' => 1]);
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);
    $service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);

    $html = $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk()->getContent();

    // 09:00-10:00 and 10:30-11:30 do not overlap the 10:00-10:30
    // appointment: they fit (capacity 1 is free throughout).
    expect($html)->toContain('aria-label="Hueco libre a las 09:00, plaza 1, cabe Corte"');
    expect($html)->toContain('aria-label="Hueco libre a las 10:30, plaza 1, cabe Corte"');
    // 09:30-10:30 overlaps the real appointment (even though 09:30 itself
    // is still free, DayTimeline renders it as a plain hueco libre), and
    // capacity 1 leaves no room for a second, simultaneous service there.
    expect($html)->toContain('aria-label="Hueco libre a las 09:30, plaza 1"');
    expect($html)->not->toContain('aria-label="Hueco libre a las 09:30, plaza 1, cabe Corte"');
});

/**
 * Review finding N1 (coordinator, agenda-service-filter): a service
 * "fits by capacity" at a start minute even in a lane whose own
 * appointment would collide with it, as long as some other lane stays
 * free the whole time — highlighting that lane too would visually
 * promise a slot it does not really have. Mirrors the exact scenario
 * the coordinator found in local data: an hour-long service at 11:00,
 * a 15-minute appointment at 11:40 in plaza 1.
 */
test('"Cabe" only highlights the lane that is actually free for the whole service, not just by capacity', function () {
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 11:40', 'ends_at' => '2030-01-08 11:55',
        'customer_name' => 'Josep',
    ]);
    $service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);

    $html = $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk()->getContent();

    // Plaza 1 holds the 11:40 appointment: an hour from 11:00 would reach
    // 12:00, colliding with it — not highlighted there, even though the
    // half hour fits by capacity (plaza 2 is free the whole hour).
    expect($html)->toContain('aria-label="Hueco libre a las 11:00, plaza 1"');
    expect($html)->not->toContain('aria-label="Hueco libre a las 11:00, plaza 1, cabe Corte"');
    expect($html)->toContain('aria-label="Hueco libre a las 11:00, plaza 2, cabe Corte"');
});

/**
 * An invalid or inactive "servicio" is ignored, not an error.
 */
test('an invalid or inactive "servicio" is ignored', function (string $servicio) {
    $html = $this->get(route('admin.agenda', ['servicio' => $servicio]))->assertOk()->getContent();

    expect($html)->not->toContain('cabe ');
    expect($html)->not->toContain('Cabe');
})->with(['', 'no-es-un-id', '999999']);

test('an inactive service id in "servicio" is ignored', function () {
    $inactive = Service::factory()->inactive()->create(['duration_minutes' => 30]);

    $html = $this->get(route('admin.agenda', ['servicio' => $inactive->id]))->assertOk()->getContent();

    expect($html)->not->toContain('Cabe');
});

/**
 * PRF-120 (T043): Semana's narrow desktop columns never show the visible
 * "Cabe" text (not enough room), but still carry it in the aria-label —
 * never conveyed by color alone. Proven by an exact count: Día's own
 * mobile grid (compact: false) is the only place "Cabe" text can appear,
 * even though several of the week's open days also have fitting huecos.
 */
test('semana\'s desktop columns carry the aria-label but never the visible "Cabe" text', function () {
    $service = Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 120]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'servicio' => $service->id]))->assertOk()->getContent();

    // Desktop column aria-label, date-prefixed (review finding M2's format).
    expect($html)->toContain(', cabe Balayage');
    expect($html)->toContain('martes 8, Hueco libre a las 09:00, plaza 1, cabe Balayage');
    // Only the mobile strip's selected-day grid (Tuesday, compact: false)
    // can show visible text: 17 half hours x 2 lanes = 34, same as vista
    // Día alone — so the 5 open desktop columns contributed zero.
    expect(substr_count($html, 'Cabe'))->toBe(34);
});

/**
 * Coordinator's decision 1: "servicio" is never folded into "volver" — the
 * two stay separate query parameters everywhere.
 */
test('"servicio" and "volver" stay separate query parameters, never combined', function () {
    $service = Service::factory()->create();

    $html = $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk()->getContent();

    expect($html)->toContain('volver='.urlencode('dia:2030-01-08'));
    expect($html)->toContain('servicio='.$service->id);
    expect($html)->not->toContain(urlencode('dia:2030-01-08:'.$service->id));
});

/**
 * Coordinator's decision 4: the filter survives every kind of navigation —
 * view tabs, Anterior/Siguiente/Hoy, the date form and "Nueva cita" — via
 * one shared query fragment, not re-derived per link.
 */
test('the service filter survives navigating the tabs, Anterior/Siguiente/Hoy and Nueva cita', function () {
    $service = Service::factory()->create();

    $html = $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk()->getContent();

    expect($html)->toContain(e(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-08', 'servicio' => $service->id])));
    expect($html)->toContain(e(route('admin.agenda', ['vista' => 'mes', 'fecha' => '2030-01-08', 'servicio' => $service->id])));
    expect($html)->toContain(e(route('admin.agenda', ['fecha' => '2030-01-07', 'servicio' => $service->id])));
    expect($html)->toContain(e(route('admin.appointments.create', ['fecha' => '2030-01-08', 'volver' => 'dia:2030-01-08', 'servicio' => $service->id])));
});

/**
 * Coordinator's decision 3: the filter is not lost by a detour through
 * Mes, which has no selector of its own.
 */
test('the service filter survives a trip through vista Mes', function () {
    $service = Service::factory()->create();

    $html = $this->get(route('admin.agenda', ['vista' => 'mes', 'servicio' => $service->id]))->assertOk()->getContent();

    expect($html)->toContain(e(route('admin.agenda', ['vista' => 'dia', 'fecha' => '2030-01-08', 'servicio' => $service->id])));
});

/**
 * Sin N+1: picking a service costs exactly one more query (its own active-
 * services list, already needed for the <select>) — never one per
 * candidate half hour or per day, in either Día or Semana.
 */
test('selecting a service keeps vista Día at a fixed number of queries', function () {
    $service = Service::factory()->create(['duration_minutes' => 120]);
    foreach (range(9, 18) as $hour) {
        Appointment::factory()->create(['starts_at' => "2030-01-08 {$hour}:00", 'ends_at' => "2030-01-08 {$hour}:15"]);
    }

    DB::enableQueryLog();
    $this->get(route('admin.agenda', ['servicio' => $service->id]))->assertOk();
    $queries = DB::getQueryLog();

    $countOf = fn (string $table) => collect($queries)->filter(fn ($q) => str_contains($q['query'], "from \"{$table}\""))->count();

    expect($countOf('appointments'))->toBe(1);
    expect($countOf('schedule_blocks'))->toBe(1);
    expect($countOf('opening_hours'))->toBe(1);
    expect($countOf('booking_settings'))->toBe(1);
    expect($countOf('services'))->toBe(1);
});

test('selecting a service keeps vista Semana at a fixed number of queries, not one per day', function () {
    $service = Service::factory()->create(['duration_minutes' => 120]);
    foreach (range(7, 13) as $dayOfMonth) {
        Appointment::factory()->create(['starts_at' => "2030-01-{$dayOfMonth} 10:00", 'ends_at' => "2030-01-{$dayOfMonth} 10:30"]);
    }

    DB::enableQueryLog();
    $this->get(route('admin.agenda', ['vista' => 'semana', 'servicio' => $service->id]))->assertOk();
    $queries = DB::getQueryLog();

    $countOf = fn (string $table) => collect($queries)->filter(fn ($q) => str_contains($q['query'], "from \"{$table}\""))->count();

    expect($countOf('appointments'))->toBe(1);
    expect($countOf('schedule_blocks'))->toBe(1);
    expect($countOf('opening_hours'))->toBe(1);
    expect($countOf('booking_settings'))->toBe(1);
    expect($countOf('services'))->toBe(1);
});

/**
 * T045/T048: "Nueva cita" preselects every service the agenda was filtered
 * by (or whose "Cabe" hueco was tapped) — not only the first.
 */
test('the create form preselects the service from a valid "servicio" query parameter', function () {
    $service = Service::factory()->create(['name' => 'Balayage']);

    $html = $this->get(route('admin.appointments.create', ['servicio' => $service->id]))->assertOk()->getContent();

    expect($html)->toMatch('/name="service_ids\[\]" value="'.$service->id.'"[^>]*checked/');
});

test('the create form preselects every service from a "servicio[]" with several', function () {
    $balayage = Service::factory()->create(['name' => 'Balayage']);
    $cut = Service::factory()->create(['name' => 'Corte']);

    $html = $this->get(route('admin.appointments.create', ['servicio' => [$balayage->id, $cut->id]]))->assertOk()->getContent();

    expect($html)->toMatch('/name="service_ids\[\]" value="'.$balayage->id.'"[^>]*checked/');
    expect($html)->toMatch('/name="service_ids\[\]" value="'.$cut->id.'"[^>]*checked/');
});

test('the create form ignores an invalid or inactive "servicio" query parameter', function (string|int $servicio) {
    $html = $this->get(route('admin.appointments.create', ['servicio' => $servicio]))->assertOk()->getContent();

    expect($html)->not->toContain('checked>');
})->with(['no-es-un-id', '999999']);

test('the create form ignores an inactive service\'s id in "servicio"', function () {
    $inactive = Service::factory()->inactive()->create();

    $html = $this->get(route('admin.appointments.create', ['servicio' => $inactive->id]))->assertOk()->getContent();

    expect($html)->not->toContain('checked>');
});

/**
 * PRF-132 (T048): the "Cabe" highlight with two services selected marks
 * only the half hours where the sum of their durations fits, same rules
 * as a single service (PRF-121).
 */
test('choosing two services in the agenda filter marks "Cabe" only where the sum fits', function () {
    $haircut = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30]);
    $beard = Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 15]);

    $html = $this->get(route('admin.agenda', ['servicio' => [$haircut->id, $beard->id]]))->assertOk()->getContent();

    // 45 minutes: 18:30 + 45 = 19:15, past closing — does not fit; 18:00
    // does (18:00 + 45 = 18:45).
    expect($html)->toContain('aria-label="Hueco libre a las 18:00, plaza 1, cabe Corte + Barba"');
    expect($html)->not->toContain('aria-label="Hueco libre a las 18:30, plaza 1, cabe Corte + Barba"');
});

/**
 * PRF-132: the filter survives navigation with several services selected,
 * the same way it already does with one (PRF-123) — via the array form
 * "servicio[]" once there is more than one.
 */
test('the service filter survives navigating the tabs with several services selected', function () {
    $haircut = Service::factory()->create(['name' => 'Corte']);
    $beard = Service::factory()->create(['name' => 'Barba']);
    // AgendaController::serviciosFromQuery() returns them in the salon's
    // order (ServiceList::sort(): sort_order, then creation order — review
    // finding L2), not the order given in the query.
    $servicioParam = ['servicio' => [$haircut->id, $beard->id]];

    $html = $this->get(route('admin.agenda', ['servicio' => [$beard->id, $haircut->id]]))->assertOk()->getContent();

    expect($html)->toContain(e(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-08', ...$servicioParam])));
    expect($html)->toContain(e(route('admin.appointments.create', ['fecha' => '2030-01-08', 'volver' => 'dia:2030-01-08', ...$servicioParam])));
});

test('selecting two services keeps vista Día at a fixed number of queries', function () {
    $haircut = Service::factory()->create(['duration_minutes' => 30]);
    $beard = Service::factory()->create(['duration_minutes' => 15]);
    foreach (range(9, 18) as $hour) {
        Appointment::factory()->create(['starts_at' => "2030-01-08 {$hour}:00", 'ends_at' => "2030-01-08 {$hour}:15"]);
    }

    DB::enableQueryLog();
    $this->get(route('admin.agenda', ['servicio' => [$haircut->id, $beard->id]]))->assertOk();
    $queries = DB::getQueryLog();

    $countOf = fn (string $table) => collect($queries)->filter(fn ($q) => str_contains($q['query'], "from \"{$table}\""))->count();

    expect($countOf('appointments'))->toBe(1);
    expect($countOf('schedule_blocks'))->toBe(1);
    expect($countOf('opening_hours'))->toBe(1);
    expect($countOf('booking_settings'))->toBe(1);
    expect($countOf('services'))->toBe(1);
});

/**
 * PRF-132 in vista Semana (review T050): the desktop columns' aria-label
 * names every chosen service and marks only where their sum fits.
 */
test('semana with two services marks only where their sum fits, naming both', function () {
    $haircut = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30]);
    $beard = Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 15]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'servicio' => [$haircut->id, $beard->id]]))->assertOk()->getContent();

    // 45 minutes: 18:00 fits (until 18:45), 18:30 does not (19:15).
    expect($html)->toContain('martes 8, Hueco libre a las 18:00, plaza 1, cabe Corte + Barba');
    expect($html)->not->toContain('martes 8, Hueco libre a las 18:30, plaza 1, cabe Corte + Barba');
});
