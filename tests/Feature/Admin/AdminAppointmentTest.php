<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    $this->service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);
});

/**
 * @return array<string, mixed>
 */
function adminAppointmentPayload(array $overrides = []): array
{
    return array_merge([
        'service_id' => test()->service->id,
        'date' => '2030-01-08',
        'time' => '10:05',
        'customer_name' => 'Rosa Vidal',
        'customer_phone' => '+34 600 123 456',
        'customer_email' => '',
        'notes' => 'Llamó por teléfono',
    ], $overrides);
}

test('the create form offers only active services', function () {
    Service::factory()->inactive()->create(['name' => 'Servicio retirado']);

    $this->get(route('admin.appointments.create', ['fecha' => '2030-01-08']))
        ->assertOk()
        ->assertSee('Corte')
        ->assertDontSee('Servicio retirado')
        ->assertSee('value="2030-01-08"', false);
});

test('a salon user can record a phone booking without email at any 5-minute time', function () {
    // Admin bookings ignore the public slot interval and minimum notice.
    BookingSetting::current()->update(['min_notice_minutes' => 600]);

    $this->post(route('admin.appointments.store'), adminAppointmentPayload())
        ->assertRedirect(route('admin.agenda', ['fecha' => '2030-01-08']));

    $appointment = Appointment::sole();
    expect($appointment->source)->toBe(AppointmentSource::Admin);
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
    expect($appointment->customer_email)->toBeNull();
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2030-01-08 10:05');
    expect($appointment->ends_at->format('H:i'))->toBe('11:05');
});

test('an unavailable time is refused with a message', function () {
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $this->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), adminAppointmentPayload(['time' => '10:30']))
        ->assertRedirect(route('admin.appointments.create'))
        ->assertSessionHasErrors(['time' => 'Esa hora no está disponible para este servicio.']);

    expect(Appointment::count())->toBe(2);
});

test('times outside opening hours or in the past are refused', function (string $date, string $time) {
    $this->post(route('admin.appointments.store'), adminAppointmentPayload(['date' => $date, 'time' => $time]))
        ->assertSessionHasErrors('time');

    expect(Appointment::count())->toBe(0);
})->with([
    'ends after closing' => ['2030-01-08', '18:30'],
    'closed monday' => ['2030-01-14', '10:00'],
    'already past' => ['2030-01-08', '07:00'],
]);

test('invalid admin booking data is rejected', function (array $overrides, string $field) {
    $this->post(route('admin.appointments.store'), adminAppointmentPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Appointment::count())->toBe(0);
})->with([
    'minute not a multiple of 5' => [['time' => '10:03'], 'time'],
    'missing name' => [['customer_name' => ''], 'customer_name'],
    'short phone' => [['customer_phone' => '12345'], 'customer_phone'],
    'invalid email' => [['customer_email' => 'nope'], 'customer_email'],
    'notes over 500' => [['notes' => str_repeat('a', 501)], 'notes'],
    'unknown service' => [['service_id' => 999], 'service_id'],
]);

test('an inactive service cannot be booked from the panel', function () {
    $inactive = Service::factory()->inactive()->create();

    $this->post(route('admin.appointments.store'), adminAppointmentPayload(['service_id' => $inactive->id]))
        ->assertSessionHasErrors('service_id');

    expect(Appointment::count())->toBe(0);
});

test('a salon user can cancel a confirmed appointment at any time before it', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 08:30', 'ends_at' => '2030-01-08 09:30']);

    $this->post(route('admin.appointments.cancel', $appointment))
        ->assertRedirect(route('admin.agenda', ['fecha' => '2030-01-08']));

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    expect(Appointment::count())->toBe(1);
});

test('appointments cannot be deleted from the panel', function () {
    expect(collect(Route::getRoutes())->contains(
        fn ($route) => in_array('DELETE', $route->methods()) && str_contains($route->uri(), 'citas')
    ))->toBeFalse();
});
