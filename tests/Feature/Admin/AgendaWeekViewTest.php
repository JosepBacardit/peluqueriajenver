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
    Appointment::factory()->create(['starts_at' => '2030-01-09 08:00', 'ends_at' => '2030-01-09 08:30', 'customer_name' => 'Antes Del Cierre']);
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
 * amber banner.
 */
test('the week view flags a day with a partial closure, without marking it closed', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 12:00', 'capacity_reduction' => 1]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('miércoles 09/01, capacidad reducida');
    expect($html)->toContain('Capacidad reducida');
    expect($html)->not->toContain('miércoles 09/01, cerrado');
});

/**
 * Review finding N3: each appointment in the desktop grid is a link (to
 * editing it, or to its day when it cannot be edited), with a readable
 * size, a 44px touch target and a full aria-label.
 */
test('the week view desktop grid links each appointment to editing it, with an aria-label and a 44px target', function () {
    $appointment = Appointment::factory()->create([
        'starts_at' => '2030-01-09 11:00', 'ends_at' => '2030-01-09 11:30',
        'service_name' => 'Peinado', 'customer_name' => 'Marta Ruiz',
    ]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('href="'.e(route('admin.appointments.edit', ['appointment' => $appointment, 'volver' => 'semana:2030-01-08'])).'"');
    expect($html)->toContain('aria-label="11:00 Peinado, Marta Ruiz"');
    expect($html)->toContain('min-h-11');
    expect($html)->toContain('text-sm');
});

/**
 * Review finding N3: a cancelled appointment in the desktop grid links to
 * its day (editing is not offered) and is marked "(Cancelada)" in text, not
 * only with the strike-through style.
 */
test('the week view desktop grid marks a cancelled appointment with text, not only strike-through', function () {
    Appointment::factory()->cancelled()->create([
        'starts_at' => '2030-01-09 11:00', 'ends_at' => '2030-01-09 11:30',
        'service_name' => 'Peinado', 'customer_name' => 'Marta Ruiz',
    ]);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('(Cancelada)');
    expect($html)->toContain('aria-label="11:00 Peinado, Marta Ruiz, cancelada"');
    expect($html)->toContain('href="'.e(route('admin.agenda', ['vista' => 'dia', 'fecha' => '2030-01-09'])).'"');
});
