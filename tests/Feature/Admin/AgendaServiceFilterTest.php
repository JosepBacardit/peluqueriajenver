<?php

use App\Models\Appointment;
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
 * PRF-121: capacity still governs "cabe", not a particular lane — with
 * capacity reduced to 1 and an existing appointment, a service long enough
 * to overlap it no longer fits there, in any lane.
 */
test('a service that would exceed capacity does not fit, regardless of lane', function () {
    \App\Models\BookingSetting::current()->update(['capacity' => 1]);
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
 * T045: "Nueva cita" preselects the service the agenda was filtered by (or
 * whose "Cabe" hueco was tapped).
 */
test('the create form preselects the service from a valid "servicio" query parameter', function () {
    $service = Service::factory()->create(['name' => 'Balayage']);

    $html = $this->get(route('admin.appointments.create', ['servicio' => $service->id]))->assertOk()->getContent();

    expect($html)->toContain('<option value="'.$service->id.'" selected>Balayage');
});

test('the create form ignores an invalid or inactive "servicio" query parameter', function (string|int $servicio) {
    $html = $this->get(route('admin.appointments.create', ['servicio' => $servicio]))->assertOk()->getContent();

    expect($html)->not->toContain('selected>');
})->with(['no-es-un-id', '999999']);

test('the create form ignores an inactive service\'s id in "servicio"', function () {
    $inactive = Service::factory()->inactive()->create();

    $html = $this->get(route('admin.appointments.create', ['servicio' => $inactive->id]))->assertOk()->getContent();

    expect($html)->not->toContain('selected>');
});
