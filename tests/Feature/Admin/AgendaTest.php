<?php

use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
});

test('the admin home opens today\'s agenda', function () {
    $this->get(route('admin.home'))->assertRedirect(route('admin.agenda'));
});

test('the agenda lists the day\'s appointments by time with every detail', function () {
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:30', 'service_name' => 'Balayage',
        'customer_name' => 'Laura Pérez', 'customer_phone' => '600 111 222', 'customer_email' => 'laura@example.test',
        'notes' => 'Primera visita', 'source' => AppointmentSource::Web,
    ]);
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 09:30', 'ends_at' => '2030-01-08 10:00', 'service_name' => 'Corte',
        'customer_name' => 'Marc Soler', 'customer_email' => null, 'source' => AppointmentSource::Admin,
    ]);
    Appointment::factory()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00', 'customer_name' => 'Otro Día']);

    $this->get(route('admin.agenda'))
        ->assertOk()
        ->assertSeeInOrder(['09:30', 'Marc Soler', 'Panel', '12:00', '13:30', 'Balayage', 'Laura Pérez', '600 111 222', 'laura@example.test', 'Primera visita', 'Web', 'Confirmada'])
        ->assertDontSee('Otro Día');
});

test('the agenda navigates to other days', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00', 'customer_name' => 'Mañana Cliente']);

    $this->get(route('admin.agenda', ['fecha' => '2030-01-09']))
        ->assertOk()
        ->assertSee('Mañana Cliente')
        ->assertSee('href="'.route('admin.agenda', ['fecha' => '2030-01-08']).'"', false)
        ->assertSee('href="'.route('admin.agenda', ['fecha' => '2030-01-10']).'"', false);
});

test('an invalid date falls back to today', function () {
    $this->get(route('admin.agenda', ['fecha' => 'not-a-date']))->assertOk()->assertSee('08/01/2030');
});

test('a day without appointments says so', function () {
    $this->get(route('admin.agenda'))->assertSee('No hay citas este día.');
});

test('cancelled appointments stay visible as cancelled', function () {
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'customer_name' => 'Cancelada Cliente']);

    $this->get(route('admin.agenda'))->assertSee('Cancelada Cliente')->assertSee('Cancelada');
});

test('the agenda requires an authenticated user', function () {
    auth()->logout();

    $this->get(route('admin.agenda'))->assertRedirect(route('login'));
});
