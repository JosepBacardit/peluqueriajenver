<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    BookingSetting::current()->update(['cancellation_limit_hours' => 24]);
});

test('the personal link shows the appointment details', function () {
    $appointment = Appointment::factory()->create([
        'services_label' => 'Balayage', 'customer_name' => 'Núria',
        'starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 14:00',
    ]);

    expect(strlen($appointment->token))->toBeGreaterThanOrEqual(40);

    $this->get(route('cita.show', $appointment->token))
        ->assertOk()
        ->assertSee('Balayage')
        ->assertSee('Núria')
        ->assertSee('jueves 10 de enero de 2030')
        ->assertSee('11:00')
        ->assertSee('Confirmada')
        ->assertSee('noindex', false);
});

test('an unknown link is not found and reveals nothing', function () {
    Appointment::factory()->create(['customer_name' => 'Núria']);

    $this->get(route('cita.show', str_repeat('x', 48)))->assertNotFound()->assertDontSee('Núria');
    $this->post(route('cita.cancel', str_repeat('x', 48)), ['confirm' => '1'])->assertNotFound();
});

test('the customer can cancel within the cancellation limit and the time is freed', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 12:00']);

    $this->get(route('cita.show', $appointment->token))->assertSee('Cancelar mi cita');

    $this->post(route('cita.cancel', $appointment->token), ['confirm' => '1'])
        ->assertRedirect(route('cita.show', $appointment->token));

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    $this->get(route('cita.show', $appointment->token))->assertSee('Tu cita se ha cancelado.');
});

/**
 * PRF-098: easy to tap on a phone, the main action on this page for a
 * customer who can still cancel.
 */
test('the cancel button spans the full width on a cancellable appointment', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 12:00']);

    $this->get(route('cita.show', $appointment->token))
        ->assertSee('<button type="submit" class="btn-outline w-full">', false);
});

/**
 * Review finding N4 (.ai/reviews/mobile-admin-ux.md, coordinator): the
 * confirmation checkbox measured 20px; the whole row is now tappable
 * (min-h-11) with a bigger checkbox.
 */
test('the cancel-confirmation row meets the 44px touch target, with a bigger checkbox', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 12:00']);

    $html = $this->get(route('cita.show', $appointment->token))->assertOk()->getContent();

    expect($html)->toContain('class="flex items-center gap-3 min-h-11 py-2 text-sm text-gray-200 cursor-pointer"');
    expect($html)->toContain('<input type="checkbox" name="confirm" value="1" required class="w-5 h-5 shrink-0 accent-gold">');
});

test('cancelling requires ticking the confirmation', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 12:00']);

    $this->post(route('cita.cancel', $appointment->token), [])->assertSessionHasErrors('confirm');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

test('past the cancellation limit the page refuses to cancel online', function (string $startsAt) {
    $appointment = Appointment::factory()->create(['starts_at' => $startsAt, 'ends_at' => CarbonImmutable::parse($startsAt)->addHour()]);

    $this->get(route('cita.show', $appointment->token))
        ->assertSee('Ya no se puede cancelar online. Llámanos al 633 912 050.')
        ->assertDontSee('Cancelar mi cita');

    $this->post(route('cita.cancel', $appointment->token), ['confirm' => '1'])
        ->assertSessionHasErrors(['confirm' => 'Ya no se puede cancelar online. Llámanos al 633 912 050.']);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
})->with([
    'in two hours' => '2030-01-07 12:00',
    'in 23 hours 59 minutes' => '2030-01-08 09:59',
    'already started' => '2030-01-07 09:30',
]);

test('exactly at the limit the customer can still cancel', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $this->post(route('cita.cancel', $appointment->token), ['confirm' => '1']);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

test('a cancelled appointment cannot be cancelled again', function () {
    $appointment = Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 12:00']);
    $cancelledAt = $appointment->cancelled_at;

    $this->get(route('cita.show', $appointment->token))
        ->assertSee('Esta cita está cancelada.')
        ->assertDontSee('Cancelar mi cita');

    $this->post(route('cita.cancel', $appointment->token), ['confirm' => '1'])
        ->assertRedirect(route('cita.show', $appointment->token));

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    expect($appointment->fresh()->cancelled_at->equalTo($cancelledAt))->toBeTrue();
});

test('appointment pages are not listed in the sitemap', function () {
    $appointment = Appointment::factory()->create();

    expect($this->get('/sitemap.xml')->getContent())->not->toContain('/cita/')->not->toContain($appointment->token);
});

test('too many cancellation attempts show a message on the appointment page', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 12:00']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('cita.cancel', $appointment->token), []);
    }

    $this->from(route('cita.show', $appointment->token))
        ->post(route('cita.cancel', $appointment->token), ['confirm' => '1'])
        ->assertRedirect(route('cita.show', $appointment->token));

    $this->get(route('cita.show', $appointment->token))
        ->assertSee('Demasiados intentos. Espera un minuto y vuelve a probar.');
});
