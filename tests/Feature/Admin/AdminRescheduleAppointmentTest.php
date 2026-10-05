<?php

use App\Enums\AppointmentStatus;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
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
        // The version of the appointment the edit form was opened with.
        'version' => test()->appointment->fresh()->updated_at->getTimestamp(),
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

/**
 * Review finding M1: the edit form carries "volver" through as a hidden
 * field, so moving the appointment returns to the view/date the salon was
 * on, not always vista Día — and an invalid one is dropped, never echoed
 * back as an open-redirect target.
 */
test('the edit form embeds a valid "volver" and drops an invalid one', function () {
    $this->get(route('admin.appointments.edit', ['appointment' => $this->appointment, 'volver' => 'mes:2030-01-08']))
        ->assertOk()
        ->assertSee('<input type="hidden" name="volver" value="mes:2030-01-08">', false);

    $html = $this->get(route('admin.appointments.edit', ['appointment' => $this->appointment, 'volver' => 'https://evil.test']))
        ->assertOk()->getContent();

    expect($html)->not->toContain('name="volver"');
});

test('moving an appointment with "volver" redirects back to that vista/fecha, not vista Día', function () {
    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['volver' => 'mes:2030-01-08']))
        ->assertRedirect(route('admin.agenda', ['vista' => 'mes', 'fecha' => '2030-01-08']));
});

test('moving an appointment with a tampered "volver" falls back to its new date, never an open redirect', function () {
    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['volver' => 'javascript:alert(1)']))
        ->assertRedirect(route('admin.agenda', ['fecha' => '2030-01-09']));
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
        ->assertSee('Esa hora ya no tiene plaza libre')
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

test('moving and changing the email at once sends a single change notice to the new address with the new link', function () {
    Mail::fake();

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['customer_email' => 'rosa.nueva@example.test']));

    $newToken = $this->appointment->fresh()->token;
    Mail::assertSentCount(1);
    Mail::assertSent(AppointmentRescheduledMail::class, fn ($mail) => $mail->hasTo('rosa.nueva@example.test')
        && str_contains($mail->render(), route('cita.show', $newToken)));
});

test('changing only the email sends the appointment and its new link to the new address, and the old link stops working', function () {
    Mail::fake();
    $oldToken = $this->appointment->token;

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => '2030-01-08', 'time' => '10:00', 'customer_email' => 'rosa.nueva@example.test']))
        ->assertSessionHas('status', 'Cita actualizada. Se ha enviado a la clienta un correo con su cita y su nuevo enlace.');

    $appointment = $this->appointment->fresh();
    expect($appointment->token)->not->toBe($oldToken);
    expect($appointment->customer_notified_at)->not->toBeNull();
    Mail::assertSentCount(1);
    Mail::assertSent(AppointmentConfirmedMail::class, function (AppointmentConfirmedMail $mail) use ($appointment) {
        $html = $mail->render();

        return $mail->hasTo('rosa.nueva@example.test')
            && str_contains($html, route('cita.show', $appointment->token))
            && str_contains($html, '10:00');
    });

    auth()->logout();
    $this->get(route('cita.show', $oldToken))->assertNotFound();
    $this->get(route('cita.show', $appointment->token))->assertOk();
});

test('when the email with the new link fails, the panel says the old link no longer works and the retry sends it', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP server unreachable'));

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['customer_email' => 'rosa.nueva@example.test']))
        ->assertSessionHas('warning', 'Cita actualizada, pero no se ha podido enviar a la clienta el correo con su nuevo enlace, y el anterior ya no funciona. El sistema lo reintentará cada 10 minutos; si no le llega, avísala por teléfono.');

    $appointment = $this->appointment->fresh();
    expect($appointment->customer_email)->toBe('rosa.nueva@example.test');
    expect($appointment->customer_notified_at)->toBeNull();

    Mail::swap(new Illuminate\Support\Testing\Fakes\MailFake(new Illuminate\Mail\MailManager(app())));
    $this->travel(10)->minutes();
    $this->artisan('appointments:notify-pending')->assertSuccessful();

    Mail::assertSent(AppointmentConfirmedMail::class, fn ($mail) => $mail->hasTo('rosa.nueva@example.test')
        && str_contains($mail->render(), route('cita.show', $appointment->token)));
});

test('a change notice that is sent counts as the confirmation still pending, so the retry sends nothing more', function () {
    Mail::fake();
    $this->appointment->forceFill(['customer_notified_at' => null])->save();

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload());
    expect($this->appointment->fresh()->customer_notified_at)->not->toBeNull();

    $this->travel(10)->minutes();
    $this->artisan('appointments:notify-pending')->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(AppointmentRescheduledMail::class);
});

