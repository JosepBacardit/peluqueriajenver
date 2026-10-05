<?php

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    // Tuesday; its week runs Monday 2030-01-07 to Sunday 2030-01-13
    // (OpeningHour's default schedule is open Tuesday-Saturday).
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
});

/**
 * PRF-100 / PRF-101: a week always runs Monday to Sunday (Europe/Madrid),
 * never the ISO week of a different locale's convention.
 */
test('the week view groups appointments by day from Monday to Sunday', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 09:30', 'ends_at' => '2030-01-08 10:00', 'customer_name' => 'Martes Cliente']);
    Appointment::factory()->create(['starts_at' => '2030-01-10 12:00', 'ends_at' => '2030-01-10 13:00', 'customer_name' => 'Jueves Cliente']);
    // Outside the week (the following Monday): must not appear.
    Appointment::factory()->create(['starts_at' => '2030-01-14 10:00', 'ends_at' => '2030-01-14 11:00', 'customer_name' => 'Otra Semana']);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('Martes Cliente');
    expect($html)->toContain('Jueves Cliente');
    expect($html)->not->toContain('Otra Semana');
});

/**
 * PRF-101: the mobile day strip shows the selected day's full agenda below
 * it, reusing the Día view's content, including its action buttons.
 */
test('the week view shows the selected day\'s full agenda (Llamar, WhatsApp, Editar, Cancelar) in the mobile strip', function () {
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 09:30', 'ends_at' => '2030-01-08 10:00',
        'customer_name' => 'Marta Ruiz', 'customer_phone' => '633 912 050',
    ]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Llamar a Marta Ruiz"');
    expect($html)->toContain('aria-label="Abrir WhatsApp con Marta Ruiz"');
    expect($html)->toContain(route('admin.appointments.edit', Appointment::first()));
    expect($html)->toContain('Cancelar cita');
});

/**
 * PRF-105: a day with no opening-hours range is marked "Cerrado" with text.
 */
test('the week view marks days without opening hours as closed', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    // Monday 2030-01-07 and Sunday 2030-01-13 have no opening_hours rows.
    expect(substr_count($html, 'Cerrado'))->toBeGreaterThanOrEqual(2);
});

/**
 * PRF-100: the desktop grid lists each day's appointments sorted by time.
 */
test('the week view desktop grid lists each day with its weekday name', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-09 11:00', 'ends_at' => '2030-01-09 11:30', 'service_name' => 'Peinado']);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('miércoles');
    expect($html)->toContain('11:00 Peinado');
    expect($html)->toContain('role="grid"');
});

/**
 * PRF-104: navigating moves a whole week, not a single day.
 */
test('the week view navigates to the previous and next week, keeping its appointments grouped', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-15 10:00', 'ends_at' => '2030-01-15 11:00', 'customer_name' => 'Semana Siguiente']);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-14']))->assertOk()->getContent();

    expect($html)->toContain('Semana Siguiente');
});

/**
 * PRF-100 / PRF-101: one query for the week's appointments and one for its
 * blocks, grouped in PHP — never one query per day of the week.
 */
test('the week view loads its appointments and blocks with one query each, not one per day', function () {
    foreach (range(7, 12) as $dayOfMonth) {
        Appointment::factory()->create([
            'starts_at' => "2030-01-{$dayOfMonth} 10:00", 'ends_at' => "2030-01-{$dayOfMonth} 10:30",
        ]);
    }
    ScheduleBlock::create(['starts_at' => '2030-01-09 00:00', 'ends_at' => '2030-01-09 23:59', 'capacity_reduction' => null]);

    DB::enableQueryLog();
    $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk();
    $queries = DB::getQueryLog();

    $appointmentQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "appointments"'))->count();
    $blockQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "schedule_blocks"'))->count();

    expect($appointmentQueries)->toBe(1);
    expect($blockQueries)->toBe(1);
});

/**
 * PRF-101: each day in the mobile strip has a 44px touch target.
 */
test('the week view day strip meets the 44px touch target', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect(substr_count($html, 'min-h-11'))->toBeGreaterThanOrEqual(7);
});

/**
 * The day strip's count only counts confirmed appointments, the same rule
 * as the month view's occupancy — a cancelled appointment still shows in
 * that day's agenda below, but it must not read as "1 cita" on the pill.
 */
test('the week view day strip only counts confirmed appointments, not cancelled ones', function () {
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 10:30']);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('miércoles 09/01, sin citas');
    expect($html)->not->toContain('miércoles 09/01, 1 cita');
});

/**
 * Review finding H1: a full closure (ScheduleBlock with no capacity
 * reduction) inside the shown week must mark that day "Cerrado", exactly
 * like vista Mes already does, both in the mobile strip and the desktop
 * grid — even when it still has a confirmed appointment that survives the
 * closure (PRF-022).
 */
