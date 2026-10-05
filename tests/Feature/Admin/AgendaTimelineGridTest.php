<?php

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