test('changing only the service also tells the customer', function () {
    Mail::fake();
    $color = Service::factory()->create(['name' => 'Color', 'duration_minutes' => 60]);

    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['service_id' => $color->id, 'date' => '2030-01-08', 'time' => '10:00']));

    Mail::assertSent(AppointmentRescheduledMail::class, fn ($mail) => str_contains($mail->render(), 'Color'));
});

test('a form opened before someone else changed the appointment saves nothing and shows the current details', function () {
    $staleForm = reschedulePayload(['customer_phone' => '611 222 333', 'date' => '2030-01-08', 'time' => '10:00']);
    $this->travel(1)->minutes();
    $this->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['time' => '12:00']));

    $this->put(route('admin.appointments.update', $this->appointment), $staleForm)
        ->assertRedirect(route('admin.appointments.edit', $this->appointment))
        ->assertSessionHas('warning');

    $appointment = $this->appointment->fresh();
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2030-01-09 12:00');
    expect($appointment->customer_phone)->toBe('600 123 456');

    $this->get(route('admin.appointments.edit', $this->appointment))->assertSee('value="12:00"', false);
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

/**
 * Review finding M1: "not movable" also returns to the view/date the salon
 * was on, from both the GET (edit) and the PUT (update) entry points.
 */
test('"not movable" also returns to the vista/fecha the salon was on', function () {
    $appointment = Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00']);

    $this->get(route('admin.appointments.edit', ['appointment' => $appointment, 'volver' => 'semana:2030-01-08']))
        ->assertRedirect(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-08']));

    $this->put(route('admin.appointments.update', $appointment), reschedulePayload(['force' => '1', 'volver' => 'semana:2030-01-08']))
        ->assertRedirect(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-08']));
});

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

test('the warning says whether the time is outside opening hours, in a one-off closure or full', function (string $date, string $time, string $message) {
    fillTwelveOClock();
    ScheduleBlock::create(['starts_at' => '2030-01-08 15:00', 'ends_at' => '2030-01-08 16:00', 'capacity_reduction' => null]);
    $editUrl = route('admin.appointments.edit', $this->appointment);

    $this->from($editUrl)->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => $date, 'time' => $time]));

    $this->get($editUrl)->assertSee($message);
})->with([
    'after closing time' => ['2030-01-08', '18:30', 'Esa hora cae fuera del horario de apertura'],
    'closed monday' => ['2030-01-14', '10:00', 'Esa hora cae fuera del horario de apertura'],
    'one-off closure' => ['2030-01-08', '15:00', 'Esa hora coincide con un cierre puntual'],
    'no capacity left' => ['2030-01-08', '12:30', 'Esa hora ya no tiene plaza libre'],
]);

test('the warning takes the focus, is linked from the time fields and comes after the normal save button', function () {
    fillTwelveOClock();
    $editUrl = route('admin.appointments.edit', $this->appointment);

    $this->from($editUrl)->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['date' => '2030-01-08', 'time' => '12:30']));
    $html = $this->get($editUrl)->getContent();

    expect($html)->toContain('id="slot-warning" role="alert" tabindex="-1"');
    expect($html)->toContain("document.getElementById('slot-warning').focus();");
    foreach (['service_id', 'date', 'time'] as $field) {
        expect($html)->toMatch('/id="'.$field.'"[^>]*aria-describedby="slot-warning-text"/');
    }
    // Enter in a field submits with the first submit button of the form.
    preg_match('/<form method="POST" action="'.preg_quote(route('admin.appointments.update', $this->appointment), '/').'".*?<\/form>/s', $html, $form);
    preg_match_all('/<button type="submit"[^>]*>([^<]+)<\/button>/', $form[0], $buttons);
    expect($buttons[1])->toBe(['Guardar cambios', 'Guardar igualmente']);
});

test('fields with an error are marked invalid and point to their message', function () {
    $editUrl = route('admin.appointments.edit', $this->appointment);

    $this->from($editUrl)->put(route('admin.appointments.update', $this->appointment), reschedulePayload(['customer_phone' => '12345']));
    $html = $this->get($editUrl)->getContent();

    expect($html)->toMatch('/id="customer_phone"[^>]*aria-invalid="true" aria-describedby="customer_phone-error"/');
    expect($html)->toContain('<p id="customer_phone-error"');
    expect($html)->not->toMatch('/id="customer_name"[^>]*aria-invalid/');
});