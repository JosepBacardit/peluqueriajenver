<?php

use App\Enums\AppointmentStatus;
use App\Mail\AppointmentRescheduledMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
 * "Now" is Tuesday 2030-01-08 08:00; the salon opens 09:00-19:00 by the
 * default schedule, with the default capacity of 2.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    $this->service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);
    $this->appointment = Appointment::factory()->create([
        'service_id' => $this->service->id,
        'starts_at' => '2030-01-08 10:00',
        'ends_at' => '2030-01-08 11:00',
        'customer_name' => 'Rosa Vidal',
        'customer_phone' => '600 123 456',
        'customer_email' => 'rosa@example.test',
        'notes' => 'Pelo rizado',
        'customer_notified_at' => now(),
        'salon_notified_at' => now(),
    ]);
});

/**
 * @return array<string, mixed>
 */
function reschedulePayload(array $overrides = []): array
{
    return array_merge([
        'service_id' => test()->service->id,
        'date' => '2030-01-09',
        'time' => '16:00',
        'customer_name' => 'Rosa Vidal',
        'customer_phone' => '600 123 456',
        'customer_email' => 'rosa@example.test',
        'notes' => 'Pelo rizado',
    ], $overrides);
}

function fillTwelveOClock(): void
{
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:00']);
}

test('the edit form shows the appointment\'s current details', function () {
    $this->get(route('admin.appointments.edit', $this->appointment))
        ->assertOk()
        ->assertSee('value="2030-01-08"', false)
        ->assertSee('value="10:00"', false)
        ->assertSee('value="Rosa Vidal"', false)
        ->assertSee('value="600 123 456"', false)
        ->assertSee('value="rosa@example.test"', false)
        ->assertSee('Pelo rizado')
        ->assertSee('<option value="'.$this->service->id.'" selected', false);
});

test('moving to a free time saves it and takes the salon to the new day', function () {
    $this->from(route('admin.appointments.edit', $this->appointment))
        ->put(route('admin.appointments.update', $this->appointment), reschedulePayload())
        ->assertRedirect(route('admin.agenda', ['fecha' => '2030-01-09']))
        ->assertSessionHas('status');

    $appointment = Appointment::sole();
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2030-01-09 16:00');
    expect($appointment->ends_at->format('Y-m-d H:i'))->toBe('2030-01-09 17:00');
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
});

test('a full time is not saved without confirmation and the form explains why', function () {
    fillTwelveOClock();
    $editUrl = route('admin.appointments.edit', $this->appointment);

    $this->from($editUrl)
        ->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => '2030-01-08', 'time' => '12:30']))
        ->assertRedirect($editUrl);

    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('10:00');

    $this->get($editUrl)
        ->assertSee('role="alert"', false)
        ->assertSee('fuera del horario de apertura o no tiene plaza libre')
        ->assertSee('Guardar igualmente')
        // What was typed is kept, not the saved values.
        ->assertSee('value="12:30"', false);
});

test('confirming "save anyway" saves a full or closed time', function (string $date, string $time) {
    fillTwelveOClock();
    $editUrl = route('admin.appointments.edit', $this->appointment);
    $payload = reschedulePayload(['date' => $date, 'time' => $time]);

    $this->from($editUrl)->put(route('admin.appointments.update', $this->appointment), $payload);
    preg_match('/name="force" value="([^"]+)"/', $this->get($editUrl)->getContent(), $button);

    $this->from($editUrl)
        ->put(route('admin.appointments.update', $this->appointment), $payload + ['force' => html_entity_decode($button[1])])
        ->assertRedirect(route('admin.agenda', ['fecha' => $date]));

    expect($this->appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe("{$date} {$time}");
})->with([
    'no capacity left' => ['2030-01-08', '12:30'],
    'after closing time' => ['2030-01-08', '18:30'],
    'closed monday' => ['2030-01-14', '10:00'],
]);

test('a confirmation given for another time does not save a different full time', function () {
    fillTwelveOClock();
    $editUrl = route('admin.appointments.edit', $this->appointment);

    $this->from($editUrl)->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => '2030-01-08', 'time' => '12:30']));
    preg_match('/name="force" value="([^"]+)"/', $this->get($editUrl)->getContent(), $button);

    // The salon changes the time after the warning and presses the button.
    $this->from($editUrl)
        ->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => '2030-01-08', 'time' => '12:15', 'force' => html_entity_decode($button[1])]))
        ->assertRedirect($editUrl);

    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('10:00');
});

test('a time already past is refused, with no option to save anyway', function () {
    $editUrl = route('admin.appointments.edit', $this->appointment);

    $this->from($editUrl)
        ->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => '2030-01-08', 'time' => '07:00', 'force' => '1']))
        ->assertRedirect($editUrl)
        ->assertSessionHasErrors(['time' => 'Esa hora ya ha pasado.']);

    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('10:00');
    $this->get($editUrl)->assertDontSee('Guardar igualmente');
});

