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
    // Tuesday; default schedule 09:00-19:00, capacity 2.
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    // The create form shows "no hay servicios activos" without this.
    Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30]);
});

/**
 * PRF-114: a free half-hour-or-longer slot, in any lane, is a link to
 * "Nueva cita" with the date, the time and volver already filled in.
 */
test('tapping a free slot in the timeline opens Nueva cita with the date and time prefilled', function () {
    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('href="'.e(route('admin.appointments.create', ['fecha' => '2030-01-08', 'hora' => '09:00', 'volver' => 'dia:2030-01-08'])).'"');
    expect($html)->toContain('aria-label="Hueco libre a las 09:00, plaza 1"');
    expect($html)->toContain('aria-label="Hueco libre a las 09:00, plaza 2"');
});

/**
 * PRF-114: a free slot right after an appointment prefills that exact
 * start time, not a rounded one.
 */
test('a free slot right after an appointment prefills its own exact start time', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 09:00', 'ends_at' => '2030-01-08 09:35']);

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Hueco libre a las 09:35, plaza 1"');
});

/**
 * PRF-114: a free gap shorter than 30 minutes is not offered as its own
 * tap target (DayTimeline already marks it not tappable; the view must
 * not turn it into a link).
 */
test('a free gap shorter than 30 minutes is not a link', function () {
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 09:00', 'ends_at' => '2030-01-08 09:30']);
    Appointment::factory()->create(['starts_at' => '2030-01-08 09:45', 'ends_at' => '2030-01-08 10:15']);

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->not->toContain('Hueco libre a las 09:30');
});

/**
 * The create form prefills the time field from a valid "hora", and
 * ignores a malformed one instead of crashing.
 */
test('the create form prefills the time field from a valid "hora" query parameter', function () {
    $this->get(route('admin.appointments.create', ['fecha' => '2030-01-08', 'hora' => '10:15']))
        ->assertOk()
        ->assertSee('id="time" name="time" type="time" step="300" required value="10:15"', false);
});

test('the create form ignores a malformed "hora" instead of crashing', function (string $hora) {
    $this->get(route('admin.appointments.create', ['fecha' => '2030-01-08', 'hora' => $hora]))
        ->assertOk()
        ->assertSee('id="time" name="time" type="time" step="300" required value=""', false);
})->with(['99:99', 'not-a-time', '9:00', '10:00:00']);

/**
 * PRF-116 / PRF-117: today, with "ahora" inside opening hours, shows the
 * "now" line and the scroll-to-it script.
 */
test('today shows the "ahora" line and the scroll script, at the right offset', function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-08 11:15'));

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    // 11:15 is 135 minutes after the 09:00 grid start, at 88px/hour.
    $expectedTop = (int) round(135 * 88 / 60);
    expect($html)->toContain('style="top: '.$expectedTop.'px"');
    expect($html)->toContain('>Ahora<');
    expect($html)->toContain('id="timeline-scroll"');
    expect($html)->toContain('container.scrollTop = Math.max(0, '.$expectedTop.' - 100);');
});

/**
 * No "ahora" line (or script) when viewing a day that is not today, or
 * when "ahora" falls outside the grid (before opening or after closing).
 */
test('no "ahora" line or scroll script when viewing another day', function () {
    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-09']))->assertOk()->getContent();

    expect($html)->not->toContain('>Ahora<');
    expect($html)->not->toContain('scrollTop');
});

test('no "ahora" line when "ahora" falls outside today\'s grid range', function () {
    // beforeEach already travels to 2030-01-08 08:00, before the 09:00 opening.
    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->not->toContain('>Ahora<');
    expect($html)->not->toContain('scrollTop');
});

/**
 * The timeline grid is built entirely from data already loaded for the
 * day (appointments, blocks, opening hours, capacity): a fixed number of
 * queries, never one per appointment, block or lane.
 */
test('vista Día loads the timeline grid with a fixed number of queries, not one per appointment', function () {
    foreach (range(9, 18) as $hour) {
        Appointment::factory()->create(['starts_at' => "2030-01-08 {$hour}:00", 'ends_at' => "2030-01-08 {$hour}:15"]);
    }

    DB::enableQueryLog();
    $this->get(route('admin.agenda'))->assertOk();
    $queries = DB::getQueryLog();

    $appointmentQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "appointments"'))->count();
    $blockQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "schedule_blocks"'))->count();
    $openingHourQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "opening_hours"'))->count();
    $bookingSettingQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "booking_settings"'))->count();

    expect($appointmentQueries)->toBe(1);
    expect($blockQueries)->toBe(1);
    expect($openingHourQueries)->toBe(1);
    expect($bookingSettingQueries)->toBe(1);
});