test('the week view marks a day covered by a full closure as closed, even with a surviving appointment', function () {
    // Within opening hours (09:00-19:00): a time before/after it would
    // fall outside the timeline grid entirely, same as it could never be
    // booked for real.
    Appointment::factory()->create(['starts_at' => '2030-01-09 09:00', 'ends_at' => '2030-01-09 09:30', 'customer_name' => 'Antes Del Cierre']);
    ScheduleBlock::create(['starts_at' => '2030-01-09 00:00', 'ends_at' => '2030-01-10 00:00', 'capacity_reduction' => null]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('miércoles 09/01, cerrado');
    // The desktop grid shows "Cerrado" and still lists the surviving
    // appointment, instead of hiding it.
    expect(substr_count($html, 'Cerrado'))->toBeGreaterThanOrEqual(1);
    expect($html)->toContain('Antes Del Cierre');
});

/**
 * Review finding H1: a partial closure (capacity reduction) does not close
 * the day, but is flagged the same way vista Día already does with its
 * amber banner — now, in the hourly grid, as a "Cierre" shaded segment in
 * the lane(s) above the reduced capacity (T039).
 */
test('the week view flags a day with a partial closure, without marking it closed', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 12:00', 'capacity_reduction' => 1]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('miércoles 09/01, capacidad reducida');
    expect($html)->not->toContain('miércoles 09/01, cerrado');
    // The desktop grid's lane 2 (capacity 2, reduced to 1) shades "Cierre"
    // for that tramo, same text DayTimelineBuildTest already verifies for
    // the underlying algorithm.
    expect($html)->toContain('Cierre');
});

/**
 * T039: Semana's desktop grid now reuses the same timeline-column
 * rendering as Día (T036/T037): an appointment is a block linking to its
 * card below (#cita-{id}), not straight to editing, with a readable size
 * (no longer forced to a fixed min-h-11, since its height already reflects
 * its real duration) and a full aria-label.
 */
test('the week view desktop grid links each appointment block to its card below', function () {
    $appointment = Appointment::factory()->create([
        'starts_at' => '2030-01-09 11:00', 'ends_at' => '2030-01-09 11:30',
        'service_name' => 'Peinado', 'customer_name' => 'Marta Ruiz',
    ]);

    // Select that same Wednesday so its card (which only the selected
    // day's mobile section renders) is present to link to.
    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-09']))->assertOk()->getContent();

    expect($html)->toContain('href="#cita-'.$appointment->id.'"');
    // Review finding M2: Semana's columns prefix every aria-label with
    // their own day; review finding N6 (no visible lane header fits a
    // narrow column): the plaza is folded into the aria-label instead.
    expect($html)->toContain('aria-label="miércoles 9, 11:00 Peinado, Marta Ruiz, plaza 1"');
    expect($html)->toContain('id="cita-'.$appointment->id.'"'); // the card it jumps to
});

/**
 * PRF-110: a cancelled appointment never occupies a lane in the grid (it
 * still shows, dimmed, in the card list below — unchanged, review finding
 * N3's "(Cancelada)" label is on that card, not on a grid block that no
 * longer exists for it).
 */
test('the week view desktop grid does not render a block for a cancelled appointment', function () {
    $appointment = Appointment::factory()->cancelled()->create([
        'starts_at' => '2030-01-09 11:00', 'ends_at' => '2030-01-09 11:30',
        'service_name' => 'Peinado', 'customer_name' => 'Marta Ruiz',
    ]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-09']))->assertOk()->getContent();

    expect($html)->not->toContain('href="#cita-'.$appointment->id.'"');
    // It still appears in the mobile/selected day's card list, dimmed.
    expect($html)->toContain('id="cita-'.$appointment->id.'"');
});

/**
 * T039: each of the 7 desktop columns must use its own date for a free
 * slot's "Nueva cita" link, not the mobile strip's globally selected day
 * (caught before shipping: the column partial was reusing a single
 * inherited $day/$volver for every column).
 */
test('a free slot in a desktop column other than the selected day creates on that column\'s own date', function () {
    // Selected day is Tuesday 2030-01-08 (the default, no "fecha"); check
    // a free slot in Thursday 2030-01-10's own column.
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('href="'.e(route('admin.appointments.create', ['fecha' => '2030-01-10', 'hora' => '09:00', 'volver' => 'semana:2030-01-10'])).'"');
    expect($html)->not->toContain('href="'.e(route('admin.appointments.create', ['fecha' => '2030-01-08', 'hora' => '09:00', 'volver' => 'semana:2030-01-10'])).'"');
});

/**
 * T039: the hour axis is shared once across the 7 desktop columns (plus
 * once more for the mobile section's own single-day grid) — never
 * repeated per column.
 */
test('the hour axis is shared once across the desktop week grid, not repeated per column', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    // Once for the mobile selected-day grid's own axis, once for that same
    // grid's "Plaza 1 · Plaza 2" header spacer (review finding N6), once
    // for the desktop header row's corner spacer (same width, so the day
    // headers line up with the columns below) and once for the desktop
    // body's actual axis: never once per column (7) on top of these.
    expect(substr_count($html, 'w-11 shrink-0'))->toBe(4);
});

/**
 * PRF-116, T039: the "ahora" line only appears in today's own column.
 */
test('the "ahora" line only appears in today\'s column of the desktop week grid', function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-08 11:15')); // Tuesday, within hours

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    // Once in the mobile grid (today's own _timeline.blade.php) and once
    // more in the desktop week grid's Tuesday column: never once per
    // weekday column (7) plus the mobile one.
    expect(substr_count($html, '>Ahora<'))->toBe(2);
});