test('the customer details are changed together with the time', function () {
    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload([
        'customer_name' => 'Rosa Vidal Puig',
        'customer_phone' => '611 222 333',
        'customer_email' => 'rosa.nueva@example.test',
        'notes' => '',
    ]))->assertSessionHasNoErrors();

    $appointment = $this->appointment->fresh();
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2030-01-09 16:00');
    expect($appointment->only(['customer_name', 'customer_phone', 'customer_email', 'notes']))
        ->toBe(['customer_name' => 'Rosa Vidal Puig', 'customer_phone' => '611 222 333', 'customer_email' => 'rosa.nueva@example.test', 'notes' => null]);
});

test('invalid details are rejected with the same rules as a new panel appointment and nothing changes', function (array $overrides, string $field) {
    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload($overrides))
        ->assertSessionHasErrors($field);

    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('10:00');
    expect($this->appointment->fresh()->customer_phone)->toBe('600 123 456');
})->with([
    'minute not a multiple of 5' => [['time' => '10:03'], 'time'],
    'missing name' => [['customer_name' => ''], 'customer_name'],
    'short phone' => [['customer_phone' => '12345'], 'customer_phone'],
    'invalid email' => [['customer_email' => 'nope'], 'customer_email'],
    'notes over 500' => [['notes' => str_repeat('a', 501)], 'notes'],
    'unknown service' => [['service_id' => 999], 'service_id'],
]);

test('another inactive service cannot be chosen, but the appointment can keep its own', function () {
    $retired = Service::factory()->inactive()->create();

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['service_id' => $retired->id]))
        ->assertSessionHasErrors('service_id');

    $this->service->update(['is_active' => false]);

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload())
        ->assertSessionHasNoErrors();
    expect($this->appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2030-01-09 16:00');
});

test('moving an appointment emails the customer the new time and the same personal link', function () {
    Mail::fake();
    $token = $this->appointment->token;

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload());

    Mail::assertSent(AppointmentRescheduledMail::class, 1);
    Mail::assertSent(AppointmentRescheduledMail::class, function (AppointmentRescheduledMail $mail) use ($token) {
        $html = $mail->render();

        return $mail->hasTo('rosa@example.test')
            && str_contains($mail->envelope()->subject, '16:00')
            && str_contains($html, route('cita.show', $token))
            && str_contains($html, 'Miércoles 9 de enero de 2030')
            && str_contains($html, '16:00')
            && ! str_contains($html, '10:00');
    });
});

test('the notice goes to the email saved with the move', function () {
    Mail::fake();

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['customer_email' => 'rosa.nueva@example.test']));

    Mail::assertSent(AppointmentRescheduledMail::class, fn ($mail) => $mail->hasTo('rosa.nueva@example.test'));
});

test('no email is sent when the customer has none or when neither the time nor the service changes', function (array $overrides) {
    Mail::fake();

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload($overrides))
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();
})->with([
    'no email' => [['customer_email' => '']],
    'only the phone changes' => [['date' => '2030-01-08', 'time' => '10:00', 'customer_phone' => '611 222 333']],
]);

test('a failing mail server never undoes the move, the panel says so and nothing retries it', function () {
    // Exactly one attempt: the retry command below must not try again.
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP server unreachable'));
    $this->travelTo(now()->addMinutes(10));

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload())
        ->assertRedirect(route('admin.agenda', ['fecha' => '2030-01-09']))
        ->assertSessionHas('warning', 'Cita actualizada, pero no se ha podido enviar el correo a la clienta con el cambio. Avísala por teléfono.');

    expect($this->appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2030-01-09 16:00');

    $this->artisan('appointments:notify-pending')->assertSuccessful();
});

test('a cancelled or already started appointment cannot be edited or moved', function (string $state) {
    $appointment = match ($state) {
        'cancelled' => Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00']),
        'started' => Appointment::factory()->create(['starts_at' => '2030-01-08 07:30', 'ends_at' => '2030-01-08 08:30']),
    };
    $before = $appointment->fresh()->toArray();

    $this->get(route('admin.appointments.edit', $appointment))
        ->assertRedirect(route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()]))
        ->assertSessionHas('warning');

    $this->put(route('admin.appointments.update', $appointment), reschedulePayload(['force' => '1']))
        ->assertRedirect(route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()]))
        ->assertSessionHas('warning');

    expect($appointment->fresh()->toArray())->toBe($before);
})->with(['cancelled', 'started']);

test('moving leaves no generated text in the appointment', function () {
    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload());

    $appointment = $this->appointment->fresh();
    expect($appointment->notes)->toBe('Pelo rizado');
    expect($appointment->customer_name)->toBe('Rosa Vidal');
});

test('creating a new appointment from the panel still cannot take a full time', function () {
    fillTwelveOClock();

    $this->post(route('admin.appointments.store'), reschedulePayload(['date' => '2030-01-08', 'time' => '12:30', 'force' => '1']))
        ->assertSessionHasErrors('time');

    expect(Appointment::count())->toBe(3);
});
